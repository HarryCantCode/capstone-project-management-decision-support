<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dex PMS - Temporary Password</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 24px;
            color: #1e293b;
        }
        .container {
            max-width: 520px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
        }
        .header {
            text-align: center;
            margin-bottom: 24px;
        }
        .logo-title {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }
        .badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #059669;
            background-color: #ecfdf5;
            padding: 4px 10px;
            border-radius: 9999px;
            margin-top: 6px;
        }
        .password-box {
            text-align: center;
            margin: 28px 0;
            background-color: #f8fafc;
            border: 2px dashed #0284c7;
            border-radius: 8px;
            padding: 20px;
        }
        .password-code {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 4px;
            color: #0f172a;
            margin: 0;
            font-family: monospace;
            user-select: all;
        }
        .instructions {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            border-radius: 4px;
            padding: 14px;
            margin-bottom: 24px;
            font-size: 13px;
            color: #1e40af;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            margin-top: 24px;
            border-top: 1px solid #f1f5f9;
            padding-top: 16px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="logo-title">Dex International Co.</h1>
            <span class="badge">Password Reset Request</span>
        </div>

        <p>Hello <strong>{{ $user->first_name ?? $user->name }}</strong>,</p>

        <p>You recently requested a new password for your Dex PMS account. A randomized temporary password has been generated for you below:</p>

        <div class="password-box">
            <p class="password-code">{{ $temporaryPassword }}</p>
        </div>

        <div class="instructions">
            <strong>Security Notice:</strong> When you sign in with this temporary password, the system will immediately prompt you to set your new permanent personal password before accessing your account.
        </div>

        <p style="font-size: 13px; color: #64748b;">If you did not request this password reset, please contact your administrator right away.</p>

        <div class="footer">
            &copy; {{ date('Y') }} Dex International Co. &bull; Project Management System
        </div>
    </div>
</body>
</html>

