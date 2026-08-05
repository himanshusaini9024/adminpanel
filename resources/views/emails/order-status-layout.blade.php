<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $subject ?? 'Order Update' }}</title>
</head>
<body style="margin:0;padding:0;background:#f0f2ee;font-family:Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2ee;padding:40px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;max-width:600px;width:100%;">
          <tr>
            <td style="background:#0b1f17;padding:28px 40px;text-align:center;">
              <h1 style="color:#fff;margin:0;font-size:20px;letter-spacing:2px;">DHIRAGO</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:36px 40px;color:#1a1a1a;font-size:15px;line-height:1.6;">
              @yield('content')
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
