@extends('backend.layouts.master')

@section('main-content')
<div class="card shadow mb-4">
  <div class="row">
    <div class="col-md-12">
      @include('backend.layouts.notification')
    </div>
  </div>
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary">Payments without order</h6>
    <small class="text-muted">
      Customers paid online through Razorpay but no order was saved (browser closed, UPI app switch, error).
      Normally the Razorpay webhook creates these automatically; anything listed here needs a look.
    </small>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ route('pending-payments.retry') }}" class="form-inline mb-4">
      @csrf
      <label class="mr-2" for="razorpay_order_id">Razorpay order ID</label>
      <input type="text" name="razorpay_order_id" id="razorpay_order_id" class="form-control mr-2" placeholder="order_XXXXXXXXXXXX" required>
      <button type="submit" class="btn btn-primary">Check &amp; create order</button>
    </form>

    @if($checkouts->isEmpty())
      <p class="text-center text-muted mb-0">Nothing here — every payment has an order.</p>
    @else
      <div class="table-responsive">
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Paid at</th>
              <th>Customer</th>
              <th>Amount</th>
              <th>Items</th>
              <th>Razorpay</th>
              <th>Problem</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach($checkouts as $checkout)
              @php $p = $checkout->payload; @endphp
              <tr>
                <td>{{ $checkout->paid_at?->format('d M Y, g:i a') }}</td>
                <td>
                  {{ $p['first_name'] ?? '—' }}<br>
                  <small>{{ $p['phone'] ?? '' }} {{ $p['email'] ?? '' }}</small>
                </td>
                <td>₹{{ number_format((float) $checkout->amount, 2) }}</td>
                <td>
                  @foreach(($p['items'] ?? []) as $item)
                    <div><small>{{ $item['name'] ?? 'Item' }} @if(!empty($item['size']))({{ $item['size'] }})@endif × {{ $item['quantity'] ?? 1 }}</small></div>
                  @endforeach
                </td>
                <td>
                  <small>{{ $checkout->razorpay_order_id }}<br>{{ $checkout->razorpay_payment_id }}</small>
                </td>
                <td><small class="text-danger">{{ $checkout->error ?: 'Waiting for order' }}</small></td>
                <td>
                  <form method="POST" action="{{ route('pending-payments.retry') }}">
                    @csrf
                    <input type="hidden" name="razorpay_order_id" value="{{ $checkout->razorpay_order_id }}">
                    <button type="submit" class="btn btn-sm btn-success">Create order</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
@endsection
