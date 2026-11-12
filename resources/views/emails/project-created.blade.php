@extends('emails.layouts.base')

@section('title', 'New Project Created - Dex PMS')

@section('content')
    <div style="text-align: center; margin-bottom: 16px;">
        <span class="pill-badge pill-green">New Project Created</span>
    </div>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; text-align: center; margin: 0 0 16px 0;">
        Project Created: {{ $project->name }}
    </h2>

    <p class="lead-greeting">
        Hi, <strong>{{ $recipient->first_name ?? $recipient->name }}</strong>!
    </p>

    <p class="lead-message">
        A new project has been created in the Dex Project Management System. The project has been registered with initial specifications and is ready for resource planning and execution.
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
                <td class="details-label">Initial Status:</td>
                <td class="details-value">{{ ucfirst($project->status ?? 'pending') }}</td>
            </tr>
            <tr>
                <td class="details-label">Contract Price:</td>
                <td class="details-value">₱{{ number_format((float) ($project->contract_price ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td class="details-label">Location:</td>
                <td class="details-value">{{ $project->location ?? 'Not specified' }}</td>
            </tr>
            <tr>
                <td class="details-label">Planned Timeline:</td>
                <td class="details-value">
                    {{ $project->start_date ? $project->start_date->format('M j, Y') : 'TBD' }} &ndash;
                    {{ $project->end_date ? $project->end_date->format('M j, Y') : 'TBD' }}
                </td>
            </tr>
            @if($project->warranty_period)
                <tr>
                    <td class="details-label">Warranty Period:</td>
                    <td class="details-value">{{ $project->warranty_period }}</td>
                </tr>
            @endif
        </table>
    </div>

    <div class="cta-container">
        <a href="{{ $actionUrl }}" class="btn-primary" target="_blank" rel="noopener">
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

