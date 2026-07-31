<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>{{ $subject ?? 'Message from Dhirago' }}</title>
</head>

<body style="margin:0;padding:0;background:#f0f2ee;font-family:Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2ee;padding:40px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;">
          <tr>
            <td style="padding:0;">
              <img src="https://dhirago-images-761186487122-eu-north-1-an.s3.eu-north-1.amazonaws.com/ecommerce/logo/unnamed.jpg" alt="DHIRAGO"
                width="600" style="display:block;width:100%;max-width:600px;height:auto;border:0;">
            </td>
          </tr>
          <tr>
            <td style="padding:36px 40px;color:#1a1a1a;font-size:15px;line-height:1.6;">
              <!-- <p style="margin:0 0 16px;">Hi {{ $name }},</p> -->
              <div style="white-space:pre-wrap;">{{ $body }}</div>
              <p style="margin:28px 0 0;color:#666;font-size:13px;">Thank you for shopping with us.</p>
            </td>
          </tr>
          <tr>
            <td style="background:#f8faf8;padding:18px 40px;text-align:center;color:#888;font-size:12px;">
              &copy; {{ date('Y') }} {{ config('app.name', 'Dhirago') }}
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>

</html>