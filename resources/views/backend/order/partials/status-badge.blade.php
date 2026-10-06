@php
  $badgeClass = match ($order->status) {
      'new' => 'badge-primary',
      'process', 'out_for_delivery' => 'badge-warning',
      'shipped' => 'badge-info',
      'delivered', 'exchanged' => 'badge-success',
      'undelivered', 'rto', 'rto_delivered', 'lost' => 'badge-danger',
      default => 'badge-secondary',
  };
@endphp
<span class="badge {{ $badgeClass }}">{{ $order->status_label }}</span>
