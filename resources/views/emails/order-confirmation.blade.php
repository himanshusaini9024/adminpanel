  <!DOCTYPE html>
  <html>

  <head>
    <meta charset="UTF-8">
    <title>Order Confirmation</title>
  </head>

  <body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:40px 0;">
      <tr>
        <td align="center">

          <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;">

            <!-- Header -->
            <tr>
              <td style="background:#000;padding:20px;text-align:center;">
                <h1 style="color:#fff;margin:0;font-size:20px;">
                  DHIRAGO
                </h1>
              </td>
            </tr>

            <!-- Content -->
            <tr>
              <td style="padding:40px;">

                <h2 style="margin-top:0;color:#111;">
                  Order Confirmed 🎉
                </h2>

                <p style="font-size:16px;color:#555;line-height:26px;">
                  Hi {{ $order->first_name }},
                </p>

                <p style="font-size:16px;color:#555;line-height:26px;">
                  Thank you for your order. Your order has been placed successfully.
                </p>

                <!-- Order Box -->
                <table width="100%" cellpadding="0" cellspacing="0"
                  style="margin:30px 0;background:#fafafa;border:1px solid #eee;border-radius:10px;">

                  <tr>
                    <td style="padding:20px;">

                      <p style="margin:0 0 10px;">
                        <strong>Order Number:</strong>
                        #{{ $order->order_number }}
                      </p>

                      <p style="margin:0 0 10px;">
                        <strong>Payment Method:</strong>
                        {{ ucfirst($order->payment_method) }}
                      </p>

                      <p style="margin:0 0 10px;">
                        <strong>Payment Status:</strong>
                        {{ ucfirst($order->payment_status) }}
                      </p>

                      <p style="margin:0;">
                        <strong>Total Amount:</strong>
                        ₹{{ number_format($order->total_amount, 2) }}
                      </p>

                    </td>
                  </tr>

                </table>

                <!-- Shipping Address -->
                <h3 style="color:#111;">
                  Shipping Address
                </h3>

                <p style="font-size:15px;color:#666;line-height:24px;">
                  {{ $order->first_name }}<br>
                  {{ $order->address1 }} {{ $order->address2 }}<br>
                  {{ $order->city }}, {{ $order->state }}<br>
                  {{ $order->post_code }}<br>
                  Phone: {{ $order->phone }}
                </p>

                <!-- Button -->
                <div style="text-align:center;margin:40px 0;">
                  <a href="https://dhirago.com/return/track-order"
                    style="background:#000;color:#fff;text-decoration:none;
            padding:14px 30px;border-radius:6px;font-size:16px;
            display:inline-block;">
                    Track Your Order
                  </a>
                </div>

                <p style="font-size:15px;color:#666;line-height:24px;">
                  We’ll notify you once your order is shipped.
                </p>

                <p style="font-size:15px;color:#666;">
                  Thank you for shopping with us ❤️
                </p>

              </td>
            </tr>

            <!-- Footer -->
            <tr>
              <td style="background:#fafafa;padding:20px;text-align:center;
            font-size:13px;color:#999;">
                © ' . date('Y') . ' DHIRAGO. All rights reserved.
              </td>
            </tr>

          </table>

        </td>
      </tr>
    </table>

  </body>

  </html>