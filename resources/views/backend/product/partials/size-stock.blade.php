@php
    $allSizes = ['S' => 'Small (S)', 'M' => 'Medium (M)', 'L' => 'Large (L)', 'XL' => 'Extra Large (XL)'];
    $offered = array_filter(array_map(fn ($s) => strtoupper(trim($s)), explode(',', (string) ($productSizes ?? ''))));
    $stockMap = $stockMap ?? [];
    $oldSizes = old('size');
    $oldStock = old('size_stock', []);
@endphp

<div class="size-stock-table">
    <table class="table table-sm table-bordered mb-1" style="max-width:520px;">
        <thead>
            <tr>
                <th style="width:40%;">Size</th>
                <th style="width:20%;" class="text-center">Offer</th>
                <th>Stock</th>
            </tr>
        </thead>
        <tbody>
            @foreach($allSizes as $code => $label)
                @php
                    $checked = is_array($oldSizes) ? in_array($code, $oldSizes, true) : in_array($code, $offered, true);
                    $qty = $oldStock[$code] ?? ($stockMap[$code] ?? 0);
                @endphp
                <tr>
                    <td>{{ $label }}</td>
                    <td class="text-center">
                        <input type="checkbox" name="size[]" value="{{ $code }}" class="size-offer" {{ $checked ? 'checked' : '' }}>
                    </td>
                    <td>
                        <input type="number" name="size_stock[{{ $code }}]" min="0" step="1"
                               class="form-control form-control-sm size-stock-input" value="{{ (int) $qty }}">
                    </td>
                </tr>
            @endforeach
            <tr class="one-size-row">
                <td>No size (one size)</td>
                <td class="text-center text-muted">—</td>
                <td>
                    <input type="number" name="size_stock[_none]" min="0" step="1"
                           class="form-control form-control-sm size-stock-input"
                           value="{{ (int) ($oldStock['_none'] ?? ($stockMap[''] ?? 0)) }}">
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" class="text-right">Total in stock</th>
                <th><span class="size-stock-total">0</span></th>
            </tr>
        </tfoot>
    </table>
    <small class="text-muted d-block">
        Tick the sizes you sell. A size with stock 0 shows as <strong>out of stock</strong> on the website.
        "No size" is used only when no size is ticked. Stock also changes automatically on orders, cancellations and returns.
    </small>
    @error('size_stock') <div class="text-danger small">{{ $message }}</div> @enderror
    @error('size_stock.*') <div class="text-danger small">{{ $message }}</div> @enderror
</div>

@once
@push('scripts')
<script>
(function () {
    document.querySelectorAll('.size-stock-table').forEach(function (box) {
        const refresh = function () {
            const offers = box.querySelectorAll('.size-offer');
            const anySize = Array.from(offers).some(function (o) { return o.checked; });
            let total = 0;

            offers.forEach(function (offer) {
                const input = offer.closest('tr').querySelector('.size-stock-input');
                input.disabled = !offer.checked;
                if (offer.checked) total += Math.max(0, parseInt(input.value || '0', 10));
            });

            const oneSize = box.querySelector('.one-size-row');
            const oneInput = oneSize.querySelector('input');
            oneSize.style.display = anySize ? 'none' : '';
            oneInput.disabled = anySize;
            if (!anySize) total = Math.max(0, parseInt(oneInput.value || '0', 10));

            box.querySelector('.size-stock-total').textContent = total;
        };

        box.addEventListener('input', refresh);
        box.addEventListener('change', refresh);
        refresh();
    });
})();
</script>
@endpush
@endonce
