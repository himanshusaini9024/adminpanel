<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Shipping;
use App\Models\User;
use PDF;
use Notification;
use Helper;
use Illuminate\Support\Str;
use App\Notifications\StatusNotification;
use App\Jobs\OrderStatusNotificationJob;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $orders=Order::orderBy('id','DESC')->paginate(10);
        return view('backend.order.index')->with('orders',$orders);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'address1' => 'required|string|max:500',
            'address2' => 'nullable|string|max:500',
            'country' => 'required|string|max:100',
            'coupon' => 'nullable|numeric',
            'phone' => 'required|numeric|digits_between:10,15',
            'post_code' => 'nullable|string|max:20',
            'email' => 'required|email|max:255',
            'shipping' => 'nullable|exists:shippings,id',
            'payment_method' => 'required|in:cod,paypal'
        ]);

        $cartItems = Cart::where('user_id', auth()->user()->id)
            ->where('order_id', null)
            ->get();
            
        if ($cartItems->isEmpty()) {
            return back()->with('error', 'Cart is Empty!');
        }
        try {
            $order = new Order();
            $order->order_number = 'ORD-' . strtoupper(Str::random(10));
            $order->user_id = auth()->user()->id;
            $order->first_name = $validated['first_name'];
            $order->last_name = $validated['last_name'];
            $order->email = $validated['email'];
            $order->phone = $validated['phone'];
            $order->country = $validated['country'];
            $order->address1 = $validated['address1'];
            $order->address2 = $validated['address2'] ?? null;
            $order->post_code = $validated['post_code'] ?? null;
            $order->sub_total = Helper::totalCartPrice();
            $order->quantity = Helper::cartCount();
            $order->status = 'new';
            $order->payment_method = $validated['payment_method'];
            
            // Calculate shipping
            $shippingPrice = 0;
            if ($request->filled('shipping')) {
                $shipping = Shipping::find($request->input('shipping'));
                if ($shipping) {
                    $order->shipping_id = $shipping->id;
                    $shippingPrice = (float)$shipping->price;
                }
            }
            
            // Calculate coupon discount
            $couponDiscount = 0;
            if (session('coupon')) {
                $couponDiscount = (float)session('coupon')['value'];
                $order->coupon = $couponDiscount;
            }
            
            // Calculate total
            $order->total_amount = $order->sub_total + $shippingPrice - $couponDiscount;
            
            // Set payment status - PayPal orders remain unpaid until payment is confirmed
            $order->payment_status = 'unpaid';
            
            $order->save();
            
            // For COD: Link cart items and clear session immediately
            // For PayPal: Don't link cart items yet - wait for payment confirmation
            if ($validated['payment_method'] == 'cod') {
                // Update cart items with order_id for COD
                Cart::where('user_id', auth()->user()->id)
                    ->where('order_id', null)
                    ->update(['order_id' => $order->id]);
                
                // Clear session data for COD
                session()->forget('cart');
                session()->forget('coupon');
                
                // Send notification to admin
                $admin = User::where('role', 'admin')->first();
                if ($admin) {
                    $details = [
                        'title' => 'New order created',
                        'actionURL' => route('order.show', $order->id),
                        'fas' => 'fa-file-alt'
                    ];
                    Notification::send($admin, new StatusNotification($details));
                }
                
                return redirect()->route('home')
                    ->with('success', 'Your product successfully placed in order');
            }
            
            // For PayPal: Store order ID in session, but don't link cart items yet
            // Cart items will be linked only after successful payment
            if ($validated['payment_method'] == 'paypal') {
                // Don't send notification yet - wait for payment confirmation
                return redirect()->route('payment')->with(['id' => $order->id]);
            }
                
        } catch (\Exception $e) {
            \Log::error('Order creation failed: ' . $e->getMessage());
            return back()
                ->with('error', 'Something went wrong. Please try again.')
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $order = Order::with(['cart_info.product', 'user', 'shipping'])->findOrFail($id);
        return view('backend.order.show')->with('order', $order);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $order=Order::find($id);
        return view('backend.order.edit')->with('order',$order);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:new,process,shipped,out_for_delivery,delivered,cancel'
        ]);
        
        try {
            $order = Order::with('cart.product')->findOrFail($id);
            $previousStatus = $order->status;
            
            // Update stock when order is delivered
            if ($validated['status'] == 'delivered' && $order->status != 'delivered') {
                foreach ($order->cart as $cart) {
                    $product = $cart->product;
                    if ($product) {
                        $product->stock -= $cart->quantity;
                        if ($product->stock < 0) {
                            $product->stock = 0;
                        }
                        $product->save();
                    }
                }
                $order->delivered_at = $order->delivered_at ?: now();
            }
            
            $order->status = $validated['status'];
            $order->save();

            // Notify customer on shipping milestones (only when status actually changes)
            if ($previousStatus !== $validated['status']) {
                $event = match ($validated['status']) {
                    'shipped' => 'shipment_booked',
                    'out_for_delivery' => 'out_for_delivery',
                    'delivered' => 'delivered',
                    default => null,
                };
                if ($event) {
                    OrderStatusNotificationJob::dispatch($order->id, $event);
                }
            }
            
            return redirect()->route('order.index')
                ->with('success', 'Successfully updated order');
                
        } catch (\Exception $e) {
            \Log::error('Order update failed: ' . $e->getMessage());
            return redirect()->route('order.index')
                ->with('error', 'Error while updating order');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $order = Order::findOrFail($id);
            $order->delete();
            
            return redirect()->route('order.index')
                ->with('success', 'Order successfully deleted');
                
        } catch (\Exception $e) {
            \Log::error('Order deletion failed: ' . $e->getMessage());
            return redirect()->route('order.index')
                ->with('error', 'Order could not be deleted');
        }
    }

    public function orderTrack(){
        return view('frontend.pages.order-track');
    }

    public function productTrackOrder(Request $request)
    {
        $validated = $request->validate([
            'order_number' => 'required|string|max:255'
        ]);
        
        $order = Order::where('user_id', auth()->user()->id)
            ->where('order_number', $validated['order_number'])
            ->first();
            
        if (!$order) {
            return back()->with('error', 'Invalid order number. Please try again.');
        }
        
        $messages = [
            'new' => 'Your order has been placed. Please wait.',
            'process' => 'Your order is under processing. Please wait.',
            'delivered' => 'Your order is successfully delivered.',
            'cancel' => 'Your order has been canceled. Please try again.'
        ];
        
        $message = $messages[$order->status] ?? 'Order status unknown.';
        $type = ($order->status == 'cancel') ? 'error' : 'success';
        
        return redirect()->route('home')->with($type, $message);
    }

    // PDF generate
    public function pdf(Request $request){
        $order=Order::getAllOrder($request->id);
        // return $order;
        $file_name=$order->order_number.'-'.$order->first_name.'.pdf';
        // return $file_name;
        $pdf=PDF::loadview('backend.order.pdf',compact('order'));
        return $pdf->download($file_name);
    }
    // Income chart
    public function incomeChart(Request $request){
        $year=\Carbon\Carbon::now()->year;
        // dd($year);
        $items=Order::with(['cart_info'])->whereYear('created_at',$year)->where('status','delivered')->get()
            ->groupBy(function($d){
                return \Carbon\Carbon::parse($d->created_at)->format('m');
            });
            // dd($items);
        $result=[];
        foreach($items as $month=>$item_collections){
            foreach($item_collections as $item){
                $amount=$item->cart_info->sum('amount');
                // dd($amount);
                $m=intval($month);
                // return $m;
                isset($result[$m]) ? $result[$m] += $amount :$result[$m]=$amount;
            }
        }
        $data=[];
        for($i=1; $i <=12; $i++){
            $monthName=date('F', mktime(0,0,0,$i,1));
            $data[$monthName] = (!empty($result[$i]))? number_format((float)($result[$i]), 2, '.', '') : 0.0;
        }
        return $data;
    }
}
