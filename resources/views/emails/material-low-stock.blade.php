@extends('emails.layouts.base')

@section('title', 'Low Stock Warning - Dex PMS')

@section('content')
    <div style="text-align: center; margin-bottom: 16px;">
        <span class="pill-badge pill-red">Low Stock Warning</span>
    </div>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; text-align: center; margin: 0 0 16px 0;">
        Stock Alert: {{ $resource->name }}
    </h2>

    <div class="alert-banner alert-danger">
        <strong>Low Stock Warning:</strong> The available inventory for <strong>{{ $resource->name }}</strong> has reached or dropped below the threshold of <strong>{{ \App\Models\Resource::LOW_STOCK_THRESHOLD }} units</strong>. Prompt restocking or procurement action is recommended.
    </div>

    <p class="lead-greeting">
        Hi, <strong>{{ $recipient->first_name ?? $recipient->name }}</strong>!
    </p>

    <p class="lead-message">
        This automated inventory alert was triggered because the stock count for this resource in the Dex PMS catalog has fallen below the safety limit:
    </p>

    <div class="details-box">
        <table class="details-table">
            <tr>
                <td class="details-label">Material Name:</td>
                <td class="details-value">{{ $resource->name }}</td>
            </tr>
            <tr>
                <td class="details-label">Resource Code:</td>
                <td class="details-value" style="color: #2C5F7C;">{{ $resource->resource_code ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="details-label">Available Stock:</td>
                <td class="details-value" style="color: #dc2626; font-size: 14px;">
                    <strong>{{ $resource->quantity_available }} {{ $resource->unit ?? 'unit(s)' }}</strong> remaining
                </td>
            </tr>
            <tr>
                <td class="details-label">Safety Threshold:</td>
                <td class="details-value">&le; {{ \App\Models\Resource::LOW_STOCK_THRESHOLD }} units</td>
            </tr>
            <tr>
                <td class="details-label">Unit Cost:</td>
                <td class="details-value">₱{{ number_format((float) ($resource->unit_cost ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td class="details-label">Category:</td>
                <td class="details-value">{{ ucfirst($resource->category ?? 'Materials') }}</td>
            </tr>
        </table>
    </div>

    <div class="cta-container">
        <a href="{{ $actionUrl }}" class="btn-primary" target="_blank" rel="noopener">
            View Material in Dex PMS
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

