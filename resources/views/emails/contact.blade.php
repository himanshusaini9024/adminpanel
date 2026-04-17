<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New Contact Message</title>
</head>
<body style="margin:0; padding:0; background:#f4f4f4; font-family:Arial, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="padding:20px;">
        <tr>
            <td align="center">
                
                <!-- Card -->
                <table width="600" cellpadding="0" cellspacing="0" 
                       style="background:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 5px 15px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td style="background:#000; color:#fff; padding:20px; text-align:center;">
                            <h2 style="margin:0;">📩 New Contact Message</h2>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:25px; color:#333;">
                            
                            <p><strong>Name:</strong> {{ $data['name'] }}</p>
                            <p><strong>Email:</strong> {{ $data['email'] }}</p>
                            <p><strong>Subject:</strong> {{ $data['subject'] ?? 'N/A' }}</p>

                            <hr style="margin:20px 0;">

                            <p><strong>Message:</strong></p>
                            <p style="background:#f9f9f9; padding:15px; border-radius:5px;">
                                {{ $data['message'] }}
                            </p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#f1f1f1; padding:15px; text-align:center; font-size:12px; color:#777;">
                            This message was sent from your website contact form.
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>