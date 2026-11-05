<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Materials Catalog Report — Dex International Co.</title>
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

        .filter-banner {
            background-color: #EBF3F8;
            border: 1px solid #C8DCE8;
            border-left: 4px solid #2C5F7C;
            padding: 7px 12px;
            margin-bottom: 12px;
            font-size: 8pt;
            color: #1B1E23;
        }

        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .kpi-card {
            width: 20%;
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

        .pill-fast {
            display: inline-block;
            background-color: #EBF5F0;
            color: #2E7D53;
            border: 1px solid #B7DFC7;
            padding: 1.5px 7px;
            border-radius: 10px;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .pill-slow {
            display: inline-block;
            background-color: #FDF3E0;
            color: #8A5A00;
            border: 1px solid #F5D491;
            padding: 1.5px 7px;
            border-radius: 10px;
            font-size: 7pt;
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
            padding: 16px;
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
                <td><strong>DEX INTERNATIONAL CO.</strong> · Materials Catalog &amp; Supply Chain Inventory Report</td>
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
                    <div class="company-sub">Materials Catalog &amp; Component Movement Report</div>
                    <div class="company-tagline">Elevator Parts, Technical Components, Pricing &amp; Demand Classification</div>
                </td>
                <td class="meta-cell">
                    <div class="doc-badge">Materials Inventory Report</div>
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

    {{-- Executive Summary Strip --}}
    <table class="kpi-table">
        <tr>
            <td class="kpi-card">
                <div class="kpi-label">Exported Catalog Items</div>
                <div class="kpi-val mono text-accent">{{ $summary['total_items'] }} {{ Str::plural('Material', $summary['total_items']) }}</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Total Available Stock</div>
                <div class="kpi-val mono">{{ number_format($summary['total_units']) }} Units</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Demand Classification</div>
                <div class="kpi-val mono" style="font-size: 9.5pt;">
                    <span class="text-success">{{ $summary['fast_moving_count'] }} Fast</span> ·
                    <span class="text-warning">{{ $summary['slow_moving_count'] }} Slow</span>
                </div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Total Cost Valuation</div>
                <div class="kpi-val mono">&#8369;{{ number_format($summary['total_cost_value'], 2) }}</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Total Retail Valuation</div>
                <div class="kpi-val mono text-success">&#8369;{{ number_format($summary['total_retail_value'], 2) }}</div>
            </td>
        </tr>
    </table>

    @if ($materials->isEmpty())
        <div class="empty-box">
            No catalog materials matched the selected report filters.
        </div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 4%;" class="text-center">#</th>
                    <th style="width: 11%;">Item Code</th>
                    <th style="width: 24%;">Material Name &amp; Description</th>
                    <th style="width: 18%;">Model / Technical Specs</th>
                    <th style="width: 11%;" class="text-center">Demand Status</th>
                    <th style="width: 7%;" class="text-right">Avail Qty</th>
                    <th style="width: 12%;" class="text-right">Unit Price (PHP)</th>
                    <th style="width: 13%;" class="text-right">Total Value (PHP)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($materials as $index => $material)
                    @php
                        $lineValuation = (int) $material->quantity_available * (float) $material->price;
                        $isFast = ($material->demand_status ?? 'fast-moving') === 'fast-moving';
                    @endphp
                    <tr>
                        <td class="text-center mono text-muted">{{ $index + 1 }}</td>
                        <td class="mono text-accent"><strong>{{ $material->resource_code }}</strong></td>
                        <td>
                            <strong>{{ $material->name }}</strong>
                            @if ($material->description)
                                <div class="text-muted" style="font-size: 7pt; margin-top: 1px;">{{ $material->description }}</div>
                            @endif
                        </td>
                        <td class="mono">{{ $material->model ?: 'Standard' }}</td>
                        <td class="text-center">
                            @if ($isFast)
                                <span class="pill-fast">Fast-Moving</span>
                            @else
                                <span class="pill-slow">Slow-Moving</span>
                            @endif
                        </td>
                        <td class="text-right mono {{ $material->quantity_available < 5 ? 'text-danger' : '' }}">
                            <strong>{{ number_format($material->quantity_available) }}</strong>
                        </td>
                        <td class="text-right mono">&#8369;{{ number_format((float) $material->price, 2) }}</td>
                        <td class="text-right mono text-success"><strong>&#8369;{{ number_format($lineValuation, 2) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5">Cumulative Inventory Totals ({{ $summary['total_items'] }} {{ Str::plural('item', $summary['total_items']) }})</td>
                    <td class="text-right mono">{{ number_format($summary['total_units']) }}</td>
                    <td class="text-right mono">Cost: &#8369;{{ number_format($summary['total_cost_value'], 2) }}</td>
                    <td class="text-right mono">&#8369;{{ number_format($summary['total_retail_value'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

</body>
</html>

