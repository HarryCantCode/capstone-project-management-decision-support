@extends('emails.layouts.base')

@section('title', $isSubtask ? 'New Sub Task Added - Dex PMS' : 'New Major Task Created - Dex PMS')

@section('content')
    <div style="text-align: center; margin-bottom: 16px;">
        @if($isSubtask)
            <span class="pill-badge pill-blue">New Sub Task</span>
        @else
            <span class="pill-badge pill-blue">New Major Task</span>
        @endif
    </div>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; text-align: center; margin: 0 0 16px 0;">
        @if($isSubtask)
            New Sub Task Logged: {{ $subtaskTitle }}
        @else
            New Major Task Logged: {{ $task->task_name }}
        @endif
    </h2>

    <p class="lead-greeting">
        Hi, <strong>{{ $recipient->first_name ?? $recipient->name }}</strong>!
    </p>

    <p class="lead-message">
        @if($isSubtask)
            A new sub / specific task has been added under major task <strong>{{ $task->task_name }}</strong> in project <strong>{{ $project->name }}</strong> ({{ $project->project_code ?? 'N/A' }}). Please review the details below:
        @else
            A new major task has been created for project <strong>{{ $project->name }}</strong> ({{ $project->project_code ?? 'N/A' }}). Please review the task schedule and milestones below:
        @endif
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
            @if($isSubtask)
                <tr>
                    <td class="details-label">Major Task:</td>
                    <td class="details-value">{{ $task->task_name }}</td>
                </tr>
                <tr>
                    <td class="details-label">Sub Task:</td>
                    <td class="details-value" style="color: #2C5F7C;">{{ $subtaskTitle }}</td>
                </tr>
                <tr>
                    <td class="details-label">Due Date:</td>
                    <td class="details-value">{{ $subtask?->due_date ? $subtask->due_date->format('M j, Y') : 'Not specified' }}</td>
                </tr>
            @else
                <tr>
                    <td class="details-label">Task Name:</td>
                    <td class="details-value" style="color: #2C5F7C;">{{ $task->task_name }}</td>
                </tr>
                <tr>
                    <td class="details-label">Start Date:</td>
                    <td class="details-value">{{ $task->start_date ? $task->start_date->format('M j, Y') : 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="details-label">End / Target Date:</td>
                    <td class="details-value">{{ $task->end_date ? $task->end_date->format('M j, Y') : 'N/A' }}</td>
                </tr>
            @endif
            <tr>
                <td class="details-label">Task Progress:</td>
                <td class="details-value">{{ (int) ($task->progress_percentage ?? 0) }}%</td>
            </tr>
        </table>
    </div>

    <div class="cta-container">
        <a href="{{ $actionUrl }}" class="btn-primary" target="_blank" rel="noopener">
            @if($isSubtask)
                View Sub Task in Dex PMS
            @else
                View Major Task in Dex PMS
            @endif
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

