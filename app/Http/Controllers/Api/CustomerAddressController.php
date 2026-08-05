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

    // ✅ UPDATE address
    public function update(Request $request, $id)
    {
        $customer = $request->user();

        $address = CustomerAddress::where('id', $id)
            ->where('customer_id', $customer->customer_id)
            ->firstOrFail();

        $data = $request->validate([
            'name' => 'required',
            'phone' => 'required',
            'address1' => 'required',
            'address2' => 'nullable',
            'city' => 'required',
            'state' => 'required',
            'pincode' => 'required',
            'type' => 'required',
        ]);

        if ($request->boolean('is_default')) {
            CustomerAddress::where('customer_id', $customer->customer_id)
                ->update(['is_default' => false]);
            $data['is_default'] = true;
        }

        $address->update($data);

        return response()->json($address->fresh());
    }

    // ✅ SET DEFAULT
    public function setDefault($id, Request $request)
    {
        // $customer = auth('customer')->user();
        $customer = $request->user();

        $address = CustomerAddress::where('id', $id)
            ->where('customer_id', $customer->customer_id)
            ->firstOrFail();

        // remove old default
        CustomerAddress::where('customer_id', $customer->customer_id)
            ->update(['is_default' => false]);

        // set new
        $address->update(['is_default' => true]);

        return response()->json(['message' => 'Updated']);
    }

    // ✅ DELETE
    public function destroy(Request $request, $id)
    {
        $customer = $request->user();

        CustomerAddress::where('id', $id)
            ->where('customer_id', $customer->customer_id)
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'Deleted']);
    }
}