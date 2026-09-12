@extends('backend.layouts.master')

@section('title','Order Detail')

@section('main-content')
<div class="card">
<h5 class="card-header">Order    
     <!-- <a href="{{route('order.pdf',$order->id)}}" class=" btn btn-sm btn-primary shadow-sm float-right"><i class="fas fa-download fa-sm text-white-50"></i> Generate PDF</a> -->
  </h5>
  <div class="card-body">
    @if($order)
    <table class="table table-striped table-hover">
      <thead>
        <tr>
            <th>S.N.</th>
            <th>Order No.</th>
            <th>Name</th>
            <th>Email</th>
            <th>Quantity</th>
            <th>Total Amount</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <tr>
            <td>{{$order->id}}</td>
            <td>{{$order->order_number}}</td>
            <td>{{$order->first_name}} {{$order->last_name}}</td>
            <td>{{$order->email}}</td>
            <td>{{$order->quantity}}</td>
            <td>₹{{number_format($order->total_amount,2)}}</td>
            <td>
                @if($order->status=='new')
                  <span class="badge badge-primary">{{$order->status}}</span>
                @elseif($order->status=='process')
                  <span class="badge badge-warning">{{$order->status}}</span>
                @elseif($order->status=='delivered')
                  <span class="badge badge-success">{{$order->status}}</span>
                @else
                  <span class="badge badge-danger">{{$order->status}}</span>
                @endif
            </td>
            <td>
                <a href="{{route('order.edit',$order->id)}}" class="btn btn-primary btn-sm float-left mr-1" style="height:30px; width:30px;border-radius:50%" data-toggle="tooltip" title="edit" data-placement="bottom"><i class="fas fa-edit"></i></a>
                <form method="POST" action="{{route('order.destroy',[$order->id])}}">
                  @csrf
                  @method('delete')
                      <button class="btn btn-danger btn-sm dltBtn" data-id={{$order->id}} style="height:30px; width:30px;border-radius:50%" data-toggle="tooltip" data-placement="bottom" title="Delete"><i class="fas fa-trash-alt"></i></button>
                </form>
            </td>

        </tr>
      </tbody>
    </table>

    <section class="confirmation_part section_padding">
      <div class="order_boxes">
        <div class="row">
          <div class="col-lg-6 col-lx-4">
            <div class="order-info">
              <h4 class="text-center pb-4">ORDER INFORMATION</h4>
              <table class="table">
                    <tr class="">
                        <td>Order Number</td>
                        <td> : {{$order->order_number}}</td>
                    </tr>
                    <tr>
                        <td>Order Date</td>
                        <td> : {{$order->created_at->format('D d M, Y')}} at {{$order->created_at->format('g : i a')}} </td>
                    </tr>
                    <tr>
                        <td>Quantity</td>
                        <td> : {{$order->quantity}}</td>
                    </tr>
                    <tr>
                        <td>Order Status</td>
                        <td> : {{$order->status}}</td>
                    </tr>
                 
                    <tr>
                      <td>Coupon</td>
                      <td> : ₹ {{number_format($order->coupon,2)}}</td>
                    </tr>
                    <tr>
                        <td>Total Amount</td>
                        <td> : ₹ {{number_format($order->total_amount,2)}}</td>
                    </tr>
                    <tr>
                        <td>Payment Method</td>
                        <td> : @if($order->payment_method=='cod') Cash on Delivery @else online @endif</td>
                    </tr>
                    <tr>
                        <td>Payment Status</td>
                        <td> : {{$order->payment_status}}</td>
                    </tr>
              </table>
            </div>
          </div>

          <div class="col-lg-6 col-lx-4">
            <div class="shipping-info">
              <h4 class="text-center pb-4">SHIPPING INFORMATION</h4>
              <table class="table">
                    <tr class="">
                        <td>Full Name</td>
                        <td> : {{$order->first_name}} </td>
                    </tr>
                    <tr>
                        <td>Email</td>
                        <td> : {{$order->email}}</td>
                    </tr>
                    <tr>
                        <td>Phone No.</td>
                        <td> : {{$order->phone}}</td>
                    </tr>
                    <tr>
                        <td>Address</td>
                        <td> : {{ collect([$order->address1, $order->address2, $order->city, $order->state])->filter()->implode(', ') }}</td>
                    </tr>
                    <tr>
                        <td>City</td>
                        <td> : {{ $order->city ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td>State</td>
                        <td> : {{ $order->state ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td>Country</td>
                        <td> : {{$order->country}}</td>
                    </tr>
                    <tr>
                        <td>Post Code</td>
                        <td> : {{$order->post_code}}</td>
                    </tr>
              </table>
            </div>
          </div>
        </div>

        {{-- Ordered products --}}
        <div class="row mt-4">
          <div class="col-12">
            <div class="order-items-info">
              <h4 class="text-center pb-3">ORDERED PRODUCTS</h4>
              @php
                $lineItems = $order->items;
                if ($lineItems->isEmpty() && $order->cart_info) {
                  $lineItems = $order->cart_info;
                }
              @endphp
              @if($lineItems->isNotEmpty())
              <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0">
                  <thead class="thead-light">
                    <tr>
                      <th style="width:80px;">Image</th>
                      <th>Product</th>
                      <th>Size</th>
                      <th>Color</th>
                      <th>SKU</th>
                      <th class="text-right">Price</th>
                      <th class="text-center">Qty</th>
                      <th class="text-right">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($lineItems as $item)
                      @php
                        $name = $item->name
                          ?? optional($item->product)->title
                          ?? optional($item->product)->name
                          ?? 'Product';
                        $image = $item->image
                          ?? optional($item->product)->photo
                          ?? null;
                        if (is_string($image) && str_starts_with(trim($image), '[')) {
                          $decoded = json_decode($image, true);
                          $image = is_array($decoded) ? ($decoded[0] ?? null) : $image;
                        }
                        $imgUrl = $image
                          ? (function_exists('media_url') ? media_url($image) : $image)
                          : asset('backend/img/thumbnail-default.jpg');
                        $size = $item->size ?? '—';
                        $color = $item->color ?? '—';
                        $sku = $item->sku ?? '—';
                        $price = (float) ($item->price ?? 0);
                        $qty = (int) ($item->quantity ?? $item->qty ?? 1);
                        $lineTotal = $price * $qty;
                      @endphp
                      <tr>
                        <td>
                          <img src="{{ $imgUrl }}" alt="{{ $name }}"
                               style="width:64px;height:64px;object-fit:cover;border-radius:4px;background:#eee;">
                        </td>
                        <td>{{ $name }}</td>
                        <td>{{ $size ?: '—' }}</td>
                        <td>{{ $color ?: '—' }}</td>
                        <td><code>{{ $sku ?: '—' }}</code></td>
                        <td class="text-right">₹{{ number_format($price, 2) }}</td>
                        <td class="text-center">{{ $qty }}</td>
                        <td class="text-right">₹{{ number_format($lineTotal, 2) }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
              @else
                <p class="text-muted text-center mb-0">No products found for this order.</p>
              @endif
            </div>
          </div>
        </div>
      </div>
    </section>
    @endif

  </div>
</div>
@endsection

@push('styles')
<style>
    .order-info,.shipping-info,.order-items-info{
        background:#ECECEC;
        padding:20px;
    }
    .order-info h4,.shipping-info h4,.order-items-info h4{
        text-decoration: underline;
    }

</style>
@endpush
