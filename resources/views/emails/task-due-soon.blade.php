@extends('emails.layouts.base')

@section('title', 'Upcoming Task Deadline - Dex PMS')

@section('content')
    <div style="text-align: center; margin-bottom: 16px;">
        <span class="pill-badge pill-amber">Task Due Soon</span>
    </div>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; text-align: center; margin: 0 0 16px 0;">
        Upcoming Deadline: {{ $subtask ? $subtask->title : $task->task_name }}
    </h2>

    <div class="alert-banner alert-warning">
        <strong>Urgent Attention:</strong> This task is scheduled for completion <strong>{{ $relativeUrgency }}</strong> ({{ $dueDateFormatted }}). Please review active work and address any potential bottlenecks.
    </div>

    <p class="lead-greeting">
        Hi, <strong>{{ $recipient->first_name ?? $recipient->name }}</strong>!
    </p>

    <p class="lead-message">
        The Dex Project Management System has flagged an approaching deadline for the following task. Please see the task details and status below:
    </p>

    <div class="details-box">
        <table class="details-table">
            <tr>
                <td class="details-label">Deadline / Due:</td>
                <td class="details-value" style="color: #d97706;">{{ $dueDateFormatted }} ({{ $relativeUrgency }})</td>
            </tr>
            @if($subtask)
                <tr>
                    <td class="details-label">Specific Sub Task:</td>
                    <td class="details-value" style="color: #2C5F7C;">{{ $subtask->title }}</td>
                </tr>
                <tr>
                    <td class="details-label">Parent Major Task:</td>
                    <td class="details-value">{{ $task->task_name }}</td>
                </tr>
            @else
                <tr>
                    <td class="details-label">Major Task:</td>
                    <td class="details-value" style="color: #2C5F7C;">{{ $task->task_name }}</td>
                </tr>
            @endif
            <tr>
                <td class="details-label">Project:</td>
                <td class="details-value">{{ $project->name }} ({{ $project->project_code ?? 'N/A' }})</td>
            </tr>
            <tr>
                <td class="details-label">Client Name:</td>
                <td class="details-value">{{ $project->client_name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="details-label">Task Progress:</td>
                <td class="details-value">{{ (int) ($task->progress_percentage ?? 0) }}%</td>
            </tr>
        </table>
    </div>

    <div class="cta-container">
        <a href="{{ $actionUrl }}" class="btn-primary btn-amber" target="_blank" rel="noopener">
            View Due Task in Dex PMS
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

