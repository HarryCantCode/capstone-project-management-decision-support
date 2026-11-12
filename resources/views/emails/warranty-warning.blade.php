@extends('emails.layouts.base')

@section('title', ($daysRemaining === 1 ? 'Warranty Expiring Tomorrow' : "Warranty Expiring in {$daysRemaining} Days") . ' - Dex PMS')

@section('content')
    <div style="text-align: center; margin-bottom: 16px;">
        @if($daysRemaining === 1)
            <span class="pill-badge pill-red">Warranty Expiring Tomorrow</span>
        @else
            <span class="pill-badge pill-amber">Warranty Expiring in {{ $daysRemaining }} Days</span>
        @endif
    </div>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; text-align: center; margin: 0 0 16px 0;">
        Warranty Alert: {{ $project->name }}
    </h2>

    @if($daysRemaining === 1)
        <div class="alert-banner alert-danger">
            <strong>Critical Reminder:</strong> The warranty coverage for project <strong>{{ $project->name }}</strong> ({{ $project->project_code ?? 'N/A' }}) expires <strong>tomorrow ({{ $warrantyEnd }})</strong>. Please ensure any final client inspections or punch-list items are settled.
        </div>
    @else
        <div class="alert-banner alert-warning">
            <strong>Warranty Notice:</strong> The warranty coverage for project <strong>{{ $project->name }}</strong> ({{ $project->project_code ?? 'N/A' }}) will expire in <strong>{{ $daysRemaining }} days on {{ $warrantyEnd }}</strong>.
        </div>
    @endif

    <p class="lead-greeting">
        Hi, <strong>{{ $recipient->first_name ?? $recipient->name }}</strong>!
    </p>

    <p class="lead-message">
        The Dex Project Management System has flagged a project warranty milestone that requires operational awareness. Please review the project details below:
    </p>

    <div class="details-box">
        <table class="details-table">
            <tr>
                <td class="details-label">Project Code:</td>
                <td class="details-value" style="color: #2C5F7C;">{{ $project->project_code ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="details-label">Project Name:</td>
                <td class="details-value">{{ $project->name }}</td>
            </tr>
            <tr>
                <td class="details-label">Client Name:</td>
                <td class="details-value">{{ $project->client_name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="details-label">Warranty Expiry:</td>
                <td class="details-value" style="color: {{ $daysRemaining === 1 ? '#dc2626' : '#d97706' }}; font-weight: 700;">
                    {{ $warrantyEnd }} ({{ $daysRemaining === 1 ? 'Tomorrow' : "in {$daysRemaining} days" }})
                </td>
            </tr>
            <tr>
                <td class="details-label">Warranty Period:</td>
                <td class="details-value">{{ $project->warranty_period ?? '1 Year' }}</td>
            </tr>
            <tr>
                <td class="details-label">Project Status:</td>
                <td class="details-value">{{ ucfirst($project->status) }}</td>
            </tr>
        </table>
    </div>

    <div class="cta-container">
        <a href="{{ $actionUrl }}" class="btn-primary {{ $daysRemaining === 1 ? 'btn-amber' : '' }}" target="_blank" rel="noopener">
            View Project in Dex PMS
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

