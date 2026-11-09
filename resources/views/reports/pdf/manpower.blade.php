<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Manpower &amp; Personnel Roster Report — Dex International Co.</title>
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
            font-size: 8.5pt;
            line-height: 1.38;
            color: #1B1E23;
            background-color: #FFFFFF;
            padding-bottom: 24px;
        }

        .mono {
            font-family: 'DejaVu Sans Mono', 'Courier New', monospace;
        }

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
            width: 50px;
            vertical-align: middle;
        }

        .logo-img {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: block;
        }

        .logo-fallback {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            background-color: #2C5F7C;
            color: #FFFFFF;
            font-weight: bold;
            font-size: 11.5pt;
            text-align: center;
            line-height: 42px;
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
            margin-top: 1px;
        }

        .company-tagline {
            font-size: 7pt;
            color: #6B7280;
        }

        .meta-cell {
            width: 205px;
            text-align: right;
            vertical-align: middle;
            font-size: 7.25pt;
            color: #5E6370;
        }

        .doc-badge {
            display: inline-block;
            background-color: #2C5F7C;
            color: #FFFFFF;
            font-size: 7.25pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 3px 8px;
            border-radius: 3px;
            margin-bottom: 4px;
        }

        .filter-banner {
            background-color: #EBF3F8;
            border: 1px solid #C8DCE8;
            border-left: 4px solid #2C5F7C;
            padding: 6px 10px;
            margin-bottom: 10px;
            font-size: 7.5pt;
            color: #1B1E23;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .kpi-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .kpi-card {
            width: 25%;
            background-color: #F7F8FA;
            border: 1px solid #DDE1E7;
            padding: 7px 8px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .kpi-label {
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #6B7280;
            margin-bottom: 2px;
        }

        .kpi-val {
            font-size: 9.5pt;
            font-weight: bold;
            color: #1B1E23;
        }

        .data-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            border: 1px solid #DDE1E7;
        }

        .data-table th {
            background-color: #F0F2F5;
            color: #1B1E23;
            font-size: 6.75pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 5px 5.5px;
            border: 1px solid #DDE1E7;
            text-align: left;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .data-table td {
            padding: 5px 5.5px;
            border: 1px solid #DDE1E7;
            font-size: 7.5pt;
            color: #1B1E23;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .status-assigned {
            display: inline-block;
            background-color: #EBF5F0;
            color: #2E7D53;
            border: 1px solid #B7DFC7;
            padding: 1px 5px;
            border-radius: 8px;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-available {
            display: inline-block;
            background-color: #F0F1F3;
            color: #5E6370;
            border: 1px solid #D1D5DB;
            padding: 1px 5px;
            border-radius: 8px;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-archived {
            display: inline-block;
            background-color: #FDECEA;
            color: #C0392B;
            border: 1px solid #F5B7B1;
            padding: 1px 5px;
            border-radius: 8px;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .expertise-badge {
            display: inline-block;
            background-color: #EBF3F8;
            border: 1px solid #C8DCE8;
            color: #2C5F7C;
            font-size: 6.75pt;
            font-weight: bold;
            padding: 1px 5px;
            border-radius: 3px;
        }

        .recent-proj-list {
            margin-top: 2px;
        }

        .recent-proj-item {
            background-color: #F7F8FA;
            border: 1px solid #DDE1E7;
            border-left: 2.5px solid #2C5F7C;
            padding: 2.5px 5px;
            margin-bottom: 3px;
            font-size: 6.75pt;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .text-accent { color: #2C5F7C; }
        .text-success { color: #2E7D53; }
        .text-warning { color: #8A5A00; }
        .text-danger { color: #C0392B; }
        .text-muted { color: #6B7280; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .empty-box {
            border: 1px dashed #DDE1E7;
            background-color: #F7F8FA;
            padding: 14px;
            text-align: center;
            color: #6B7280;
            font-style: italic;
        }
    </style>
</head>
<body>

    <div class="page-footer">
        <table class="footer-table">
            <tr>
                <td><strong>DEX INTERNATIONAL CO.</strong> · Field Manpower &amp; Technical Personnel Roster Report</td>
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
                    <div class="company-sub">Manpower &amp; Technical Expertise Roster Report</div>
                    <div class="company-tagline">Field Engineers, Foremen, Safety Officers, Technicians &amp; Installation Personnel</div>
                </td>
                <td class="meta-cell">
                    <div class="doc-badge">Manpower Roster Report</div>
                    <div class="mono"><strong>Ref No:</strong> {{ $referenceNo }}</div>
                    <div><strong>Prepared By:</strong> {{ $generatedBy }} ({{ $generatedByRole }})</div>
                    <div class="mono"><strong>Generated:</strong> {{ $generatedAt }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="filter-banner">
        <strong>Report Configuration &amp; Filter Scope:</strong> {{ $filterSummary }}
    </div>

    <table class="kpi-table">
        <tr>
            <td class="kpi-card">
                <div class="kpi-label">Total Personnel Exported</div>
                <div class="kpi-val mono text-accent">{{ $summary['total_count'] }} {{ Str::plural('Personnel', $summary['total_count']) }}</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Currently Assigned</div>
                <div class="kpi-val mono text-success">{{ $summary['assigned_count'] }} Deployed</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Available Pool</div>
                <div class="kpi-val mono">{{ $summary['available_count'] }} Ready</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Expertise</div>
                <div class="kpi-val" style="font-size: 9.5pt;">{{ $expertiseFilter ?: 'All Technical Roles' }}</div>
            </td>
        </tr>
    </table>

    @if ($personnel->isEmpty())
        <div class="empty-box">
            No field personnel matched the selected expertise or status filters.
        </div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: {{ $includeRecentProjects ? '4%' : '5%' }};" class="text-center">#</th>
                    <th style="width: {{ $includeRecentProjects ? '12%' : '14%' }};">Employee ID</th>
                    <th style="width: {{ $includeRecentProjects ? '17%' : '24%' }};">Full Name</th>
                    <th style="width: {{ $includeRecentProjects ? '15%' : '20%' }};">Expertise / Role</th>
                    <th style="width: {{ $includeRecentProjects ? '10%' : '12%' }};" class="text-center">Status</th>
                    <th style="width: {{ $includeRecentProjects ? '17%' : '25%' }};">Current Assignment</th>
                    @if ($includeRecentProjects)
                        <th style="width: 25%;">Recent Projects &amp; Assignment History</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($personnel as $index => $person)
                    <tr>
                        <td class="text-center mono text-muted">{{ $index + 1 }}</td>
                        <td class="mono text-accent"><strong>{{ $person['employee_id'] }}</strong></td>
                        <td><strong>{{ $person['full_name'] }}</strong></td>
                        <td>
                            <span class="expertise-badge">{{ $person['expertise'] }}</span>
                        </td>
                        <td class="text-center">
                            @if ($person['status'] === 'Assigned')
                                <span class="status-assigned">Assigned</span>
                            @elseif ($person['status'] === 'Archived')
                                <span class="status-archived">Archived</span>
                            @else
                                <span class="status-available">Available</span>
                            @endif
                        </td>
                        <td>
                            @if ($person['current_project'])
                                <div class="mono text-accent" style="font-weight: bold; font-size: 7.5pt;">{{ $person['current_project']->project_code }}</div>
                                <div><strong>{{ $person['current_project']->name }}</strong></div>
                                <div class="text-muted" style="font-size: 7pt;">Since {{ $person['date_assigned'] }}</div>
                            @else
                                <span class="text-muted">Not currently assigned</span>
                            @endif
                        </td>
                        @if ($includeRecentProjects)
                            <td>
                                @if (empty($person['recent_projects']))
                                    <span class="text-muted" style="font-style: italic; font-size: 7.5pt;">No recent project assignments recorded.</span>
                                @else
                                    <div class="recent-proj-list">
                                        @foreach ($person['recent_projects'] as $rp)
                                            <div class="recent-proj-item">
                                                <div>
                                                    <span class="mono text-accent" style="font-weight: bold;">{{ $rp['project_code'] }}</span>
                                                    — <strong>{{ $rp['project_name'] }}</strong>
                                                    <span class="text-muted">({{ $rp['project_status'] }})</span>
                                                </div>
                                                <div class="mono text-muted" style="font-size: 6.75pt;">
                                                    Assigned: {{ $rp['assigned_at'] }} · {{ $rp['released_at'] }}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

</body>
</html>

