<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dex PMS Security Verification</title>
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
            color: #2563eb;
            background-color: #eff6ff;
            padding: 4px 10px;
            border-radius: 9999px;
            margin-top: 6px;
        }
        .otp-box {
            text-align: center;
            margin: 28px 0;
            background-color: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 20px;
        }
        .otp-code {
            font-size: 36px;
            font-weight: 800;
            letter-spacing: 8px;
            color: #1e3a8a;
            margin: 0;
            font-family: monospace;
        }
        .otp-expiry {
            font-size: 12px;
            color: #64748b;
            margin-top: 8px;
            margin-bottom: 0;
        }
        .details-box {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            font-size: 13px;
        }
        .details-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            color: #475569;
        }
        .details-row:last-child {
            margin-bottom: 0;
        }
        .details-label {
            font-weight: 600;
            color: #334155;
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
            <span class="badge">Security Verification</span>
        </div>

        <p>Hello <strong>{{ $user->first_name ?? $user->name }}</strong>,</p>

        <p>To complete your process we need to verify you, Here is your 6-digit one-time password (OTP) for your DEX PMS Account that we will use to verify if it is you. Please do not share this OTP with others. This will expire within 3-minutes:</p>

        <div class="otp-box">
            <p class="otp-code">{{ $otp }}</p>
            <p class="otp-expiry">Valid for 3 minutes only.</p>
        </div>

        <div class="details-box">
            <div class="details-row">
                <span class="details-label">Browser & System:</span>
                <span>{{ $deviceInfo['browser'] ?? 'Web Browser' }} ({{ $deviceInfo['platform'] ?? 'OS' }})</span>
            </div>
            <div class="details-row">
                <span class="details-label">Device Type:</span>
                <span>{{ $deviceInfo['device_type'] ?? 'Computer' }}</span>
            </div>
            <div class="details-row">
                <span class="details-label">IP Address:</span>
                <span>{{ $deviceInfo['ip_address'] ?? 'Unknown' }}</span>
            </div>
            <div class="details-row">
                <span class="details-label">Estimated Location:</span>
                <span>{{ $deviceInfo['location'] ?? 'Unknown Location' }}</span>
            </div>
        </div>

        <p style="font-size: 13px; color: #64748b;">If you did not attempt to sign in, someone else may be trying to access your account. Please change your password or notify your system administrator immediately.</p>

        <div class="footer">
            &copy; {{ date('Y') }} Dex International Co. &bull; Project Management System
        </div>
    </div>
</body>
</html>

