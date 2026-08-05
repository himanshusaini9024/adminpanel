@extends('emails.order-status-layout')

@section('content')
  <p style="margin:0 0 16px;">Hi {{ $order->first_name }},</p>
  <h2 style="margin:0 0 16px;font-size:22px;color:#0b1f17;">Your order is on the way!</h2>
  <p style="margin:0 0 16px;color:#444;">
    Great news — order <strong>#{{ $order->order_number }}</strong> has been shipped
    @if($order->courier_name)
      via <strong>{{ $order->courier_name }}</strong>
    @endif.
  </p>
  @if($order->awb_code)
    <p style="margin:0 0 8px;"><strong>Tracking / AWB:</strong> {{ $order->awb_code }}</p>
  @endif
  @if($order->expected_delivery_date)
    <p style="margin:0 0 16px;"><strong>Expected delivery:</strong> {{ $order->expected_delivery_date->format('d M Y') }}</p>
  @endif
  <p style="margin:0;color:#666;font-size:14px;">We'll notify you again when it's out for delivery.</p>
@endsection
