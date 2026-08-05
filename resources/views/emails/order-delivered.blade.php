@extends('emails.order-status-layout')

@section('content')
  <p style="margin:0 0 16px;">Hi {{ $order->first_name }},</p>
  <h2 style="margin:0 0 16px;font-size:22px;color:#0b1f17;">Order delivered</h2>
  <p style="margin:0 0 16px;color:#444;">
    Your order <strong>#{{ $order->order_number }}</strong> has been delivered successfully.
    We hope you love it!
  </p>
  <p style="margin:0;color:#666;font-size:14px;">
    Thank you for shopping with {{ config('app.name', 'Dhirago') }}.
  </p>
@endsection
