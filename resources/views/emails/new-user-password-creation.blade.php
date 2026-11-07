<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Dex International Co.</title>
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
        .welcome-lead {
            font-size: 15px;
            line-height: 1.6;
            color: #334155;
            margin-bottom: 20px;
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
        .cta-container {
            text-align: center;
            margin: 28px 0;
        }
        .btn-create {
            display: inline-block;
            background-color: #2C5F7C;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.02em;
        }
        .link-subtext {
            font-size: 12px;
            color: #64748b;
            margin-top: 10px;
            word-break: break-all;
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
            <span class="badge">Project Management System</span>
        </div>

        <p class="welcome-lead">
            Hi, <strong>{{ $user->first_name ?? $user->name }}</strong>! Thank you for being part with DEX International Co. we are excited to work with you! At the bottom is your password creation link, do not share it with anyone.
        </p>

        <div class="account-box">
            <div class="account-row">
                <span class="account-label">Employee ID:</span>
                <span>{{ $user->employee_number ?? 'N/A' }}</span>
            </div>
            <div class="account-row">
                <span class="account-label">Account Number:</span>
                <span>{{ $user->account_number ?? 'N/A' }}</span>
            </div>
            <div class="account-row">
                <span class="account-label">Registered Email:</span>
                <span>{{ $user->email }}</span>
            </div>
            <div class="account-row">
                <span class="account-label">Job Title:</span>
                <span>{{ $user->job_title ?? 'Employee' }}</span>
            </div>
        </div>

        <div class="requirements-box">
            <div class="requirements-title">Password Format Requirements</div>
            <ul class="requirements-list">
                <li>At least <strong>8 characters</strong> long</li>
                <li>At least <strong>1 capital letter</strong> (A&ndash;Z)</li>
                <li>At least <strong>1 number</strong> (0&ndash;9)</li>
                <li>Mix of <strong>symbols</strong> (!@#$%^&*...)</li>
                <li>Cannot contain your <strong>name</strong> or <strong>birthdate</strong></li>
            </ul>
        </div>

        <div class="cta-container">
            <a href="{{ $creationUrl }}" class="btn-create" target="_blank" rel="noopener">Create My Password</a>
            <p class="link-subtext">
                Or copy and paste this secure link into your browser:<br>
                <a href="{{ $creationUrl }}" style="color: #2C5F7C;">{{ $creationUrl }}</a>
            </p>
        </div>

        <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; border-radius: 4px; padding: 12px 16px; margin-bottom: 20px; font-size: 12.5px; color: #991b1b; line-height: 1.5;">
            <strong>Security Notice:</strong> This password creation link will automatically expire after <strong>1 hour (60 minutes)</strong>. Please set your password before it expires. Once used, it cannot be reused.
        </div>

        <p style="font-size: 12px; color: #64748b; line-height: 1.5;">
            <strong>Note:</strong> This link is secure and unique to your account. Once your password is set, you will be able to log in using either your email address or your 7-digit Account Number ({{ $user->account_number }}).
        </p>

        <div class="footer">
            &copy; {{ date('Y') }} Dex International Co. &bull; Project Management System
        </div>
    </div>
</body>
</html>

