<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Reset OTP</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:30px 12px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #e2e8f0;">
                    <tr>
                        <td style="background:#172554;padding:28px 30px;text-align:center;">
                            <h1 style="margin:0;color:#ffffff;font-size:26px;">E-Waste Platform</h1>
                            <p style="margin:8px 0 0;color:#bfdbfe;font-size:14px;">
                                Password Reset Verification
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px 30px;">
                            <h2 style="margin:0 0 14px;color:#0f172a;font-size:22px;">
                                Hello {{ $user->name }},
                            </h2>

                            <p style="margin:0 0 18px;color:#334155;font-size:15px;line-height:24px;">
                                Use the OTP below to reset your E-Waste account password.
                            </p>

                            <div style="text-align:center;margin:28px 0;">
                                <div style="display:inline-block;background:#eff6ff;color:#172554;border:2px dashed #172554;border-radius:14px;padding:18px 30px;font-size:34px;font-weight:bold;letter-spacing:8px;">
                                    {{ $otp }}
                                </div>
                            </div>

                            <p style="margin:0;color:#334155;font-size:15px;line-height:24px;">
                                This OTP will expire in <strong>10 minutes</strong>. If you did not request this, please ignore this email.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#f8fafc;padding:18px 30px;text-align:center;border-top:1px solid #e2e8f0;">
                            <p style="margin:0;color:#64748b;font-size:12px;">
                                © {{ date('Y') }} E-Waste Platform. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
