<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dex PMS Notification')</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 24px;
            color: #1e293b;
            -webkit-font-smoothing: antialiased;
        }
        .email-wrapper {
            width: 100%;
            background-color: #f4f6f9;
            padding: 24px 0;
        }
        .container {
            max-width: 580px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            padding: 32px 28px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
        }
        .brand-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .logo-title {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            letter-spacing: -0.02em;
        }
        .badge-system {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #2C5F7C;
            background-color: #e0f2fe;
            padding: 4px 12px;
            border-radius: 9999px;
            margin-top: 6px;
        }
        .divider {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 20px 0;
        }
        .lead-greeting {
            font-size: 15px;
            line-height: 1.6;
            color: #334155;
            margin-bottom: 16px;
        }
        .lead-message {
            font-size: 14px;
            line-height: 1.6;
            color: #475569;
            margin-bottom: 20px;
        }
        .details-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
        }
        .details-table td {
            padding: 7px 6px;
            font-size: 13px;
            vertical-align: top;
        }
        .details-label {
            font-weight: 600;
            color: #64748b;
            width: 38%;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.04em;
        }
        .details-value {
            color: #0f172a;
            font-weight: 600;
        }
        .pill-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .pill-blue {
            background-color: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }
        .pill-green {
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        .pill-amber {
            background-color: #fffbeb;
            color: #d97706;
            border: 1px solid #fde68a;
        }
        .pill-red {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .pill-purple {
            background-color: #faf5ff;
            color: #7c3aed;
            border: 1px solid #ddd6fe;
        }
        .alert-banner {
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.5;
        }
        .alert-info {
            background-color: #eff6ff;
            border-left: 4px solid #2563eb;
            color: #1e40af;
        }
        .alert-warning {
            background-color: #fffbeb;
            border-left: 4px solid #d97706;
            color: #92400e;
        }
        .alert-danger {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        .alert-success {
            background-color: #ecfdf5;
            border-left: 4px solid #059669;
            color: #065f46;
        }
        .cta-container {
            text-align: center;
            margin: 28px 0 20px 0;
        }
        .btn-primary {
            display: inline-block;
            background-color: #2C5F7C;
            color: #ffffff !important;
            text-decoration: none;
            padding: 13px 32px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.02em;
            box-shadow: 0 2px 4px rgba(44, 95, 124, 0.25);
        }
        .btn-green {
            background-color: #059669 !important;
            box-shadow: 0 2px 4px rgba(5, 150, 105, 0.25) !important;
        }
        .btn-amber {
            background-color: #d97706 !important;
            box-shadow: 0 2px 4px rgba(217, 119, 6, 0.25) !important;
        }
        .link-subtext {
            font-size: 11.5px;
            color: #64748b;
            margin-top: 10px;
            word-break: break-all;
            line-height: 1.4;
        }
        .session-notice-box,
        .confidentiality-notice-box {
            margin-top: 18px;
            background-color: #f8fafc;
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 11.5px;
            color: #64748b;
            text-align: left;
            line-height: 1.5;
            border-left: 3px solid #64748b;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            margin-top: 24px;
            border-top: 1px solid #f1f5f9;
            padding-top: 18px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="container">
            <div class="brand-header">
                <h1 class="logo-title">Dex International Co.</h1>
                <span class="badge-system">Project Management System</span>
            </div>

            @yield('content')

            <div class="footer">
                <div>&copy; {{ date('Y') }} Dex International Co. &bull; All Rights Reserved.</div>
                <div style="margin-top: 4px; font-size: 11px; color: #cbd5e1;">This is an automated notification from Dex PMS. Please do not reply directly to this email.</div>
            </div>
        </div>
    </div>
</body>
</html>

