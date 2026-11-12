@extends('emails.layouts.base')

@section('title', ($isApproved ? 'Request Approved' : 'Request Rejected') . ' - Dex PMS')

@section('content')
    <div style="text-align: center; margin-bottom: 16px;">
        @if($isApproved)
            <span class="pill-badge pill-green">Request Approved</span>
        @else
            <span class="pill-badge pill-red">Request Rejected</span>
        @endif
    </div>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; text-align: center; margin: 0 0 16px 0;">
        @if($isApproved)
            Your Request Has Been Approved
        @else
            Your Request Has Been Rejected
        @endif
    </h2>

    @if($isApproved)
        <div class="alert-banner alert-success">
            <strong>Approved:</strong> Your submission regarding project <strong>{{ $project->name }}</strong> has been officially approved by management and applied to the system records.
        </div>
    @else
        <div class="alert-banner alert-danger">
            <strong>Not Approved:</strong> Your submission regarding project <strong>{{ $project->name }}</strong> was reviewed and rejected by management. Please consult the reviewer remarks below.
        </div>
    @endif

    <p class="lead-greeting">
        Hi, <strong>{{ $recipient->first_name ?? $recipient->name }}</strong>!
    </p>

    <p class="lead-message">
        Management has reviewed your pending request. Here is the outcome of the review:
    </p>

    <div class="details-box">
        <table class="details-table">
            <tr>
                <td class="details-label">Review Outcome:</td>
                <td class="details-value">
                    @if($isApproved)
                        <span style="color: #059669; font-weight: 700;">APPROVED</span>
                    @else
                        <span style="color: #dc2626; font-weight: 700;">REJECTED</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="details-label">Project:</td>
                <td class="details-value">{{ $project->name }} ({{ $project->project_code ?? 'N/A' }})</td>
            </tr>
            <tr>
                <td class="details-label">Request Type:</td>
                <td class="details-value" style="color: #2C5F7C;">{{ $actionTypeLabel }}</td>
            </tr>
            <tr>
                <td class="details-label">Item / Subject:</td>
                <td class="details-value">{{ $actionSummary }}</td>
            </tr>
            <tr>
                <td class="details-label">Reviewed By:</td>
                <td class="details-value">{{ $reviewer?->name ?? 'Manager / Admin' }}</td>
            </tr>
            <tr>
                <td class="details-label">Reviewed On:</td>
                <td class="details-value">{{ $pendingAction->reviewed_at ? $pendingAction->reviewed_at->format('M j, Y · g:i A') : now()->format('M j, Y · g:i A') }}</td>
            </tr>
            <tr>
                <td class="details-label">Reviewer Notes:</td>
                <td class="details-value" style="font-style: italic; color: #334155;">
                    {{ $pendingAction->admin_notes ?: 'No additional remarks provided.' }}
                </td>
            </tr>
        </table>
    </div>

    <div class="cta-container">
        <a href="{{ $actionUrl }}" class="btn-primary {{ $isApproved ? 'btn-green' : '' }}" target="_blank" rel="noopener">
            View Request in Dex PMS
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

