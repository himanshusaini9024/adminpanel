@extends('emails.order-status-layout')

@section('content')
  <p style="margin:0 0 16px;">Hi {{ $order->first_name }},</p>
  <h2 style="margin:0 0 16px;font-size:22px;color:#0b1f17;">Delivery attempt unsuccessful</h2>
  <p style="margin:0 0 16px;color:#444;">
    @if($order->courier_name)
      <strong>{{ $order->courier_name }}</strong> tried
    @else
      Our courier partner tried
    @endif
    to deliver your order <strong>#{{ $order->order_number }}</strong> but could not complete the delivery.
    They will attempt delivery again.
  </p>
  @if($order->courier_remark)
    <p style="margin:0 0 16px;"><strong>Courier note:</strong> {{ $order->courier_remark }}</p>
  @endif
  @if($order->awb_code)
    <p style="margin:0 0 16px;"><strong>Tracking / AWB:</strong> {{ $order->awb_code }}</p>
  @endif
  <p style="margin:0;color:#666;font-size:14px;">
    Please keep your phone reachable. If you need to change your address or delivery time, reply to this email
    or message us on WhatsApp so the parcel is not returned to us.
  </p>
@endsection
