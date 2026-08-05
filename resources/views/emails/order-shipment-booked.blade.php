<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your order has been shipped</title>
  <style>
    body, table, td { -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
    table { border-collapse:collapse !important; }
    img { -ms-interpolation-mode:bicubic; }

    @media only screen and (max-width:600px) {
      .email-container { width:100% !important; }
      .mobile-px { padding-left:20px !important; padding-right:20px !important; }
      .stack-col {
        display:block !important;
        width:100% !important;
        max-width:100% !important;
        box-sizing:border-box;
        padding:0 0 10px !important;
      }
      .hero-heading { font-size:19px !important; line-height:27px !important; }
      .cta-btn {
        display:block !important;
        width:100% !important;
        box-sizing:border-box;
        text-align:center !important;
      }
      .item-thumb { width:56px !important; height:56px !important; }
    }
  </style>
</head>

<body style="margin:0;padding:0;background:#f0f2ee;font-family:Arial,sans-serif;">
  @php
    $trackUrl = env('ORDER_TRACK_URL', 'https://dhirago.com/return/track-order');
    $deliveryDate = $order->expected_delivery_date;
    if ($deliveryDate && !($deliveryDate instanceof \Carbon\CarbonInterface)) {
        try {
            $deliveryDate = \Carbon\Carbon::parse($deliveryDate);
        } catch (\Throwable $e) {
            $deliveryDate = null;
        }
    }
  @endphp

  <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
    style="background:#f0f2ee;padding:40px 0;">
    <tr>
      <td align="center">
        <table class="email-container" width="600" cellpadding="0" cellspacing="0" role="presentation"
          style="width:600px;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;">

          <!-- Banner -->
          <tr>
            <td style="padding:0;">
              <img
                src="https://dhirago-images-761186487122-eu-north-1-an.s3.eu-north-1.amazonaws.com/ecommerce/logo/unnamed.jpg"
                alt="DHIRAGO" width="600"
                style="display:block;width:100%;max-width:600px;height:auto;border:0;">
            </td>
          </tr>

          <!-- Shipment header -->
          <tr>
            <td class="mobile-px" style="background:#ffffff;padding:36px 40px 40px;border-bottom:1px solid #f0f0f0;">
              <img
                src="https://dhirago-images-761186487122-eu-north-1-an.s3.eu-north-1.amazonaws.com/ecommerce/logo/3.svg"
                alt="DHIRAGO" width="90" style="display:block;border:0;">

              <h2 class="hero-heading" style="color:#111;margin:12px 0;font-size:22px;line-height:34px;font-weight:500;">
                Your order is on the way!<br>
                It has been <span style="color:#3bb54a;">shipped.</span>
              </h2>

              <p style="color:#666;font-size:15px;line-height:22px;margin:0 0 28px;">
                Hi {{ $order->first_name ?: 'Customer' }}, great news! Your order
                <strong>#{{ $order->order_number }}</strong> has left our facility
                @if($order->courier_name)
                  with <strong>{{ $order->courier_name }}</strong>
                @endif
                and is making its way to you.
              </p>

              <!-- Shipment cards -->
              <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                  <td class="stack-col" width="33%" style="padding:0 6px;">
                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                      style="background:#e9f7ec;border-radius:10px;">
                      <tr>
                        <td style="padding:18px 8px;text-align:center;">
                          <div style="font-size:20px;line-height:1;margin-bottom:8px;">&#128230;</div>
                          <div style="font-size:13px;font-weight:bold;color:#0b1f17;">
                            Order #{{ $order->order_number }}
                          </div>
                        </td>
                      </tr>
                    </table>
                  </td>
                  <td class="stack-col" width="33%" style="padding:0 6px;">
                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                      style="background:#e9f7ec;border-radius:10px;">
                      <tr>
                        <td style="padding:18px 8px;text-align:center;">
                          <div style="font-size:20px;line-height:1;margin-bottom:8px;">&#128666;</div>
                          <div style="font-size:13px;font-weight:bold;color:#0b1f17;">
                            {{ $order->courier_name ?: 'In transit' }}
                          </div>
                        </td>
                      </tr>
                    </table>
                  </td>
                  <td class="stack-col" width="33%" style="padding:0 6px;">
                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                      style="background:#e9f7ec;border-radius:10px;">
                      <tr>
                        <td style="padding:18px 8px;text-align:center;">
                          <div style="font-size:20px;line-height:1;margin-bottom:8px;">&#128205;</div>
                          <div style="font-size:12px;font-weight:bold;color:#0b1f17;word-break:break-all;">
                            AWB: {{ $order->awb_code ?: 'Updating soon' }}
                          </div>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              <div style="margin:28px 0 8px;">
                <a href="{{ $trackUrl }}" class="cta-btn"
                  style="background:#0b1f17;color:#fff;text-decoration:none;padding:14px 34px;
                  border-radius:0;font-size:15px;font-weight:bold;display:inline-block;">
                  Track Your Order
                </a>
              </div>

              @if($deliveryDate)
                <p style="margin:16px 0 0;color:#666;font-size:14px;">
                  Expected delivery:
                  <strong style="color:#111;">{{ $deliveryDate->format('d M Y') }}</strong>
                </p>
              @endif
            </td>
          </tr>

          <!-- Order summary -->
          <tr>
            <td class="mobile-px" style="padding:36px 40px 0;">
              <h2 style="margin:0 0 4px;color:#111;font-size:22px;">Order summary</h2>
              <p style="margin:0 0 20px;color:#3bb54a;font-size:14px;font-weight:bold;">
                #{{ $order->order_number }}
              </p>

              @if($order->items && $order->items->count())
                @foreach($order->items as $item)
                  <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                    style="margin-bottom:16px;">
                    <tr>
                      <td width="72" style="vertical-align:top;">
                        @if(!empty($item->image))
                          <img class="item-thumb"
                            src="{{ media_url(preg_replace('/\?.*$/', '', (string) $item->image)) }}"
                            alt="{{ $item->name }}" width="64" height="64"
                            style="display:block;width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid #eee;">
                        @endif
                      </td>
                      <td style="vertical-align:top;padding-left:14px;">
                        <div style="color:#111;font-size:13px;font-weight:bold;">
                          {{ $item->name ?: ($item->product->title ?? 'Product') }}
                          &times; {{ $item->quantity }}
                        </div>
                        @if(!empty($item->size) || !empty($item->color))
                          <div style="color:#888;font-size:13px;margin-top:4px;">
                            @if(!empty($item->size)) Size: {{ strtoupper($item->size) }} @endif
                            @if(!empty($item->size) && !empty($item->color)) &nbsp;&middot;&nbsp; @endif
                            @if(!empty($item->color)) Color: {{ ucfirst($item->color) }} @endif
                          </div>
                        @endif
                      </td>
                      <td style="vertical-align:top;text-align:right;color:#111;font-size:15px;font-weight:bold;white-space:nowrap;">
                        &#8377;{{ number_format($item->price, 2) }}
                      </td>
                    </tr>
                  </table>
                @endforeach
              @endif

              <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="background:#fafafa;border:1px solid #eee;border-radius:12px;margin-top:8px;">
                <tr>
                  <td style="padding:22px;">
                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                      <tr>
                        <td style="padding:6px 0;color:#555;font-size:15px;">Shipment status</td>
                        <td style="padding:6px 0;color:#3bb54a;font-size:15px;text-align:right;font-weight:bold;">
                          Shipped
                        </td>
                      </tr>
                      @if($order->awb_code)
                        <tr>
                          <td style="padding:6px 0;color:#555;font-size:15px;">Tracking / AWB</td>
                          <td style="padding:6px 0;color:#111;font-size:14px;text-align:right;font-weight:bold;">
                            {{ $order->awb_code }}
                          </td>
                        </tr>
                      @endif
                      <tr>
                        <td style="padding:14px 0 0;border-top:1px solid #eee;color:#111;font-size:16px;font-weight:bold;">
                          Total Amount
                        </td>
                        <td style="padding:14px 0 0;border-top:1px solid #eee;color:#111;font-size:16px;text-align:right;font-weight:bold;">
                          &#8377;{{ number_format($order->total_amount, 2) }}
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Shipping address -->
          <tr>
            <td class="mobile-px" style="padding:28px 40px 0;">
              <h3 style="color:#111;margin:0 0 10px;font-size:17px;">Shipping Address</h3>
              <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="background:#fafafa;border:1px solid #eee;border-radius:12px;">
                <tr>
                  <td style="padding:20px;color:#555;font-size:15px;line-height:24px;">
                    <strong style="color:#111;">
                      {{ trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? '')) }}
                    </strong><br>
                    {{ $order->address1 }} {{ $order->address2 }}<br>
                    {{ $order->city }}, {{ $order->state }}<br>
                    {{ $order->post_code }}<br>
                    Phone: {{ $order->phone }}
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Help box -->
          <tr>
            <td class="mobile-px" style="padding:32px 40px 40px;">
              <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="background:#e9f7ec;border-radius:12px;">
                <tr>
                  <td style="padding:26px;text-align:center;">
                    <h3 style="margin:0 0 8px;color:#111;font-size:18px;">Any queries or concerns?</h3>
                    <p style="margin:0;color:#555;font-size:14px;line-height:22px;">
                      We'll notify you again when your order is out for delivery.<br>
                      <a href="mailto:contact@dhirago.com"
                        style="color:#3bb54a;text-decoration:none;font-weight:bold;">
                        contact@dhirago.com
                      </a>
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td>
              <img
                src="https://dhirago-images-761186487122-eu-north-1-an.s3.eu-north-1.amazonaws.com/ecommerce/logo/unnamed.jpg"
                alt="DHIRAGO" width="600"
                style="display:block;width:100%;max-width:600px;height:auto;border:0;">
              <p style="font-size:13px;color:#8fa197;margin:0;text-align:center;padding:12px 20px;">
                &copy; {{ date('Y') }} DHIRAGO. All rights reserved.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
