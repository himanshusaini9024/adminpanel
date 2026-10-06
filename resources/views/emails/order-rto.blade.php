@extends('emails.order-status-layout')

@section('content')
  <p style="margin:0 0 16px;">Hi {{ $order->first_name }},</p>
  <h2 style="margin:0 0 16px;font-size:22px;color:#0b1f17;">Your order is being returned to us</h2>
  <p style="margin:0 0 16px;color:#444;">
    Our courier partner could not deliver your order <strong>#{{ $order->order_number }}</strong>,
    so it is on its way back to our warehouse.
  </p>
  @if($order->courier_remark)
    <p style="margin:0 0 16px;"><strong>Courier note:</strong> {{ $order->courier_remark }}</p>
  @endif
  <p style="margin:0;color:#666;font-size:14px;">
    Reply to this email or message us on WhatsApp and our team will help you with re-delivery or the next steps.
  </p>
@endsection
