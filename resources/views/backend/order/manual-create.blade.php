@extends('backend.layouts.master')

@section('title', 'Create Manual Order')

@section('main-content')
<div class="card shadow mb-4">
  <div class="row">
    <div class="col-md-12">
      @include('backend.layouts.notification')
    </div>
  </div>
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary float-left">Create order (WhatsApp / bank transfer)</h6>
    <a href="{{ route('order.index') }}" class="btn btn-sm btn-outline-secondary float-right">Back to orders</a>
  </div>
  <div class="card-body">
    @if($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0 pl-3">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('manual-order.store') }}" enctype="multipart/form-data" id="manual-order-form">
      @csrf

      <h6 class="font-weight-bold text-dark mb-3">1. Customer</h6>
      <div class="form-row">
        <div class="form-group col-md-4">
          <label>First name <span class="text-danger">*</span></label>
          <input type="text" name="first_name" value="{{ old('first_name') }}" class="form-control" required>
        </div>
        <div class="form-group col-md-4">
          <label>Last name</label>
          <input type="text" name="last_name" value="{{ old('last_name') }}" class="form-control">
        </div>
        <div class="form-group col-md-4">
          <label>Order came from <span class="text-danger">*</span></label>
          <select name="order_source" class="form-control">
            @foreach($sources as $value => $label)
              <option value="{{ $value }}" @selected(old('order_source', 'whatsapp') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group col-md-4">
          <label>Mobile number <span class="text-danger">*</span></label>
          <input type="text" name="phone" value="{{ old('phone') }}" class="form-control" placeholder="10-digit mobile" required>
          <small class="text-muted">The order is saved to the customer's account for this number (created if new), so they see it after logging in on the website.</small>
        </div>
        <div class="form-group col-md-4">
          <label>Email</label>
          <input type="email" name="email" value="{{ old('email') }}" class="form-control" placeholder="Optional">
          <small class="text-muted">Needed for the email confirmation.</small>
        </div>
      </div>

      <h6 class="font-weight-bold text-dark mb-3 mt-2">2. Delivery address</h6>
      <div class="form-row">
        <div class="form-group col-md-6">
          <label>Address line 1 <span class="text-danger">*</span></label>
          <input type="text" name="address1" value="{{ old('address1') }}" class="form-control" required>
        </div>
        <div class="form-group col-md-6">
          <label>Address line 2</label>
          <input type="text" name="address2" value="{{ old('address2') }}" class="form-control">
        </div>
        <div class="form-group col-md-4">
          <label>City <span class="text-danger">*</span></label>
          <input type="text" name="city" value="{{ old('city') }}" class="form-control" required>
        </div>
        <div class="form-group col-md-4">
          <label>State <span class="text-danger">*</span></label>
          <input type="text" name="state" value="{{ old('state') }}" class="form-control" required>
        </div>
        <div class="form-group col-md-4">
          <label>Pincode <span class="text-danger">*</span></label>
          <input type="text" name="pincode" value="{{ old('pincode') }}" class="form-control" maxlength="6" required>
        </div>
      </div>

      <h6 class="font-weight-bold text-dark mb-3 mt-2">3. Products</h6>
      <div class="table-responsive">
        <table class="table table-bordered mb-2" id="items-table">
          <thead class="thead-light">
            <tr>
              <th style="min-width:260px">Product</th>
              <th style="width:120px">Size</th>
              <th style="width:110px">Qty</th>
              <th style="width:150px">Price (₹ each)</th>
              <th style="width:130px" class="text-right">Total</th>
              <th style="width:50px"></th>
            </tr>
          </thead>
          <tbody></tbody>
          <tfoot>
            <tr>
              <td colspan="4" class="text-right font-weight-bold">Order total</td>
              <td class="text-right font-weight-bold" id="order-total">₹0.00</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <button type="button" class="btn btn-sm btn-outline-primary mb-4" id="add-item"><i class="fas fa-plus"></i> Add product</button>
      <p class="text-muted small mt-n3">Price fills in from the product's selling price. Change it if you agreed a different price with the customer.</p>

      <h6 class="font-weight-bold text-dark mb-3 mt-2">4. Payment (bank transfer)</h6>
      <div class="form-row">
        <div class="form-group col-md-4">
          <label>Payment status <span class="text-danger">*</span></label>
          <select name="payment_status" class="form-control" id="payment-status">
            <option value="paid" @selected(old('payment_status', 'paid') === 'paid')>Paid, money received</option>
            <option value="unpaid" @selected(old('payment_status') === 'unpaid')>Not paid yet</option>
          </select>
        </div>
        <div class="form-group col-md-4 paid-only">
          <label>UTR / transaction reference <span class="text-danger">*</span></label>
          <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" class="form-control">
        </div>
        <div class="form-group col-md-4 paid-only">
          <label>Payment date</label>
          <input type="datetime-local" name="paid_at" value="{{ old('paid_at') }}" class="form-control">
          <small class="text-muted">Leave empty for now.</small>
        </div>
        <div class="form-group col-md-6">
          <label>Payment screenshot</label>
          <input type="file" name="payment_proof" class="form-control-file" accept="image/*,application/pdf">
          <small class="text-muted">Optional. JPG, PNG, WEBP or PDF, up to 5 MB. Only admins can view it.</small>
        </div>
        <div class="form-group col-md-6">
          <label>Internal note</label>
          <textarea name="admin_note" rows="2" class="form-control" placeholder="Optional, not shown to the customer">{{ old('admin_note') }}</textarea>
        </div>
      </div>
      <div class="alert alert-info unpaid-only py-2">
        The order is saved as unpaid and is <strong>not</strong> sent to Shiprocket. Open it and click
        <strong>Mark as paid</strong> when the money arrives; it is booked with Shiprocket then.
      </div>

      <div class="form-group form-check">
        <input type="hidden" name="notify_customer" value="0">
        <input type="checkbox" class="form-check-input" id="notify-customer" name="notify_customer" value="1" @checked(old('notify_customer', '1') === '1')>
        <label class="form-check-label" for="notify-customer">Send the order confirmation to the customer (email and WhatsApp)</label>
      </div>

      <button type="submit" class="btn btn-success" id="submit-order">Create order</button>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
  (function () {
    const products = @json($productOptions);
    const initialItems = @json(array_values(old('items', [])));
    const byId = Object.fromEntries(products.map((p) => [String(p.id), p]));
    const $tbody = $('#items-table tbody');
    let rowIndex = 0;

    const money = (n) => '₹' + (Number(n) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function productOptions(selected) {
      return '<option value="">Select product…</option>' + products.map((p) =>
        `<option value="${p.id}" ${String(selected) === String(p.id) ? 'selected' : ''}>${escapeHtml(p.title)} (${escapeHtml(p.sku || '')})</option>`
      ).join('');
    }

    function sizeOptions(product, selected) {
      if (!product || !product.sizes.length) return '<option value="">One size</option>';
      return product.sizes.map((s) =>
        `<option value="${s}" ${String(selected || '').toUpperCase() === s ? 'selected' : ''}>${s}</option>`
      ).join('');
    }

    function addRow(item) {
      item = item || {};
      const i = rowIndex++;
      const product = byId[String(item.product_id || '')];
      const $row = $(`
        <tr>
          <td><select name="items[${i}][product_id]" class="form-control item-product" required>${productOptions(item.product_id)}</select></td>
          <td><select name="items[${i}][size]" class="form-control item-size">${sizeOptions(product, item.size)}</select></td>
          <td><input type="number" name="items[${i}][quantity]" class="form-control item-qty" min="1" max="100" value="${item.quantity || 1}" required></td>
          <td><input type="number" name="items[${i}][price]" class="form-control item-price" min="0" step="0.01" value="${item.price ?? (product ? product.price : '')}" required></td>
          <td class="text-right align-middle item-total">₹0.00</td>
          <td class="align-middle"><button type="button" class="btn btn-sm btn-link text-danger remove-item" title="Remove"><i class="fas fa-times"></i></button></td>
        </tr>`);
      $tbody.append($row);
      recalc();
    }

    function recalc() {
      let total = 0;
      $tbody.find('tr').each(function () {
        const line = (Number($(this).find('.item-qty').val()) || 0) * (Number($(this).find('.item-price').val()) || 0);
        $(this).find('.item-total').text(money(line));
        total += line;
      });
      $('#order-total').text(money(total));
      $('.remove-item').prop('disabled', $tbody.find('tr').length <= 1);
    }

    $tbody.on('change', '.item-product', function () {
      const $row = $(this).closest('tr');
      const product = byId[$(this).val()];
      $row.find('.item-size').html(sizeOptions(product));
      $row.find('.item-price').val(product ? product.price : '');
      recalc();
    });
    $tbody.on('input change', '.item-qty, .item-price', recalc);
    $tbody.on('click', '.remove-item', function () {
      $(this).closest('tr').remove();
      recalc();
    });
    $('#add-item').on('click', () => addRow());

    function togglePayment() {
      const paid = $('#payment-status').val() === 'paid';
      $('.paid-only').toggle(paid);
      $('.unpaid-only').toggle(!paid);
      $('.paid-only input[name="payment_reference"]').prop('required', paid);
    }
    $('#payment-status').on('change', togglePayment);
    togglePayment();

    $('#manual-order-form').on('submit', function () {
      $('#submit-order').prop('disabled', true).text('Creating…');
    });

    (initialItems.length ? initialItems : [{}]).forEach(addRow);
  })();
</script>
@endpush
