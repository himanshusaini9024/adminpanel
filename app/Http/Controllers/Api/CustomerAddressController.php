<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CustomerAddress;

class CustomerAddressController extends Controller
{
    // ✅ GET addresses
    public function index(Request $request)
    {
        // $customer = auth('customer')->user();
        $customer = $request->user();

        return response()->json(
            $customer->addresses()->latest()->get()
        );
    }

    // ✅ SAVE address
    public function store(Request $request)
    {
        // $customer = auth('customer')->user();
        
        $customer = $request->user();

        $data = $request->validate([
            'name' => 'required',
            'phone' => 'required',
            'address1' => 'required',
            'address2' => 'nullable',
            'city' => 'required',
            'state' => 'required',
            'pincode' => 'required',
            'type' => 'required'
        ]);

        // 👉 first address = default
        // $isDefault = $customer->addresses()->count() === 0;

        // $address = CustomerAddress::create([
        //     'customer_id' => $customer->customer_id,
        //     'name' => $request->name,
        //     'phone' => $request->phone,
        //     'address' => $request->address,
        //     'city' => $request->city,
        //     'state' => $request->state,
        //     'pincode' => $request->pincode,
        //     'is_default' => $isDefault,
        // ]);

        // return response()->json($address);

          if ($request->is_default) {
            CustomerAddress::where('customer_id', $customer->customer_id)
                ->update(['is_default' => false]);
        }

        $data['customer_id'] = $customer->customer_id;
        $data['is_default'] = $request->is_default ?? false;

        return CustomerAddress::create($data);
    }

    // ✅ SET DEFAULT
    public function setDefault($id, Request $request)
    {
        // $customer = auth('customer')->user();
        $customer = $request->user();

        // remove old default
        CustomerAddress::where('customer_id', $customer->customer_id)
            ->update(['is_default' => false]);

        // set new
           CustomerAddress::where('id', $id)
            ->update(['is_default' => true]);

        return response()->json(['message' => 'Updated']);
    }

    // ✅ DELETE
    public function destroy($id)
    {
        CustomerAddress::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}