@extends('emails.order-status-layout')

@section('content')
  <p style="margin:0 0 16px;">Hi {{ $order->first_name ?: 'there' }},</p>
  <h2 style="margin:0 0 16px;font-size:22px;color:#0b1f17;">{{ $headline }}</h2>
  <p style="margin:0 0 16px;color:#444;">
    {!! $bodyHtml !!}
  </p>
  @if(!empty($return->reason))
    <p style="margin:0 0 16px;color:#666;font-size:14px;">
      Reason: {{ $return->reason }}
    </p>
  @endif
  <p style="margin:0;color:#666;font-size:14px;">
    Thank you for shopping with Dhirago.
  </p>
@endsection
