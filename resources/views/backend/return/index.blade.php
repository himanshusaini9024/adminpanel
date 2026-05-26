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
                        <th>Reverse AWB</th>
                        <th>Courier</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($returns as $return)
 
                    <tr>
                        <td>{{ $return->id }}</td>

                        <td>
                            #{{ $return->order->order_number ?? 'N/A' }}
                        </td>

                        <td>
                            {{ $return->order->first_name ?? '' }}
                            {{ $return->order->last_name ?? '' }}
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

                            @elseif($return->status == 'completed')

                                <span class="badge badge-success">
                                    Completed
                                </span>
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
                                style="display:inline-block"
                            >
                                @csrf

                                <button class="btn btn-success btn-sm">
                                    Approve
                                </button>
                            </form>

                            <form
                                action="{{ url('admin/returns/'.$return->id.'/reject') }}"
                                method="POST"
                                style="display:inline-block"
                            >
                                @csrf

                                <button class="btn btn-danger btn-sm">
                                    Reject
                                </button>
                            </form>

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