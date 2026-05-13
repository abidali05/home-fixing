<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - OTP</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 0 10px rgba(0,0,0,0.05);">
                    <tr>
                        <td style="background-color: #2d89ef; padding: 20px; color: white; text-align: center;">
                            <h2 style="margin: 0; font-size: 24px;">Password Reset OTP</h2>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px; color: #333;">
                            <p style="font-size: 16px; margin-bottom: 20px;">Hi {{ $user->name }},</p>

                            <p style="font-size: 15px; margin-bottom: 20px;">
                                We received a request to reset your password. Use the OTP below to proceed:
                            </p>

                            <div style="text-align: center; margin: 30px 0;">
                                <p style="font-size: 28px; font-weight: bold; color: #2d89ef;">{{ $user->otp }}</p>
                            </div>

                            <p style="font-size: 14px; color: #777; margin-top: 30px;">
                                This OTP is valid for the next 5 minutes. If you didn’t request a password reset, please ignore this email.
                            </p>

                            <p style="font-size: 14px; color: #777; margin-top: 20px;">Thanks,<br>The {{ config('app.name') }} Team</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f4f4f4; padding: 20px; text-align: center; font-size: 12px; color: #999;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
