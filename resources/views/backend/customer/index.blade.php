@extends('backend.layouts.master')
@section('title','E-SHOP || Customers')

@section('main-content')
<div class="card shadow mb-4">
    <div class="row">
        <div class="col-md-12">
            @include('backend.layouts.notification')
        </div>
    </div>
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary float-left">Customers</h6>
    </div>
    <div class="card-body">
        <form method="get" class="form-inline mb-3" action="{{ route('customer.index') }}">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control mr-2 mb-2" placeholder="Search name, email, phone">
            <label class="mr-2 mb-2">
                <input type="checkbox" name="has_cart" value="1" {{ request('has_cart') === '1' ? 'checked' : '' }}>
                Has cart items
            </label>
            <button type="submit" class="btn btn-primary btn-sm mb-2 mr-2">Filter</button>
            <a href="{{ route('customer.index') }}" class="btn btn-secondary btn-sm mb-2">Reset</a>
        </form>

        <div class="table-responsive">
            @if($customers->count() > 0)
            <table class="table table-bordered" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>City</th>
                        <th>Cart</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customers as $customer)
                        @php $cartCount = count($customer->cart_items); @endphp
                        <tr>
                            <td>{{ $customer->customer_id }}</td>
                            <td>{{ $customer->full_name }}</td>
                            <td>{{ $customer->email ?: '—' }}</td>
                            <td>{{ $customer->phone ?: '—' }}</td>
                            <td>{{ $customer->city ?: '—' }}</td>
                            <td>
                                @if($cartCount > 0)
                                    <span class="badge badge-info">{{ $cartCount }} item(s)</span>
                                @else
                                    <span class="text-muted">Empty</span>
                                @endif
                            </td>
                            <td>
                                @if(($customer->status ?? '') === 'active' || ($customer->status ?? '') === '1')
                                    <span class="badge badge-success">{{ $customer->status ?: 'active' }}</span>
                                @else
                                    <span class="badge badge-warning">{{ $customer->status ?: '—' }}</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('customer.edit', $customer->customer_id) }}"
                                   class="btn btn-primary btn-sm"
                                   style="height:30px;width:30px;border-radius:50%;padding:4px;"
                                   title="Edit / View">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <span style="float:right">{{ $customers->links() }}</span>
            @else
                <h6 class="text-center mb-0">No customers found.</h6>
            @endif
        </div>
    </div>
</div>
@endsection
