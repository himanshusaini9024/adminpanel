@extends('backend.layouts.master')
@section('title', 'E-SHOP || Stock history')

@section('main-content')
<div class="mb-3">
    <a href="{{ route('inventory.index') }}" class="btn btn-sm btn-secondary">&larr; Back to Inventory</a>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">
            Stock history
            @if($product)
                — {{ $product->title }} <small class="text-muted">({{ $product->sku }})</small>
            @endif
        </h6>
        @if($product)
            <a href="{{ route('inventory.history') }}" class="btn btn-sm btn-outline-secondary">All products</a>
        @endif
    </div>
    <div class="card-body">
        @if($movements->isEmpty())
            <p class="text-muted mb-0">No stock changes yet.</p>
        @else
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="thead-light">
                    <tr>
                        <th>Date</th>
                        @unless($product)<th>Product</th>@endunless
                        <th>Size</th>
                        <th class="text-center">Change</th>
                        <th class="text-center">Stock after</th>
                        <th>Reason</th>
                        <th>Reference</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($movements as $m)
                        <tr>
                            <td class="text-nowrap">{{ $m->created_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</td>
                            @unless($product)
                                <td>
                                    <a href="{{ route('inventory.history', ['product' => $m->product_id]) }}">
                                        {{ $m->product->title ?? ('#' . $m->product_id) }}
                                    </a>
                                </td>
                            @endunless
                            <td>{{ $m->size === '' ? 'One size' : $m->size }}</td>
                            <td class="text-center font-weight-bold {{ $m->change < 0 ? 'text-danger' : 'text-success' }}">
                                {{ $m->change > 0 ? '+' : '' }}{{ $m->change }}
                            </td>
                            <td class="text-center {{ $m->stock_after < 0 ? 'text-danger font-weight-bold' : '' }}">
                                {{ $m->stock_after }}
                            </td>
                            <td>
                                {{ $m->reason_label }}
                                @if($m->note)<br><small class="text-muted">{{ $m->note }}</small>@endif
                            </td>
                            <td>
                                @if($m->reference_type === 'Order' && $m->reference_id)
                                    <a href="{{ route('order.show', $m->reference_id) }}">
                                        Order #{{ env('ORDER_PREFIX') }}{{ $orderNumbers[$m->reference_id] ?? $m->reference_id }}
                                    </a>
                                @elseif($m->reference_type === 'ReturnOrder' && $m->reference_id)
                                    Return #{{ $m->reference_id }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $m->user->name ?? ($m->user_id ? '#' . $m->user_id : 'System') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $movements->links() }}
        @endif
    </div>
</div>
@endsection
