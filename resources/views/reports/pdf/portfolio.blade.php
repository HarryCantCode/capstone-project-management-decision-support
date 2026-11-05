<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Executive Portfolio Summary — Dex International Co.</title>
    <style>
        @page {
            margin: 30px 34px 48px 34px;
        }

        * {
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
        }

        .mono {
            font-family: 'DejaVu Sans Mono', 'Courier New', monospace;
        }

        .page-footer {
            position: fixed;
            bottom: -32px;
            left: 0;
            right: 0;
            height: 24px;
            border-top: 1px solid #DDE1E7;
            padding-top: 5px;
            font-size: 7.5pt;
            color: #6B7280;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-header {
            border-bottom: 2.5px solid #2C5F7C;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .header-table {
            width: 100%;
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
            font-size: 13.5pt;
            font-weight: bold;
            color: #1B1E23;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        .company-sub {
            font-size: 8pt;
            color: #2C5F7C;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-top: 1px;
        }

        .company-tagline {
            font-size: 7.5pt;
            color: #6B7280;
        }

        .meta-cell {
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
            letter-spacing: 0.8px;
            padding: 3px 9px;
            border-radius: 3px;
            margin-bottom: 4px;
        }

        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .kpi-card {
            width: 25%;
            background-color: #F7F8FA;
            border: 1px solid #DDE1E7;
            padding: 8px 10px;
            vertical-align: top;
        }

        .kpi-label {
            font-size: 6.75pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6B7280;
            margin-bottom: 2px;
        }

        .kpi-val {
            font-size: 11pt;
            font-weight: bold;
            color: #1B1E23;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #DDE1E7;
        }

        .data-table th {
            background-color: #F0F2F5;
            color: #1B1E23;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 6px 8px;
            border: 1px solid #DDE1E7;
            text-align: left;
        }

        .data-table td {
            padding: 5.5px 8px;
            border: 1px solid #DDE1E7;
            font-size: 8pt;
            color: #1B1E23;
            vertical-align: top;
        }

        .data-table tr:nth-child(even) td {
            background-color: #FAFBFD;
        }

        .data-table tfoot td {
            background-color: #EBF3F8;
            font-weight: bold;
            font-size: 8.25pt;
            border-top: 1.5px solid #2C5F7C;
        }

        .status-pill {
            display: inline-block;
            padding: 1.5px 7px;
            border-radius: 10px;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-completed { background-color: #EBF5F0; color: #2E7D53; border: 1px solid #B7DFC7; }
        .status-ongoing { background-color: #FDF3E0; color: #8A5A00; border: 1px solid #F5D491; }
        .status-pending { background-color: #F0F1F3; color: #5E6370; border: 1px solid #D1D5DB; }
        .status-delayed { background-color: #FDECEA; color: #C0392B; border: 1px solid #F5B7B1; }

        .text-accent { color: #2C5F7C; }
        .text-success { color: #2E7D53; }
        .text-warning { color: #8A5A00; }
        .text-danger { color: #C0392B; }
        .text-muted { color: #6B7280; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>

    <div class="page-footer">
        <table class="footer-table">
            <tr>
                <td><strong>DEX INTERNATIONAL CO.</strong> · Executive Project Portfolio Summary Report</td>
                <td class="text-right mono">Ref: {{ $referenceNo }} · Generated: {{ $generatedAt }}</td>
            </tr>
        </table>
    </div>

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
                    <div class="company-sub">Executive Project Portfolio &amp; Financial Ledger</div>
                    <div class="company-tagline">Multi-Project Status, Technical Capacity, Budgets &amp; Expenditures Summary</div>
                </td>
                <td class="meta-cell">
                    <div class="doc-badge">Portfolio Summary Report</div>
                    <div class="mono"><strong>Ref No:</strong> {{ $referenceNo }}</div>
                    <div><strong>Prepared By:</strong> {{ $generatedBy }} ({{ $generatedByRole }})</div>
                    <div class="mono"><strong>Generated:</strong> {{ $generatedAt }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="kpi-table">
        <tr>
            <td class="kpi-card">
                <div class="kpi-label">Portfolio Scope</div>
                <div class="kpi-val mono text-accent">{{ $summary['total_projects'] }} {{ Str::plural('Project', $summary['total_projects']) }}</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Total Contract Value</div>
                <div class="kpi-val mono text-success">&#8369;{{ number_format($summary['total_allocated'], 2) }}</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Total Actual Spend</div>
                <div class="kpi-val mono">&#8369;{{ number_format($summary['total_spent'], 2) }} ({{ $summary['utilization'] }}%)</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Remaining Portfolio Budget</div>
                <div class="kpi-val mono {{ $summary['total_remaining'] >= 0 ? 'text-success' : 'text-danger' }}">&#8369;{{ number_format($summary['total_remaining'], 2) }}</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 10%;">Code</th>
                <th style="width: 22%;">Project Name &amp; Type</th>
                <th style="width: 15%;">Client</th>
                <th style="width: 15%;">Elevator Specs</th>
                <th style="width: 9%;" class="text-center">Status</th>
                <th style="width: 10%;" class="text-right">Contract (PHP)</th>
                <th style="width: 10%;" class="text-right">Spend (PHP)</th>
                <th style="width: 9%;" class="text-right">Remaining</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($projects as $proj)
                <tr>
                    <td class="mono text-accent"><strong>{{ $proj->project_code }}</strong></td>
                    <td>
                        <strong>{{ $proj->name }}</strong>
                        <div class="text-muted" style="font-size: 7pt;">{{ $proj->project_type ?: 'General' }} · Target: {{ $proj->target_completion_date ? $proj->target_completion_date->format('M j, Y') : 'N/A' }}</div>
                    </td>
                    <td>{{ $proj->client_name }}</td>
                    <td class="mono" style="font-size: 7.25pt;">
                        {{ $proj->rated_capacity ?: '—' }} · {{ $proj->lifting_speed ?: '—' }}
                        @if ($proj->no_of_stops)
                            ({{ $proj->no_of_stops }} stops)
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="status-pill status-{{ $proj->status }}">{{ ucfirst($proj->status) }}</span>
                    </td>
                    <td class="text-right mono">&#8369;{{ number_format((float) $proj->contract_price, 2) }}</td>
                    <td class="text-right mono">&#8369;{{ number_format((float) $proj->actual_spend, 2) }}</td>
                    <td class="text-right mono {{ $proj->remaining_budget >= 0 ? 'text-success' : 'text-danger' }}">
                        &#8369;{{ number_format($proj->remaining_budget, 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted" style="padding: 14px;">No projects matched the selected portfolio filters.</td>
                </tr>
            @endforelse
        </tbody>
        @if ($projects->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="5">Portfolio Cumulative Financial Totals</td>
                    <td class="text-right mono">&#8369;{{ number_format($summary['total_allocated'], 2) }}</td>
                    <td class="text-right mono">&#8369;{{ number_format($summary['total_spent'], 2) }}</td>
                    <td class="text-right mono">&#8369;{{ number_format($summary['total_remaining'], 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

</body>
</html>

