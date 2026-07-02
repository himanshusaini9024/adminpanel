@extends('backend.layouts.master')

@section('title','Return Requests')

@section('main-content')
@if(session('success'))
<div class="alert alert-success">
    {{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="alert alert-danger">
    {{ session('error') }}
</div>
@endif
<div class="card">
    <h5 class="card-header">
        Return Requests
    </h5>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Reason</th>
                        <th>Comment</th>
                        <th>Status</th>
                        <th>Type</th>
                        <th>Refund Process</th>
                        <th>Reverse AWB</th>
                        <th>Courier</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($returns as $return)

                    <tr class="{{ ($return->type ?? '') === 'exchange' ? 'bg-warning' : '' }}">
                        <td>{{ $return->id }}</td>

                        <td>
                            #{{ $return->order->order_number ?? 'N/A' }}
                        </td>

                        <td>
                            {{ $return->order->first_name ?? '' }}
                        </td>

                        <td>
                            {{ $return->reason }}
                        </td>

                        <td>
                            {{ $return->comment }}
                        </td>

                        <td>

                            @if($return->status == 'pending')
                            <span class="badge badge-warning">
                                Pending
                            </span>

                            @elseif($return->status == 'pickup_scheduled')

                            <span class="badge badge-info">
                                Pickup Scheduled
                            </span>

                            @elseif($return->status == 'rejected')

                            <span class="badge badge-danger">
                                Rejected
                            </span>

                            @elseif($return->status == 'delivered')

                            <span class="badge badge-success">
                                Completed
                            </span>
                            @endif

                        </td>
                        <td>
                            <span class="badge badge-primary">
                                {{ $return->type ?? '-' }}
                            </span>
                        </td>

                        <td>

                            @if($return->type == 'return')

                            @if($return->status == 'delivered')
                            <span class="badge badge-danger">
                                Ready For Refund
                            </span>

                            @elseif($return->status == 'refundprocess')
                            <span class="badge badge-success">
                                Refund Processing
                            </span>

                            @elseif($return->status == 'refunded')
                            <span class="badge badge-success">
                                Refunded
                            </span>
                            @endif

                            @else

                            @if($return->status == 'delivered')
                            <span class="badge badge-info">
                                Ready For Exchange
                            </span>

                            @elseif($return->status == 'replacement_created')
                            <span class="badge badge-primary">
                                Replacement Created
                            </span>

                            @elseif($return->status == 'replacement_shipped')
                            <span class="badge badge-success">
                                Replacement Shipped
                            </span>
                            @endif

                            @endif

                        </td>

                        <td>
                            {{ $return->reverse_awb ?? '-' }}
                        </td>

                        <td>
                            {{ $return->courier ?? '-' }}
                        </td>

                        <td>

                            @if($return->status == 'pending')

                            <form
                                action="{{ url('admin/returns/'.$return->id.'/'.($return->order->items->first()->sku ?? 'NOSKU').'/approve') }}"
                                method="POST"
                                style="display:inline-block">
                                @csrf

                                <button class="btn btn-success btn-sm">
                                    Approve
                                </button>
                            </form>

                            <form
                                action="{{ url('admin/returns/'.$return->id.'/reject') }}"
                                method="POST"
                                style="display:inline-block">
                                @csrf

                                <button class="btn btn-danger btn-sm">
                                    Reject
                                </button>
                            </form>

                            @elseif($return->status == 'delivered')

                            @if($return->type == 'return')

                            <form
                                action="{{ url('admin/refund/'.$return->id.'/'.$return->order->razorpay_payment_id.'/process') }}"
                                method="POST">

                                @csrf

                                <button class="btn btn-warning btn-sm">
                                    Refund
                                </button>

                            </form>

                            @else

                            <form
                                action="{{ url('admin/exchange/'.$return->id.'/process') }}"
                                method="POST">

                                @csrf

                                <button class="btn btn-primary btn-sm">
                                    Create Replacement
                                </button>

                            </form>

                            @endif

                            @else

                            <span class="text-muted">
                                Processed
                            </span>




                            @endif

                        </td>
                    </tr>

                    @empty

                    <tr>
                        <td colspan="9" class="text-center">
                            No return requests found
                        </td>
                    </tr>

                    @endforelse

                </tbody>

            </table>
        </div>
    </div>
</div>

@endsection