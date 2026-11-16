@extends('emails.layouts.base')

@section('title', 'Task Assignment - Dex PMS')

@section('content')
    <div style="text-align: center; margin-bottom: 16px;">
        <span class="pill-badge pill-blue">Task Assigned</span>
    </div>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; text-align: center; margin: 0 0 16px 0;">
        You Have Been Assigned: {{ $subtask->title }}
    </h2>

    <p class="lead-greeting">
        Hi, <strong>{{ $recipient->first_name ?? $recipient->name }}</strong>!
    </p>

    <p class="lead-message">
        You have been assigned to execute the specific task <strong>{{ $subtask->title }}</strong> under major task <strong>{{ $task->task_name }}</strong> in project <strong>{{ $project->name }}</strong> ({{ $project->project_code ?? 'N/A' }}). Please review the task details below:
    </p>

    <div class="details-box">
        <table class="details-table">
            <tr>
                <td class="details-label">Project:</td>
                <td class="details-value">{{ $project->name }} ({{ $project->project_code ?? 'N/A' }})</td>
            </tr>
            <tr>
                <td class="details-label">Client:</td>
                <td class="details-value">{{ $project->client_name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="details-label">Major Task:</td>
                <td class="details-value">{{ $task->task_name }}</td>
            </tr>
            <tr>
                <td class="details-label">Specific Task:</td>
                <td class="details-value" style="color: #2C5F7C; font-weight: 600;">{{ $subtask->title }}</td>
            </tr>
            <tr>
                <td class="details-label">Target Date:</td>
                <td class="details-value">{{ $subtask->due_date ? $subtask->due_date->format('M j, Y') : ($task->start_date ? $task->start_date->format('M j, Y') . ' to ' . $task->end_date->format('M j, Y') : 'Scheduled Week') }}</td>
            </tr>
            <tr>
                <td class="details-label">Status:</td>
                <td class="details-value">Assigned / In Progress</td>
            </tr>
        </table>
    </div>

    <div class="cta-container">
        <a href="{{ $actionUrl }}" class="btn-primary" target="_blank" rel="noopener">
            View Assigned Task in Dex PMS
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
