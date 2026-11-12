@extends('emails.layouts.base')

@section('title', 'Security Alert: Password Changed - Dex PMS')

@section('content')
    <div style="text-align: center; margin-bottom: 16px;">
        <span class="pill-badge pill-amber">Security Alert</span>
    </div>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; text-align: center; margin: 0 0 16px 0;">
        Password Successfully Changed
    </h2>

    <p class="lead-greeting">
        Hi, <strong>{{ $recipient->first_name ?? $recipient->name }}</strong>!
    </p>

    <p class="lead-message">
        This is an official security confirmation that the password for your Dex PMS account was recently changed. If you made this modification, no further action is required and you may safely disregard this notification.
    </p>

    <div class="alert-banner alert-warning">
        <strong style="display: block; margin-bottom: 6px;">Did you not make this change?</strong>
        If you did not recognize or authorize this change, please <strong>contact your system administrator immediately</strong> to protect your credentials and prevent unauthorized access to your account.
    </div>

    <div class="details-box">
        <table class="details-table">
            <tr>
                <td class="details-label">Account User:</td>
                <td class="details-value">{{ $recipient->name }}</td>
            </tr>
            <tr>
                <td class="details-label">Email Address:</td>
                <td class="details-value">{{ $recipient->email }}</td>
            </tr>
            @if($recipient->employee_number)
            <tr>
                <td class="details-label">Employee ID:</td>
                <td class="details-value font-mono">{{ $recipient->employee_number }}</td>
            </tr>
            @endif
            <tr>
                <td class="details-label">Date & Time:</td>
                <td class="details-value">{{ $changedAt }}</td>
            </tr>
            @if($ipAddress)
            <tr>
                <td class="details-label">Request IP:</td>
                <td class="details-value font-mono">{{ $ipAddress }}</td>
            </tr>
            @endif
            <tr>
                <td class="details-label">Status:</td>
                <td class="details-value" style="color: #059669; font-weight: 700;">Password Updated</td>
            </tr>
        </table>
    </div>

    <div class="cta-container">
        <a href="{{ $actionUrl }}" class="btn-primary" target="_blank" rel="noopener">
            Review Account Settings
        </a>
        <p class="link-subtext">
            Or copy and paste this direct link into your browser:<br>
            <a href="{{ $actionUrl }}" style="color: #2C5F7C;">{{ $actionUrl }}</a>
        </p>

        <div class="confidentiality-notice-box">
            <strong>Confidentiality Notice:</strong> This email and any associated details are strictly confidential, proprietary, and intended solely for the designated recipient within Dex International Co. Dissemination, distribution, copying, or sharing of this sensitive information without prior authorization is strictly prohibited.
        </div>
    </div>
@endsection

