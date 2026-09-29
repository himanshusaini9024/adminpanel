@extends('backend.layouts.master')
@section('title', 'E-SHOP || Inventory')

@section('main-content')
<div class="row">
    <div class="col-md-12">
        @include('backend.layouts.notification')
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-wrap align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">Inventory</h6>
        <a href="{{ route('inventory.history') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-history"></i> Stock history
        </a>
    </div>

    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap:10px;">
            <div class="btn-group btn-group-sm" role="group">
                @foreach([
                    'all' => ['All products', 'secondary'],
                    'out' => ['Out of stock', 'danger'],
                    'some_out' => ['Some sizes out', 'warning'],
                    'low' => ['Low stock (≤ ' . $lowThreshold . ')', 'info'],
                ] as $key => [$label, $color])
                    <a href="{{ route('inventory.index', array_filter(['filter' => $key, 'q' => $search])) }}"
                       class="btn btn-{{ $filter === $key ? $color : 'outline-' . $color }}">
                        {{ $label }} <span class="badge badge-light">{{ $counts[$key] }}</span>
                    </a>
                @endforeach
            </div>

            <form method="get" class="form-inline">
                <input type="hidden" name="filter" value="{{ $filter }}">
                <input type="text" name="q" value="{{ $search }}" class="form-control form-control-sm mr-2"
                       placeholder="Search title or SKU">
                <button class="btn btn-sm btn-primary">Search</button>
            </form>
        </div>

        @if($rows->isEmpty())
            <p class="text-muted mb-0">No products match this filter.</p>
        @else
        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle">
                <thead class="thead-light">
                    <tr>
                        <th>Product</th>
                        <th>Stock per size</th>
                        <th class="text-center" style="width:90px;">Total</th>
                        <th style="width:120px;">Status</th>
                        <th style="width:190px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        @php $product = $row['product']; @endphp
                        <tr>
                            <td>
                                <div class="font-weight-bold">{{ $product->title }}</div>
                                <small class="text-muted">SKU: {{ $product->sku ?: '-' }}</small>
                                @if($product->status !== 'active')
                                    <span class="badge badge-secondary ml-1">inactive</span>
                                @endif
                            </td>
                            <td>
                                <form method="post" action="{{ route('inventory.update', $product->id) }}"
                                      id="stock-form-{{ $product->id }}" class="d-flex flex-wrap" style="gap:8px;">
                                    @csrf
                                    @foreach($row['map'] as $size => $qty)
                                        <label class="mb-0 text-center" style="width:64px;">
                                            <small class="d-block font-weight-bold">{{ $size === '' ? 'One size' : $size }}</small>
                                            <input type="number" min="0" step="1"
                                                   name="size_stock[{{ $size === '' ? '_none' : $size }}]"
                                                   value="{{ max(0, (int) $qty) }}"
                                                   class="form-control form-control-sm text-center
                                                        {{ $qty <= 0 ? 'border-danger text-danger' : ($qty <= $lowThreshold ? 'border-warning' : '') }}">
                                            @if($qty < 0)
                                                <small class="text-danger d-block">oversold {{ abs($qty) }}</small>
                                            @endif
                                        </label>
                                    @endforeach
                                </form>
                            </td>
                            <td class="text-center font-weight-bold">{{ $row['total'] }}</td>
                            <td>
                                @if($row['out'])
                                    <span class="badge badge-danger">Out of stock</span>
                                @elseif($row['some_out'])
                                    <span class="badge badge-warning">Some sizes out</span>
                                @elseif($row['low'])
                                    <span class="badge badge-info">Low stock</span>
                                @else
                                    <span class="badge badge-success">In stock</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                <button type="submit" form="stock-form-{{ $product->id }}" class="btn btn-sm btn-primary">
                                    Save
                                </button>
                                <a href="{{ route('inventory.history', ['product' => $product->id]) }}"
                                   class="btn btn-sm btn-outline-secondary">History</a>
                                <a href="{{ route('product.edit', $product->id) }}"
                                   class="btn btn-sm btn-outline-secondary" title="Edit product">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <small class="text-muted">
            Enter the actual stock you have for each size and click Save. Orders, cancellations and
            returns update these numbers automatically — see Stock history for every change.
        </small>
        @endif
    </div>
</div>
@endsection
