@extends('emails.order-status-layout')

@section('content')
  <p style="margin:0 0 16px;">Hi {{ $order->first_name }},</p>
  <h2 style="margin:0 0 16px;font-size:22px;color:#0b1f17;">Out for delivery</h2>
  <p style="margin:0 0 16px;color:#444;">
    Your order <strong>#{{ $order->order_number }}</strong> is out for delivery
    @if($order->courier_name)
      with <strong>{{ $order->courier_name }}</strong>
    @endif
    and should reach you soon.
  </p>
  @if($order->awb_code)
    <p style="margin:0 0 16px;"><strong>Tracking / AWB:</strong> {{ $order->awb_code }}</p>
  @endif
  <p style="margin:0;color:#666;font-size:14px;">Please keep your phone reachable for a smooth delivery.</p>
@endsection
