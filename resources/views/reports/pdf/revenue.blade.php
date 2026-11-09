<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Revenue &amp; Financial Performance Report (Without Taxes) — Dex International Co.</title>
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

        .section-header {
            background-color: #EBF3F8;
            border: 1px solid #C8DCE8;
            border-left: 4px solid #2C5F7C;
            padding: 5px 10px;
            margin-bottom: 7px;
            margin-top: 8px;
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
            font-size: 8.5pt;
            font-weight: bold;
            color: #1B1E23;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .data-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            border: 1px solid #DDE1E7;
            margin-bottom: 12px;
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
            padding: 4.5px 5.5px;
            border: 1px solid #DDE1E7;
            font-size: 7.5pt;
            color: #1B1E23;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .data-table tr:nth-child(even) td {
            background-color: #FAFBFD;
        }

        .data-table tfoot td {
            background-color: #EBF3F8;
            font-weight: bold;
            font-size: 7.75pt;
            border-top: 1.5px solid #2C5F7C;
        }

        .bar-track {
            width: 100%;
            height: 6px;
            background-color: #E5E7EB;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 2px;
        }

        .bar-fill-revenue {
            height: 6px;
            background-color: #2C5F7C;
        }

        .bar-fill-spend {
            height: 6px;
            background-color: #D97706;
        }

        .status-completed {
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

        .status-ongoing {
            display: inline-block;
            background-color: #FDF3E0;
            color: #8A5A00;
            border: 1px solid #F5D491;
            padding: 1px 5px;
            border-radius: 8px;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pending {
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

        .status-delayed {
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
            padding: 12px;
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
                <td><strong>DEX INTERNATIONAL CO.</strong> · Revenue &amp; Financial Performance Report (Without Taxes)</td>
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
                    <div class="company-sub">Revenue &amp; Financial Performance Report (Without Taxes)</div>
                    <div class="company-tagline">Contract Price Revenue (Without Taxes), Actual Spends &amp; Remaining Budget Analysis</div>
                </td>
                <td class="meta-cell">
                    <div class="doc-badge">Revenue Report · Without Taxes</div>
                    <div class="mono"><strong>Ref No:</strong> {{ $referenceNo }}</div>
                    <div><strong>Prepared By:</strong> {{ $generatedBy }} ({{ $generatedByRole }})</div>
                    <div class="mono"><strong>Generated:</strong> {{ $generatedAt }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="filter-banner">
        <strong>Report Configuration &amp; Scope:</strong> {{ $filterSummary }}
    </div>

    {{-- Top KPI Cards --}}
    <table class="kpi-table">
        <tr>
            <td class="kpi-card">
                <div class="kpi-label">Total Contract Value</div>
                <div class="kpi-val mono text-success">&#8369;{{ number_format($summary['total_contract_without_taxes'], 2) }}</div>
                <div class="text-muted" style="font-size: 7pt; margin-top: 2px;">Across {{ $summary['projects_count'] }} {{ Str::plural('project', $summary['projects_count']) }}</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Total Actual Spends</div>
                <div class="kpi-val mono text-warning">&#8369;{{ number_format($summary['total_actual_spends'], 2) }}</div>
                <div class="text-muted" style="font-size: 7pt; margin-top: 2px;">Recorded project expenditures</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Total revenue / remaining budget (without tax)</div>
                <div class="kpi-val mono {{ $summary['total_remaining_budget'] >= 0 ? 'text-accent' : 'text-danger' }}">
                    &#8369;{{ number_format($summary['total_remaining_budget'], 2) }}
                </div>
                <div class="text-muted" style="font-size: 7pt; margin-top: 2px;">Net revenue balance (Without Taxes)</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Budget Utilization Rate</div>
                <div class="kpi-val mono {{ $summary['utilization_rate'] > 100 ? 'text-danger' : 'text-accent' }}">{{ number_format($summary['utilization_rate'], 1) }}%</div>
                <div class="text-muted" style="font-size: 7pt; margin-top: 2px;">Mode: {{ ucfirst($comparisonMode) }} Comparison</div>
            </td>
        </tr>
    </table>

    {{-- Section 01: Monthly / Yearly Graphical Plot Chart & Comparison Table --}}
    <div class="section-header">
        <span class="section-number">01</span>
        <span class="section-title">{{ $comparisonMode === 'yearly' ? 'Yearly Revenue Comparison (Multi-Year)' : 'Monthly Revenue Comparison (' . ($yearFilter === 'all' ? 'All Years' : $yearFilter) . ')' }} — Without Taxes</span>
    </div>

    @php
        $chartMax = $maxPeriodValue > 0 ? (float) $maxPeriodValue : 100000.0;
        $bucketCount = max(1, count($periodRows));
        $plotLeft = 58.0;
        $plotRight = 498.0;
        $plotWidth = $plotRight - $plotLeft;
        $plotTop = 16.0;
        $plotBottom = 152.0;
        $plotHeight = $plotBottom - $plotTop;

        $formatShortPhp = function (float $val): string {
            $abs = abs($val);
            if ($abs >= 1000000) {
                return 'PHP ' . number_format($val / 1000000, 1) . 'M';
            }
            if ($abs >= 1000) {
                return 'PHP ' . number_format($val / 1000, 0) . 'K';
            }
            return 'PHP ' . number_format($val, 0);
        };

        $revPoints = [];
        $spendPoints = [];
        $remPoints = [];
        $chartItems = [];

        foreach (array_values($periodRows) as $idx => $r) {
            $cx = round($plotLeft + (($idx + 0.5) / $bucketCount) * $plotWidth, 1);

            $revVal = max(0.0, (float) $r['contract_price']);
            $spendVal = max(0.0, (float) $r['actual_spend']);
            $remVal = max(0.0, (float) $r['remaining_budget']);

            $revRatio = min(1.0, $revVal / $chartMax);
            $spendRatio = min(1.0, $spendVal / $chartMax);
            $remRatio = min(1.0, abs((float) $r['remaining_budget']) / $chartMax);

            $revY = round($plotBottom - ($revRatio * $plotHeight), 1);
            $spendY = round($plotBottom - ($spendRatio * $plotHeight), 1);
            $remY = round($plotBottom - (min(1.0, $remVal / $chartMax) * $plotHeight), 1);

            $revBarH = max(2.0, round($revRatio * $plotHeight, 1));
            $spendBarH = max(2.0, round($spendRatio * $plotHeight, 1));
            $remBarH = max(2.0, round($remRatio * $plotHeight, 1));

            $revPoints[] = "{$cx},{$revY}";
            $spendPoints[] = "{$cx},{$spendY}";
            $remPoints[] = "{$cx},{$remY}";

            $shortLbl = $comparisonMode === 'yearly'
                ? (string) $r['period_label']
                : substr((string) $r['period_label'], 0, 3);

            $chartItems[] = [
                'cx' => $cx,
                'shortLabel' => htmlspecialchars($shortLbl, ENT_QUOTES, 'UTF-8'),
                'revY' => $revY,
                'spendY' => $spendY,
                'remY' => $remY,
                'revBarH' => $revBarH,
                'spendBarH' => $spendBarH,
                'remBarH' => $remBarH,
                'remPositive' => ((float) $r['remaining_budget']) >= 0,
            ];
        }

        $revPolyline = implode(' ', $revPoints);
        $spendPolyline = implode(' ', $spendPoints);
        $remPolyline = implode(' ', $remPoints);

        $svgLines = [];
        $svgLines[] = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 510 175" width="510" height="175">';
        $svgLines[] = '<rect x="0" y="0" width="510" height="175" fill="#FAFBFD" rx="4" />';

        // Grid lines & Y-axis labels
        $gridSteps = [
            ['y' => $plotTop, 'val' => $chartMax],
            ['y' => round($plotTop + $plotHeight * 0.25, 1), 'val' => $chartMax * 0.75],
            ['y' => round($plotTop + $plotHeight * 0.50, 1), 'val' => $chartMax * 0.50],
            ['y' => round($plotTop + $plotHeight * 0.75, 1), 'val' => $chartMax * 0.25],
            ['y' => $plotBottom, 'val' => 0.0],
        ];
        foreach ($gridSteps as $gIdx => $g) {
            $strokeColor = $gIdx === 4 ? '#9CA3AF' : '#DDE1E7';
            $dashAttr = $gIdx === 4 ? '' : ' stroke-dasharray="3,3"';
            $lbl = htmlspecialchars($formatShortPhp((float) $g['val']), ENT_QUOTES, 'UTF-8');
            $svgLines[] = '<line x1="' . $plotLeft . '" y1="' . $g['y'] . '" x2="' . $plotRight . '" y2="' . $g['y'] . '" stroke="' . $strokeColor . '" stroke-width="1"' . $dashAttr . ' />';
            $svgLines[] = '<text x="' . ($plotLeft - 5) . '" y="' . ($g['y'] + 3) . '" text-anchor="end" font-family="DejaVu Sans Mono, monospace" font-size="6.8" fill="#6B7280">' . $lbl . '</text>';
        }

        // 3-Bar Grouped Graph + X-axis labels
        $barWidth = $bucketCount <= 4 ? 11.0 : 6.2;
        $barGap = $bucketCount <= 4 ? 2.5 : 1.4;
        $groupHalfWidth = (($barWidth * 3) + ($barGap * 2)) / 2.0;

        foreach ($chartItems as $item) {
            $b1X = round($item['cx'] - $groupHalfWidth, 1);
            $b1Y = round($plotBottom - $item['revBarH'], 1);
            $b2X = round($b1X + $barWidth + $barGap, 1);
            $b2Y = round($plotBottom - $item['spendBarH'], 1);
            $b3X = round($b2X + $barWidth + $barGap, 1);
            $b3Y = round($plotBottom - $item['remBarH'], 1);
            $remColor = $item['remPositive'] ? '#2563EB' : '#DC2626';

            $svgLines[] = '<rect x="' . $b1X . '" y="' . $b1Y . '" width="' . $barWidth . '" height="' . $item['revBarH'] . '" fill="#10B981" rx="1.2" />';
            $svgLines[] = '<rect x="' . $b2X . '" y="' . $b2Y . '" width="' . $barWidth . '" height="' . $item['spendBarH'] . '" fill="#D97706" rx="1.2" />';
            $svgLines[] = '<rect x="' . $b3X . '" y="' . $b3Y . '" width="' . $barWidth . '" height="' . $item['remBarH'] . '" fill="' . $remColor . '" rx="1.2" />';
            $svgLines[] = '<text x="' . $item['cx'] . '" y="166" text-anchor="middle" font-family="DejaVu Sans Mono, monospace" font-size="7.2" font-weight="bold" fill="#4B5563">' . $item['shortLabel'] . '</text>';
        }

        $svgLines[] = '</svg>';
        $svgChartBase64 = 'data:image/svg+xml;base64,' . base64_encode(implode('', $svgLines));
    @endphp

    <div style="border: 1px solid #DDE1E7; background-color: #FAFBFD; padding: 7px 8px; margin-bottom: 10px;">
        <table style="width: 100%; table-layout: fixed; border-collapse: collapse; margin-bottom: 4px;">
            <tr>
                <td style="width: 42%; font-size: 7.25pt; font-weight: bold; color: #1B1E23;">
                    3-Bar Graphical Comparison (Without Taxes)
                </td>
                <td class="text-right" style="width: 58%; font-size: 6.75pt; color: #4B5563;">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 2px; background-color: #10B981; margin-right: 2px;"></span>
                    <strong>Total contract value</strong>
                    &nbsp;&nbsp;
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 2px; background-color: #D97706; margin-right: 2px;"></span>
                    <strong>Actual spends</strong>
                    &nbsp;&nbsp;
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 2px; background-color: #2563EB; margin-right: 2px;"></span>
                    <strong>Total revenue</strong>
                </td>
            </tr>
        </table>
        <img src="{{ $svgChartBase64 }}" alt="Revenue 3-Bar Chart" style="width: 100%; height: auto; display: block;">
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 15%;">Period</th>
                <th style="width: 8%;" class="text-center">Projects</th>
                <th style="width: 19%;" class="text-right">Total Contract Value (PHP)</th>
                <th style="width: 18%;" class="text-right">Actual Spends (PHP)</th>
                <th style="width: 19%;" class="text-right">Total revenue / remaining budget (without tax)</th>
                <th style="width: 9%;" class="text-right">Util. %</th>
                <th style="width: 12%;">Graphical Proportion</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($periodRows as $row)
                @php
                    $revBarPct = $maxPeriodValue > 0 ? min(100, round(($row['contract_price'] / $maxPeriodValue) * 100)) : 0;
                    $spendBarPct = $maxPeriodValue > 0 ? min(100, round(($row['actual_spend'] / $maxPeriodValue) * 100)) : 0;
                @endphp
                <tr>
                    <td><strong>{{ $row['period_label'] }}</strong></td>
                    <td class="text-center mono">{{ $row['projects_count'] }}</td>
                    <td class="text-right mono text-success"><strong>&#8369;{{ number_format($row['contract_price'], 2) }}</strong></td>
                    <td class="text-right mono">&#8369;{{ number_format($row['actual_spend'], 2) }}</td>
                    <td class="text-right mono {{ $row['remaining_budget'] >= 0 ? 'text-accent' : 'text-danger' }}">
                        <strong>&#8369;{{ number_format($row['remaining_budget'], 2) }}</strong>
                    </td>
                    <td class="text-right mono">{{ number_format($row['utilization'], 1) }}%</td>
                    <td>
                        <div class="bar-track" title="Revenue (Without Taxes)">
                            <div class="bar-fill-revenue" style="width: {{ $revBarPct }}%;"></div>
                        </div>
                        <div class="bar-track" style="margin-bottom: 0;" title="Actual Spends">
                            <div class="bar-fill-spend" style="width: {{ $spendBarPct }}%;"></div>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Total ({{ ucfirst($comparisonMode) }} Scope)</td>
                <td class="text-center mono">{{ $summary['projects_count'] }}</td>
                <td class="text-right mono text-success">&#8369;{{ number_format($summary['total_contract_without_taxes'], 2) }}</td>
                <td class="text-right mono">&#8369;{{ number_format($summary['total_actual_spends'], 2) }}</td>
                <td class="text-right mono {{ $summary['total_remaining_budget'] >= 0 ? 'text-accent' : 'text-danger' }}">
                    &#8369;{{ number_format($summary['total_remaining_budget'], 2) }}
                </td>
                <td class="text-right mono">{{ number_format($summary['utilization_rate'], 1) }}%</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    {{-- Section 02: Project-by-Project Revenue Breakdown --}}
    <div class="section-header">
        <span class="section-number">02</span>
        <span class="section-title">Project-by-Project Revenue &amp; Remaining Budget Ledger (Without Taxes)</span>
    </div>

    @if ($projects->isEmpty())
        <div class="empty-box">
            No projects matched the selected period or status filter.
        </div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 4%;" class="text-center">#</th>
                    <th style="width: 12%;">Project Code</th>
                    <th style="width: 24%;">Project Name &amp; Client</th>
                    <th style="width: 11%;">Project Date</th>
                    <th style="width: 10%;" class="text-center">Status</th>
                    <th style="width: 13%;" class="text-right">Total Contract Value</th>
                    <th style="width: 12%;" class="text-right">Actual Spends</th>
                    <th style="width: 14%;" class="text-right">Total revenue / remaining budget (without tax)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($projects as $idx => $proj)
                    @php
                        $pStatusClass = match ($proj->status) {
                            'completed' => 'status-completed',
                            'ongoing' => 'status-ongoing',
                            'delayed' => 'status-delayed',
                            default => 'status-pending',
                        };
                        $effDate = ($proj->start_date ?? $proj->created_at)?->format('M j, Y') ?? '—';
                    @endphp
                    <tr>
                        <td class="text-center mono text-muted">{{ $idx + 1 }}</td>
                        <td class="mono text-accent"><strong>{{ $proj->project_code }}</strong></td>
                        <td>
                            <strong>{{ $proj->name }}</strong>
                            <div class="text-muted" style="font-size: 7pt;">Client: {{ $proj->client_name }}</div>
                        </td>
                        <td class="mono">{{ $effDate }}</td>
                        <td class="text-center">
                            <span class="{{ $pStatusClass }}">{{ ucfirst($proj->status) }}</span>
                        </td>
                        <td class="text-right mono text-success"><strong>&#8369;{{ number_format((float) $proj->contract_price, 2) }}</strong></td>
                        <td class="text-right mono">&#8369;{{ number_format((float) $proj->actual_spend, 2) }}</td>
                        <td class="text-right mono {{ $proj->remaining_budget >= 0 ? 'text-accent' : 'text-danger' }}">
                            <strong>&#8369;{{ number_format((float) $proj->remaining_budget, 2) }}</strong>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5">Total Portfolio Revenue (Without Taxes), Actual Spends &amp; Remaining Budget</td>
                    <td class="text-right mono text-success">&#8369;{{ number_format($summary['total_contract_without_taxes'], 2) }}</td>
                    <td class="text-right mono">&#8369;{{ number_format($summary['total_actual_spends'], 2) }}</td>
                    <td class="text-right mono {{ $summary['total_remaining_budget'] >= 0 ? 'text-accent' : 'text-danger' }}">
                        &#8369;{{ number_format($summary['total_remaining_budget'], 2) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    @endif

</body>
</html>

