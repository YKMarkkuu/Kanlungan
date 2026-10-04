<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Your Kanlungan verification code</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f3ee;">
<div style="max-width: 480px; margin: 0 auto; padding: 24px 16px; font-family: Segoe UI, Tahoma, Arial, sans-serif; font-size: 16px; line-height: 1.5; color: #1e1e1e;">

<p style="margin: 0 0 16px 0; font-size: 18px; font-weight: bold;">Kanlungan</p>

<p style="margin: 0 0 16px 0;">Your verification code is:</p>

<div style="margin: 0 0 16px 0; padding: 16px; background-color: #ffffff; border: 2px solid #1a6b4a; border-radius: 8px; text-align: center; font-family: Consolas, Menlo, Courier New, monospace; font-size: 32px; font-weight: bold; letter-spacing: 6px; color: #1e1e1e;">
{{ $plainCode }}
</div>

<p style="margin: 0 0 16px 0;">This code expires in {{ \App\Models\OtpCode::TTL_MINUTES }} minutes.</p>

<p style="margin: 0 0 16px 0; color: #6a6a6a; font-size: 14px;">If you didn't request this, ignore this email.</p>

</div>
</body>
</html>
