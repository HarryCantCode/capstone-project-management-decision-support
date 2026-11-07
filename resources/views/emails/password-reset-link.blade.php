<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dex International Co. - Password Reset Request</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 24px;
            color: #1e293b;
        }
        .container {
            max-width: 540px;
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
        .message-body {
            font-size: 14px;
            line-height: 1.6;
            color: #334155;
            margin-bottom: 20px;
        }
        .cta-container {
            text-align: center;
            margin: 28px 0;
        }
        .btn-create {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff !important;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }
        .account-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .account-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            color: #475569;
        }
        .account-row:last-child {
            margin-bottom: 0;
        }
        .account-label {
            font-weight: 600;
            color: #334155;
        }
        .requirements-box {
            background-color: #f8fafc;
            border-left: 4px solid #2563eb;
            border-radius: 4px;
            padding: 16px;
            margin-bottom: 24px;
            font-size: 13px;
        }
        .requirements-title {
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 11px;
            color: #1e3a8a;
            margin-bottom: 8px;
        }
        .requirements-list {
            margin: 0;
            padding-left: 18px;
            color: #334155;
            line-height: 1.6;
        }
        .security-notice {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            border-radius: 4px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 12.5px;
            color: #991b1b;
            line-height: 1.5;
        }
        .fallback-link {
            font-size: 12px;
            color: #64748b;
            word-break: break-all;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px dashed #e2e8f0;
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
            <span class="badge">Password Reset</span>
        </div>

        <p class="message-body" style="font-size: 15px;">
            Hi, <strong>{{ $user->first_name ?: $user->name }}</strong>,
        </p>

        <p class="message-body">
            We've received a request to reset your password. Please use the link below to create a new password. If you did not request this change, contact your administrator or supervisor immediately. Otherwise, you may proceed using the link.
        </p>

        {{-- Direct CTA Button --}}
        <div class="cta-container">
            <a href="{{ $resetUrl }}" class="btn-create" target="_blank" rel="noopener noreferrer">
                Create New Password
            </a>
        </div>

        {{-- Account Reference Details --}}
        <div class="account-box">
            <div class="account-row">
                <span class="account-label">User Account:</span>
                <span>{{ $user->name }}</span>
            </div>
            <div class="account-row">
                <span class="account-label">Email:</span>
                <span>{{ $user->email }}</span>
            </div>
            @if ($user->account_number)
                <div class="account-row">
                    <span class="account-label">Account No.:</span>
                    <span style="font-family: monospace;">{{ $user->account_number }}</span>
                </div>
            @endif
            @if ($user->employee_number)
                <div class="account-row">
                    <span class="account-label">Employee ID:</span>
                    <span style="font-family: monospace;">{{ $user->employee_number }}</span>
                </div>
            @endif
        </div>

        {{-- Password Complexity Requirements --}}
        <div class="requirements-box">
            <div class="requirements-title">Password Security Requirements</div>
            <ul class="requirements-list">
                <li>At least <strong>8 characters</strong> in length</li>
                <li>At least <strong>one uppercase</strong> letter (A-Z) and <strong>one lowercase</strong> letter (a-z)</li>
                <li>At least <strong>one numeric digit</strong> (0-9)</li>
                <li>At least <strong>one special symbol</strong> (e.g., ! @ # $ % & * ?)</li>
                <li>Must not contain your first or last name</li>
            </ul>
        </div>

        {{-- Security Notice & Expiry --}}
        <div class="security-notice">
            <strong>Security Notice:</strong> This password creation link is single-use and will automatically expire after <strong>1 hour (60 minutes)</strong>. Once used or expired, it cannot be reused.
        </div>

        <div class="fallback-link">
            If the button above does not work, copy and paste the following URL into your browser:<br>
            <a href="{{ $resetUrl }}" style="color: #2563eb;">{{ $resetUrl }}</a>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} Dex International Co. &bull; Project Management System &bull; All rights reserved.
        </div>
    </div>
</body>
</html>

