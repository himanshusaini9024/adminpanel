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
              <img src="https://images.dhirago.com/ecommerce/email/email.webp" alt="DHIRAGO"
                width="600" style="display:block;width:100%;max-width:600px;height:auto;border:0;">
            </td>
          </tr>
          <tr>
            <td style="padding:36px 40px;color:#1a1a1a;font-size:15px;line-height:1.6;">
              <!-- <p style="margin:0 0 16px;">Hi {{ $name }},</p> -->
              <div style="white-space:pre-wrap;">{{ $body }}</div>
                <table cellpadding="0" cellspacing="0" style="margin:28px 0 0;">
                <tr>
                  <td align="center" style="border-radius:8px;background:#1a1a1a;">
                    <a href="https://dhirago.com/"
                      style="display:inline-block;padding:14px 32px;color:#ffffff;font-size:14px;font-weight:bold;text-decoration:none;border-radius:8px;">
                      SHOP NOW
                    </a>
                  </td>
                </tr>
              </table>  
              <p style="margin:28px 0 0;color:#666;font-size:13px;">Thanks for connecting with us.</p>
            </td>
          </tr>
          <tr>
            <td style="background:#f8faf8;padding:18px 40px;text-align:center;color:#888;font-size:12px;">
              &copy; {{ date('Y') }} {{ config('Dhirago', 'Dhirago') }}
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>

</html>