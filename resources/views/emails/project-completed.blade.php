@extends('emails.layouts.base')

@section('title', 'Project Completed - Dex PMS')

@section('content')
    <div style="text-align: center; margin-bottom: 16px;">
        <span class="pill-badge pill-green">Project Completed</span>
    </div>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; text-align: center; margin: 0 0 16px 0;">
        Project Completed: {{ $project->name }}
    </h2>

    <div class="alert-banner alert-success">
        <strong>Milestone Achieved:</strong> Project <strong>{{ $project->name }}</strong> ({{ $project->project_code ?? 'N/A' }}) has officially reached completion and has been marked as <strong>Completed</strong> in Dex PMS.
    </div>

    <p class="lead-greeting">
        Hi, <strong>{{ $recipient->first_name ?? $recipient->name }}</strong>!
    </p>

    <p class="lead-message">
        All project milestones and operational tasks have been successfully concluded. Below is the summary of the completed project:
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
                <td class="details-label">Final Status:</td>
                <td class="details-value" style="color: #059669;">Completed</td>
            </tr>
            <tr>
                <td class="details-label">Contract Price:</td>
                <td class="details-value">₱{{ number_format((float) ($project->contract_price ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td class="details-label">Total Actual Spend:</td>
                <td class="details-value">₱{{ number_format((float) ($project->actual_spend ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td class="details-label">Completion Date:</td>
                <td class="details-value">{{ now()->format('M j, Y') }}</td>
            </tr>
            @if($project->warranty_end_date || $project->warranty_period)
                <tr>
                    <td class="details-label">Warranty Expiry:</td>
                    <td class="details-value">
                        {{ $project->warranty_end_date ? $project->warranty_end_date->format('M j, Y') : $project->warranty_period }}
                    </td>
                </tr>
            @endif
        </table>
    </div>

    <div class="cta-container">
        <a href="{{ $actionUrl }}" class="btn-primary btn-green" target="_blank" rel="noopener">
            View Completed Project in Dex PMS
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

