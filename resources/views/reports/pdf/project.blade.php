<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Project Report — {{ $project->project_code }} — {{ $project->name }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }

        body, div, p, h1, h2, h3, h4, h5, h6, table, thead, tbody, tfoot, tr, th, td, ul, ol, li, span, img {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.4;
            color: #1B1E23;
            background-color: #FFFFFF;
            padding-bottom: 24px;
        }

        .mono {
            font-family: 'DejaVu Sans Mono', 'Courier New', monospace;
        }

        /* ── Running Page Footer ── */
        .page-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 20px;
            border-top: 1px solid #DDE1E7;
            padding-top: 4px;
            font-size: 7pt;
            color: #6B7280;
            background-color: #FFFFFF;
        }

        .footer-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        /* ── Top Corporate Header with DEX Logo ── */
        .report-header {
            border-bottom: 2.5px solid #2C5F7C;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .header-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .logo-box {
            width: 52px;
            vertical-align: middle;
        }

        .logo-img {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            display: block;
        }

        .logo-fallback {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            background-color: #2C5F7C;
            color: #FFFFFF;
            font-weight: bold;
            font-size: 12pt;
            text-align: center;
            line-height: 44px;
        }

        .brand-cell {
            vertical-align: middle;
            padding-left: 8px;
        }

        .company-name {
            font-size: 13pt;
            font-weight: bold;
            color: #1B1E23;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        .company-sub {
            font-size: 7.5pt;
            color: #2C5F7C;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        .company-tagline {
            font-size: 7pt;
            color: #6B7280;
            margin-top: 1px;
        }

        .meta-cell {
            width: 205px;
            text-align: right;
            vertical-align: middle;
            font-size: 7.5pt;
            color: #5E6370;
        }

        .doc-badge {
            display: inline-block;
            background-color: #2C5F7C;
            color: #FFFFFF;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 3px 9px;
            border-radius: 3px;
            margin-bottom: 4px;
        }

        /* ── Project Title Banner ── */
        .title-banner {
            background-color: #F7F8FA;
            border: 1px solid #DDE1E7;
            border-left: 4px solid #2C5F7C;
            border-radius: 4px;
            padding: 9px 12px;
            margin-bottom: 12px;
        }

        .title-banner-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .project-title {
            font-size: 12pt;
            font-weight: bold;
            color: #1B1E23;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .project-subtitle {
            font-size: 8pt;
            color: #5E6370;
            margin-top: 2px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* ── Status Pills ── */
        .status-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .status-completed {
            background-color: #EBF5F0;
            color: #2E7D53;
            border: 1px solid #B7DFC7;
        }

        .status-ongoing {
            background-color: #FDF3E0;
            color: #8A5A00;
            border: 1px solid #F5D491;
        }

        .status-pending {
            background-color: #F0F1F3;
            color: #5E6370;
            border: 1px solid #D1D5DB;
        }

        .status-delayed {
            background-color: #FDECEA;
            color: #C0392B;
            border: 1px solid #F5B7B1;
        }

        /* ── Section Containers ── */
        .report-section {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }

        .report-section-flow {
            margin-bottom: 12px;
        }

        .section-header {
            background-color: #EBF3F8;
            border: 1px solid #C8DCE8;
            border-left: 4px solid #2C5F7C;
            padding: 5px 10px;
            margin-bottom: 7px;
        }

        .section-header-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .section-number {
            display: inline-block;
            background-color: #2C5F7C;
            color: #FFFFFF;
            font-size: 7pt;
            font-weight: bold;
            padding: 1px 6px;
            border-radius: 3px;
            margin-right: 6px;
        }

        .section-title {
            font-size: 9pt;
            font-weight: bold;
            color: #1B1E23;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .section-meta {
            text-align: right;
            font-size: 7pt;
            color: #2C5F7C;
            font-weight: bold;
        }

        /* ── Key-Value Grid Tables ── */
        .kv-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            border: 1px solid #DDE1E7;
        }

        .kv-table td {
            padding: 5.5px 8px;
            border: 1px solid #DDE1E7;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .kv-label {
            font-size: 6.75pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #6B7280;
            display: block;
            margin-bottom: 2px;
        }

        .kv-value {
            font-size: 8.5pt;
            color: #1B1E23;
            font-weight: bold;
        }

        .kv-value-normal {
            font-size: 8pt;
            color: #1B1E23;
            font-weight: normal;
        }

        /* ── Financial KPI Strip ── */
        .fin-kpi-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .fin-kpi-card {
            width: 25%;
            background-color: #F7F8FA;
            border: 1px solid #DDE1E7;
            padding: 7px 8px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .fin-kpi-label {
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #6B7280;
            margin-bottom: 2px;
        }

        .fin-kpi-val {
            font-size: 9.5pt;
            font-weight: bold;
            color: #1B1E23;
        }

        .text-accent { color: #2C5F7C; }
        .text-success { color: #2E7D53; }
        .text-warning { color: #8A5A00; }
        .text-danger { color: #C0392B; }
        .text-muted { color: #6B7280; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* ── Standard Data Tables ── */
        .data-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            border: 1px solid #DDE1E7;
        }

        .data-table th {
            background-color: #F0F2F5;
            color: #1B1E23;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 5.5px 6px;
            border: 1px solid #DDE1E7;
            text-align: left;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .data-table td {
            padding: 5px 6px;
            border: 1px solid #DDE1E7;
            font-size: 7.75pt;
            color: #1B1E23;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .data-table tr:nth-child(even) td {
            background-color: #FAFBFD;
        }

        .data-table tfoot td {
            background-color: #EBF3F8;
            font-weight: bold;
            font-size: 8pt;
            border-top: 1.5px solid #2C5F7C;
        }

        .badge-neutral {
            display: inline-block;
            background-color: #F0F2F5;
            border: 1px solid #DDE1E7;
            color: #1B1E23;
            font-size: 6.75pt;
            font-weight: bold;
            padding: 1px 5px;
            border-radius: 3px;
        }

        .empty-box {
            border: 1px dashed #DDE1E7;
            background-color: #F7F8FA;
            padding: 9px 12px;
            text-align: center;
            font-size: 8pt;
            color: #6B7280;
            font-style: italic;
        }

        /* ── Progress Bar ── */
        .progress-track {
            width: 100%;
            height: 6px;
            background-color: #E5E7EB;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 4px;
        }

        .progress-fill {
            height: 6px;
            background-color: #2C5F7C;
        }

        /* ── Signature Block ── */
        .signature-section {
            margin-top: 18px;
            page-break-inside: avoid;
        }

        .sig-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .sig-cell {
            width: 48%;
            vertical-align: bottom;
            padding-top: 22px;
        }

        .sig-line {
            border-top: 1px solid #1B1E23;
            padding-top: 4px;
            font-size: 8pt;
            font-weight: bold;
            color: #1B1E23;
        }

        .sig-role {
            font-size: 7pt;
            color: #6B7280;
        }
    </style>
</head>
<body>

    {{-- Running Footer on All Pages --}}
    <div class="page-footer">
        <table class="footer-table">
            <tr>
                <td><strong>DEX INTERNATIONAL CO.</strong> · Project Management System · Official Engineering &amp; Financial Dossier</td>
                <td class="text-right mono">Ref: {{ $referenceNo }} · Generated: {{ $generatedAt }}</td>
            </tr>
        </table>
    </div>

    {{-- Top Corporate Header with DEX LOGO --}}
    <div class="report-header">
        <table class="header-table">
            <tr>
                <td class="logo-box">
                    @if (!empty($logoBase64))
                        <img src="{{ $logoBase64 }}" alt="DEX Logo" class="logo-img">
                    @else
                        <div class="logo-fallback">DEX</div>
                    @endif
                </td>
                <td class="brand-cell">
                    <div class="company-name">Dex International Co.</div>
                    <div class="company-sub">Engineering · Elevator &amp; Crane Installation · Servicing</div>
                    <div class="company-tagline">Centralized Project Management &amp; Technical Reporting System</div>
                </td>
                <td class="meta-cell">
                    <div class="doc-badge">Official Project Report</div>
                    <div class="mono"><strong>Ref No:</strong> {{ $referenceNo }}</div>
                    <div><strong>Prepared By:</strong> {{ $generatedBy }} ({{ $generatedByRole }})</div>
                    <div class="mono"><strong>Date:</strong> {{ $generatedAt }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Project Title Banner --}}
    @php
        $statusClass = match ($project->status) {
            'completed' => 'status-completed',
            'ongoing' => 'status-ongoing',
            'delayed' => 'status-delayed',
            default => 'status-pending',
        };
        $sectionIndex = 1;
    @endphp

    <div class="title-banner">
        <table class="title-banner-table">
            <tr>
                <td style="vertical-align: middle;">
                    <div class="project-title">{{ $project->name }}</div>
                    <div class="project-subtitle">
                        <span class="mono" style="font-weight: bold; color: #2C5F7C;">{{ $project->project_code }}</span>
                        &nbsp;·&nbsp; Client: <strong>{{ $project->client_name }}</strong>
                        &nbsp;·&nbsp; Category: <strong>{{ $project->project_type ?: 'General Installation' }}</strong>
                        @if ($project->trashed())
                            &nbsp;·&nbsp; <span style="color: #C0392B; font-weight: bold;">[ARCHIVED RECORD]</span>
                        @endif
                    </div>
                </td>
                <td style="text-align: right; vertical-align: middle; width: 170px;">
                    <span class="status-pill {{ $statusClass }}">{{ strtoupper($project->status) }}</span>
                    <div style="font-size: 7.5pt; color: #5E6370; margin-top: 4px;">
                        Overall Progress: <strong class="mono">{{ $overallProgress }}%</strong>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- =========================================================================
         1. PROJECT OVERVIEW (Level 1 — Executive Identification & Schedule)
         ========================================================================= --}}
    @if ($sections['overview'])
    <div class="report-section">
        <div class="section-header">
            <table class="section-header-table">
                <tr>
                    <td>
                        <span class="section-number">0{{ $sectionIndex++ }}</span>
                        <span class="section-title">Project Overview &amp; Schedule</span>
                    </td>
                    <td class="section-meta mono">Code: {{ $project->project_code }}</td>
                </tr>
            </table>
        </div>

        <table class="kv-table">
            <tr>
                <td style="width: 25%;">
                    <span class="kv-label">Project Code</span>
                    <span class="kv-value mono text-accent">{{ $project->project_code }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="kv-label">Client / Organization</span>
                    <span class="kv-value">{{ $project->client_name }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="kv-label">Project Type</span>
                    <span class="kv-value">{{ $project->project_type ?: 'General' }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="kv-label">Payment Status</span>
                    <span class="kv-value">{{ $project->payment_status ?: 'Partial' }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="kv-label">Start Date</span>
                    <span class="kv-value mono">{{ $project->start_date ? $project->start_date->format('M j, Y') : $project->created_at->format('M j, Y') }}</span>
                </td>
                <td>
                    <span class="kv-label">Target Completion</span>
                    <span class="kv-value mono text-accent">{{ $project->target_completion_date ? $project->target_completion_date->format('M j, Y') : 'Not set' }}</span>
                </td>
                <td>
                    <span class="kv-label">Warranty Coverage</span>
                    <span class="kv-value mono">
                        {{ $project->warranty_period ?? '1 Year' }}
                        @if ($project->warranty_end_date)
                            <span style="font-size: 7.5pt; font-weight: normal; color: #6B7280;">(Until {{ $project->warranty_end_date->format('M j, Y') }})</span>
                        @endif
                    </span>
                </td>
                <td>
                    <span class="kv-label">Execution Progress</span>
                    <span class="kv-value mono">{{ $overallProgress }}% ({{ $completedSubtasks }}/{{ $totalSubtasks }} tasks)</span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="kv-label">Site Location / Installation Address</span>
                    <span class="kv-value-normal">{{ $project->full_address ?: 'Not specified' }}</span>
                </td>
                <td>
                    <span class="kv-label">Project Lead (Assigned Manager)</span>
                    <span class="kv-value">{{ $projectLead ?? 'None' }}</span>
                </td>
                <td>
                    <span class="kv-label">Project Creator</span>
                    <span class="kv-value-normal">{{ $projectCreator ?? ($project->creator?->name ?? 'System') }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <span class="kv-label">Scope &amp; Project Description</span>
                    <span class="kv-value-normal">{{ $project->description ?: 'No additional project description provided.' }}</span>
                </td>
            </tr>
        </table>
    </div>
    @endif

    {{-- =========================================================================
         2. ELEVATOR TECHNICAL SPECIFICATIONS (Level 2 — Engineering Scope)
         ========================================================================= --}}
    @if ($sections['specs'])
    <div class="report-section">
        <div class="section-header">
            <table class="section-header-table">
                <tr>
                    <td>
                        <span class="section-number">0{{ $sectionIndex++ }}</span>
                        <span class="section-title">Elevator Technical Specifications</span>
                    </td>
                    <td class="section-meta">Engineering Parameters &amp; Cabin Finishes</td>
                </tr>
            </table>
        </div>

        <table class="kv-table">
            @foreach (array_chunk($specifications, 3) as $specRow)
                <tr>
                    @foreach ($specRow as $spec)
                        <td style="width: 33.33%;">
                            <span class="kv-label">{{ $spec['label'] }}</span>
                            <span class="kv-value {{ $spec['mono'] ? 'mono' : '' }}">{{ $spec['value'] }}</span>
                        </td>
                    @endforeach
                    @for ($i = count($specRow); $i < 3; $i++)
                        <td style="width: 33.33%;"></td>
                    @endfor
                </tr>
            @endforeach
        </table>
    </div>
    @endif

    {{-- =========================================================================
         3. FINANCIALS & BUDGET SUMMARY (Level 3 — Financial Health & Breakdown)
         ========================================================================= --}}
    @if ($sections['financials'])
    <div class="report-section">
        <div class="section-header">
            <table class="section-header-table">
                <tr>
                    <td>
                        <span class="section-number">0{{ $sectionIndex++ }}</span>
                        <span class="section-title">Financials &amp; Budget</span>
                    </td>
                    <td class="section-meta">
                        Health: {{ $financials['costing_status'] }} · Payment: {{ $financials['payment_status'] }}
                    </td>
                </tr>
            </table>
        </div>

        {{-- KPI Cards Row --}}
        <table class="fin-kpi-table">
            <tr>
                <td class="fin-kpi-card">
                    <div class="fin-kpi-label">Contract Price</div>
                    <div class="fin-kpi-val mono text-success">&#8369;{{ number_format($financials['contract_price'], 2) }}</div>
                </td>
                <td class="fin-kpi-card">
                    <div class="fin-kpi-label">Total Actual Spend</div>
                    <div class="fin-kpi-val mono">&#8369;{{ number_format($financials['actual_spend'], 2) }}</div>
                </td>
                <td class="fin-kpi-card">
                    <div class="fin-kpi-label">Remaining Balance</div>
                    <div class="fin-kpi-val mono {{ $financials['remaining_budget'] >= 0 ? 'text-success' : 'text-danger' }}">
                        &#8369;{{ number_format($financials['remaining_budget'], 2) }}
                    </div>
                </td>
                <td class="fin-kpi-card">
                    <div class="fin-kpi-label">Budget Utilization</div>
                    <div class="fin-kpi-val mono {{ $financials['spend_percentage'] > 100 ? 'text-danger' : ($financials['spend_percentage'] >= 85 ? 'text-warning' : 'text-accent') }}">
                        {{ number_format($financials['spend_percentage'], 1) }}%
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" style="width: {{ min(100, $financials['spend_percentage']) }}%; background-color: {{ $financials['spend_percentage'] > 100 ? '#C0392B' : ($financials['spend_percentage'] >= 85 ? '#C98A2C' : '#2E7D53') }};"></div>
                    </div>
                </td>
            </tr>
        </table>

        {{-- Category Breakdown Table --}}
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 38%;">Cost Category</th>
                    <th class="text-center" style="width: 16%;">Logged Entries</th>
                    <th class="text-right" style="width: 24%;">Category Spend (PHP)</th>
                    <th class="text-right" style="width: 22%;">Share of Actual Spend</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($financials['categories'] as $cat)
                    <tr>
                        <td><strong>{{ $cat['label'] }}</strong></td>
                        <td class="text-center mono">{{ $cat['count'] }} {{ $cat['count'] === 1 ? 'item' : 'items' }}</td>
                        <td class="text-right mono">&#8369;{{ number_format($cat['total'], 2) }}</td>
                        <td class="text-right mono">{{ number_format($cat['percentage'], 1) }}%</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>Total Project Spend vs. Contract Budget</td>
                    <td class="text-center mono">{{ $costs->count() }} entries</td>
                    <td class="text-right mono">&#8369;{{ number_format($financials['actual_spend'], 2) }}</td>
                    <td class="text-right mono">{{ number_format($financials['spend_percentage'], 1) }}% of Contract</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif

    {{-- =========================================================================
         4. ITEMIZED EXPENDITURES LEDGER (Level 4 — Financial Audit Trail)
         ========================================================================= --}}
    @if ($sections['expenditures'])
    <div class="report-section-flow">
        <div class="section-header">
            <table class="section-header-table">
                <tr>
                    <td>
                        <span class="section-number">0{{ $sectionIndex++ }}</span>
                        <span class="section-title">Itemized Expenditures Ledger</span>
                    </td>
                    <td class="section-meta mono">
                        {{ $costs->count() }} {{ Str::plural('Record', $costs->count()) }} · Total: &#8369;{{ number_format($financials['actual_spend'], 2) }}
                    </td>
                </tr>
            </table>
        </div>

        @if ($costs->isEmpty())
            <div class="empty-box">
                No itemized expenditures have been logged for this project as of {{ $generatedAt }}.
            </div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 6%;" class="text-center">#</th>
                        <th style="width: 14%;">Date Incurred</th>
                        <th style="width: 34%;">Description / Material Item</th>
                        <th style="width: 15%;">Category</th>
                        <th style="width: 7%;" class="text-center">Qty</th>
                        <th style="width: 11%;">Logged By</th>
                        <th style="width: 13%;" class="text-right">Amount (PHP)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($costs as $index => $cost)
                        <tr>
                            <td class="text-center mono text-muted">{{ $index + 1 }}</td>
                            <td class="mono">
                                {{ $cost->incurred_date ? $cost->incurred_date->format('M j, Y') : ($cost->created_at ? $cost->created_at->format('M j, Y') : '—') }}
                            </td>
                            <td>
                                <strong>{{ $cost->description }}</strong>
                                @if ($cost->resource)
                                    <div class="mono text-muted" style="font-size: 7pt;">
                                        Catalog Ref: {{ $cost->resource->resource_code }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="badge-neutral">{{ ucfirst($cost->cost_type) }}</span>
                            </td>
                            <td class="text-center mono">{{ $cost->quantity ?? 1 }}</td>
                            <td>{{ $cost->creator?->name ?? 'System' }}</td>
                            <td class="text-right mono"><strong>&#8369;{{ number_format((float) $cost->amount, 2) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">Total Recorded Project Expenditures</td>
                        <td class="text-center mono">{{ $costs->sum(fn ($c) => $c->quantity ?? 1) }}</td>
                        <td></td>
                        <td class="text-right mono">&#8369;{{ number_format((float) $costs->sum('amount'), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>
    @endif

    {{-- =========================================================================
         5. ASSIGNED MANPOWER & PERSONNEL (Level 5 — Currently Assigned Field Team)
         ========================================================================= --}}
    @if ($sections['manpower'])
    <div class="report-section-flow">
        <div class="section-header">
            <table class="section-header-table">
                <tr>
                    <td>
                        <span class="section-number">0{{ $sectionIndex++ }}</span>
                        <span class="section-title">Assigned Manpower &amp; Field Personnel</span>
                    </td>
                    <td class="section-meta mono">
                        {{ count($manpower) > 0 ? count($manpower) . ' ' . Str::plural('Personnel', count($manpower)) . ' Assigned' : 'None' }}
                    </td>
                </tr>
            </table>
        </div>

        @if (empty($manpower))
            <div class="empty-box">
                None
            </div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 8%;" class="text-center">#</th>
                        <th style="width: 20%;">Employee ID</th>
                        <th style="width: 32%;">Full Name</th>
                        <th style="width: 24%;">Expertise / Role</th>
                        <th style="width: 16%;">Date Assigned</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($manpower as $idx => $person)
                        <tr>
                            <td class="text-center mono text-muted">{{ $idx + 1 }}</td>
                            <td class="mono text-accent"><strong>{{ $person['employee_id'] }}</strong></td>
                            <td><strong>{{ $person['full_name'] }}</strong></td>
                            <td>
                                <span class="badge-neutral">{{ $person['expertise'] }}</span>
                            </td>
                            <td class="mono">{{ $person['assigned_at'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @endif

    {{-- =========================================================================
         SIGNATURE & VERIFICATION FOOTER
         ========================================================================= --}}
    <div class="signature-section">
        <table class="sig-table">
            <tr>
                <td class="sig-cell">
                    <div class="sig-line">{{ $generatedBy }}</div>
                    <div class="sig-role">Prepared By ({{ $generatedByRole }}) · Dex International Co.</div>
                </td>
                <td style="width: 4%;"></td>
                <td class="sig-cell">
                    <div class="sig-line">Authorized Project Director / Management</div>
                    <div class="sig-role">Reviewed &amp; Approved By · Date: ________________________</div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>

