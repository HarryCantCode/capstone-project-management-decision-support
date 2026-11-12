@extends('emails.layouts.base')

@section('title', 'Staff Request Awaiting Review - Dex PMS')

@section('content')
    <div style="text-align: center; margin-bottom: 16px;">
        <span class="pill-badge pill-amber">Pending Review Required</span>
    </div>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; text-align: center; margin: 0 0 16px 0;">
        Staff Request: {{ $actionTitle }}
    </h2>

    <div class="alert-banner alert-warning">
        <strong>Review Required:</strong> Staff member <strong>{{ $staffUser->name }}</strong> submitted an action on project <strong>{{ $project->name }}</strong> that is currently pending manager / administrator approval.
    </div>

    <p class="lead-greeting">
        Hi, <strong>{{ $recipient->first_name ?? $recipient->name }}</strong>!
    </p>

    <p class="lead-message">
        Please review the submission details below to examine and decide whether to approve or reject the request:
    </p>

    <div class="details-box">
        <table class="details-table">
            <tr>
                <td class="details-label">Submitted By:</td>
                <td class="details-value">{{ $staffUser->name }} ({{ $staffUser->employee_number ?? $staffUser->email }})</td>
            </tr>
            <tr>
                <td class="details-label">Staff Role:</td>
                <td class="details-value">{{ $staffUser->job_title ?? 'Staff' }}</td>
            </tr>
            <tr>
                <td class="details-label">Project:</td>
                <td class="details-value">{{ $project->name }} ({{ $project->project_code ?? 'N/A' }})</td>
            </tr>
            <tr>
                <td class="details-label">Request Type:</td>
                <td class="details-value" style="color: #2C5F7C;">
                    {{ $actionTypeLabel }}
                </td>
            </tr>

            @if($pendingAction->action_type === 'add_expense')
                <tr>
                    <td class="details-label">Expense Description:</td>
                    <td class="details-value">{{ $payload['description'] ?? 'Expense' }}</td>
                </tr>
                <tr>
                    <td class="details-label">Amount:</td>
                    <td class="details-value" style="color: #0f172a; font-size: 14px;">
                        <strong>₱{{ number_format((float) ($payload['amount'] ?? 0), 2) }}</strong>
                    </td>
                </tr>
                <tr>
                    <td class="details-label">Cost Category:</td>
                    <td class="details-value">{{ ucfirst($payload['cost_type'] ?? 'materials') }}</td>
                </tr>
                <tr>
                    <td class="details-label">Date Incurred:</td>
                    <td class="details-value">{{ $payload['incurred_date'] ?? 'N/A' }}</td>
                </tr>
                @if(!empty($payload['resource_name']))
                    <tr>
                        <td class="details-label">Catalog Material:</td>
                        <td class="details-value">{{ $payload['resource_name'] }}</td>
                    </tr>
                @endif
            @elseif($pendingAction->action_type === 'complete_subtask')
                <tr>
                    <td class="details-label">Sub Task Marked:</td>
                    <td class="details-value">{{ $payload['subtask_title'] ?? $payload['subtask_name'] ?? 'Task' }}</td>
                </tr>
                <tr>
                    <td class="details-label">Staff Notes:</td>
                    <td class="details-value">{{ $payload['notes'] ?? $payload['description'] ?? 'No notes provided' }}</td>
                </tr>
                <tr>
                    <td class="details-label">Proof / Attachments:</td>
                    <td class="details-value">
                        @php
                            $filesCount = !empty($payload['files']) && is_array($payload['files'])
                                ? count($payload['files'])
                                : (!empty($payload['file_name']) ? 1 : 0);
                        @endphp
                        {{ $filesCount > 0 ? "{$filesCount} file(s) attached" : 'None attached' }}
                    </td>
                </tr>
            @else
                <tr>
                    <td class="details-label">Details:</td>
                    <td class="details-value">{{ json_encode($payload) }}</td>
                </tr>
            @endif

            <tr>
                <td class="details-label">Submitted At:</td>
                <td class="details-value">{{ $pendingAction->created_at ? $pendingAction->created_at->format('M j, Y · g:i A') : now()->format('M j, Y · g:i A') }}</td>
            </tr>
        </table>
    </div>

    <div class="cta-container">
        <a href="{{ $actionUrl }}" class="btn-primary btn-amber" target="_blank" rel="noopener">
            Review Request in Dex PMS
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

