<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your New Password</title>
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
                                New Login Password
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px 30px;">
                            <h2 style="margin:0 0 14px;color:#0f172a;font-size:22px;">
                                Hello {{ $user->name }},
                            </h2>

                            <p style="margin:0 0 18px;color:#334155;font-size:15px;line-height:24px;">
                                Your password has been reset successfully. Use the new password below to login.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;margin:22px 0;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <p style="margin:0 0 10px;color:#475569;font-size:14px;">
                                            <strong>Email:</strong>
                                            <span style="color:#0f172a;">{{ $user->email }}</span>
                                        </p>

                                        <p style="margin:0;color:#475569;font-size:14px;">
                                            <strong>New Password:</strong>
                                            <span style="color:#0f172a;font-weight:bold;">{{ $newPassword }}</span>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 18px;color:#334155;font-size:15px;line-height:24px;">
                                Please login and change your password after you access your account.
                            </p>

                            <div style="text-align:center;margin:28px 0;">
                                <a href="{{ $loginUrl }}" style="display:inline-block;background:#172554;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:12px;font-weight:bold;font-size:15px;">
                                    Login to E-Waste
                                </a>
                            </div>
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
