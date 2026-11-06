@extends('layouts.app')

@section('title', 'Reports & Insights')
@section('meta-description', 'Generate downloadable PDF reports for projects, materials catalog movement, and field manpower rosters.')

@php
    $currentYear = (int) now()->year;
    $projectYears = $projects
        ->map(fn ($p) => (int) ($p->start_date ?? $p->created_at)->format('Y'))
        ->unique()
        ->toArray();
    $availableYears = array_values(array_unique(array_merge(range($currentYear - 4, $currentYear), $projectYears)));
    rsort($availableYears);

    $projectsJson = $projects->map(function ($p) {
        $tasks = $p->tasks;
        $totalSub = $tasks->sum(fn ($t) => $t->subtasks->count());
        $doneSub = $tasks->sum(fn ($t) => $t->subtasks->where('is_completed', true)->count());
        $progress = $totalSub > 0 ? (int) round(($doneSub / $totalSub) * 100) : ($p->status === 'completed' ? 100 : 0);
        $effDate = $p->start_date ?? $p->created_at;

        return [
            'id' => $p->id,
            'project_code' => $p->project_code,
            'name' => $p->name,
            'client_name' => $p->client_name,
            'project_type' => $p->project_type ?: 'General',
            'status' => $p->status,
            'payment_status' => $p->payment_status ?: 'Partial',
            'is_archived' => $p->trashed(),
            'contract_price' => (float) $p->contract_price,
            'actual_spend' => (float) $p->actual_spend,
            'remaining_budget' => (float) $p->remaining_budget,
            'spend_percentage' => (float) $p->spend_percentage,
            'rated_capacity' => $p->rated_capacity ?: '—',
            'lifting_speed' => $p->lifting_speed ?: '—',
            'no_of_stops' => $p->no_of_stops ? $p->no_of_stops . ' Stops' : '—',
            'costs_count' => (int) $p->costs_count,
            'manpower_count' => (int) $p->personnel_count,
            'progress' => $progress,
            'year' => (int) $effDate->format('Y'),
            'month' => (int) $effDate->format('n'),
            'report_date' => $effDate->format('M j, Y'),
            'target_date' => $p->target_completion_date ? $p->target_completion_date->format('M j, Y') : 'Not set',
            'pdf_url' => route('reports.project.pdf', $p->id),
            'show_url' => route('projects.show', $p->id),
        ];
    })->values();

    $materialsJson = $materials->map(function ($m) {
        return [
            'id' => $m->id,
            'resource_code' => $m->resource_code,
            'name' => $m->name,
            'model' => $m->model ?: 'Standard',
            'description' => $m->description ?: '',
            'quantity_available' => (int) $m->quantity_available,
            'price' => (float) $m->price,
            'unit_cost' => (float) $m->unit_cost,
            'demand_status' => $m->demand_status ?: 'fast-moving',
            'show_url' => route('resources.show', $m->id),
        ];
    })->values();

    $manpowerJson = $manpower->map(function ($person) use ($recentProjectsMap) {
        $isArchived = $person->trashed();
        $isAssigned = !$isArchived && $person->project_id && $person->project;

        return [
            'id' => $person->id,
            'employee_id' => $person->employee_id,
            'full_name' => $person->full_name,
            'expertise' => $person->expertise,
            'is_archived' => $isArchived,
            'is_assigned' => (bool) $isAssigned,
            'status_label' => $isArchived ? 'Archived' : ($isAssigned ? 'Assigned' : 'Not Assigned'),
            'project_code' => $isAssigned ? $person->project->project_code : null,
            'project_name' => $isAssigned ? $person->project->name : null,
            'date_assigned' => $person->date_assigned ? $person->date_assigned->format('M j, Y') : '—',
            'location' => $person->city ? ($person->city . ($person->province ? ', ' . $person->province : '')) : ($person->full_address ?: '—'),
            'recent_projects' => $recentProjectsMap[$person->id] ?? [],
        ];
    })->values();
@endphp

@section('content')
<div
    class="reports-module"
    x-data="{
        activeTab: 'projects',

        /* ── 1. Project Report State ── */
        projects: @js($projectsJson),
        projectSearch: '',
        projectStatus: '',
        projectScope: 'active',
        quickProjectId: '{{ $projects->first()?->id ?? '' }}',
        sections: {
            overview: true,
            financials: true,
            specs: true,
            expenditures: true,
            manpower: true
        },

        get filteredProjects() {
            const q = this.projectSearch.trim().toLowerCase();
            return this.projects.filter(p => {
                if (this.projectScope === 'active' && p.is_archived) return false;
                if (this.projectScope === 'archived' && !p.is_archived) return false;
                if (this.projectStatus && p.status !== this.projectStatus) return false;
                if (q) {
                    const matchName = p.name.toLowerCase().includes(q);
                    const matchCode = p.project_code.toLowerCase().includes(q);
                    const matchClient = p.client_name.toLowerCase().includes(q);
                    const matchType = p.project_type.toLowerCase().includes(q);
                    if (!matchName && !matchCode && !matchClient && !matchType) return false;
                }
                return true;
            });
        },

        buildProjectPdfUrl(baseUrl, disposition = 'download') {
            const params = new URLSearchParams({
                include_overview: this.sections.overview ? '1' : '0',
                include_financials: this.sections.financials ? '1' : '0',
                include_specs: this.sections.specs ? '1' : '0',
                include_expenditures: this.sections.expenditures ? '1' : '0',
                include_manpower: this.sections.manpower ? '1' : '0',
                disposition: disposition
            });
            return `${baseUrl}?${params.toString()}`;
        },

        exportQuickProject(disposition = 'download') {
            if (!this.quickProjectId) return;
            const found = this.projects.find(p => String(p.id) === String(this.quickProjectId));
            if (!found) return;
            const url = this.buildProjectPdfUrl(found.pdf_url, disposition);
            if (disposition === 'stream') {
                window.open(url, '_blank');
            } else {
                window.location.href = url;
            }
        },

        /* ── 2. Materials Report State ── */
        materials: @js($materialsJson),
        materialSearch: '',
        materialDemandStatus: '',
        materialStockFilter: 'all',
        selectedMaterialIds: [],

        get filteredMaterials() {
            const q = this.materialSearch.trim().toLowerCase();
            return this.materials.filter(m => {
                if (this.materialDemandStatus && m.demand_status !== this.materialDemandStatus) return false;
                if (this.materialStockFilter === 'low_stock' && (m.quantity_available <= 0 || m.quantity_available >= 5)) return false;
                if (this.materialStockFilter === 'out_of_stock' && m.quantity_available > 0) return false;
                if (this.materialStockFilter === 'in_stock' && m.quantity_available <= 0) return false;
                if (q) {
                    const matchCode = m.resource_code.toLowerCase().includes(q);
                    const matchName = m.name.toLowerCase().includes(q);
                    const matchModel = m.model.toLowerCase().includes(q);
                    const matchDesc = m.description.toLowerCase().includes(q);
                    if (!matchCode && !matchName && !matchModel && !matchDesc) return false;
                }
                return true;
            });
        },

        toggleMaterial(id) {
            const idx = this.selectedMaterialIds.indexOf(id);
            if (idx > -1) {
                this.selectedMaterialIds.splice(idx, 1);
            } else {
                this.selectedMaterialIds.push(id);
            }
        },

        isAllMaterialsSelected() {
            const visible = this.filteredMaterials;
            if (visible.length === 0) return false;
            return visible.every(m => this.selectedMaterialIds.includes(m.id));
        },

        toggleSelectAllMaterials(e) {
            const visibleIds = this.filteredMaterials.map(m => m.id);
            if (e.target.checked) {
                visibleIds.forEach(id => {
                    if (!this.selectedMaterialIds.includes(id)) {
                        this.selectedMaterialIds.push(id);
                    }
                });
            } else {
                this.selectedMaterialIds = this.selectedMaterialIds.filter(id => !visibleIds.includes(id));
            }
        },

        submitMaterialsExport(mode, format = 'pdf', disposition = 'download') {
            if (mode === 'selected' && this.selectedMaterialIds.length === 0) return;
            this.$refs.matExportMode.value = mode;
            this.$refs.matExportFormat.value = format;
            this.$refs.matExportDisposition.value = disposition;
            this.$refs.materialsExportForm.target = disposition === 'stream' ? '_blank' : '_self';
            this.$nextTick(() => {
                this.$refs.materialsExportForm.submit();
            });
        },

        /* ── 3. Manpower Report State ── */
        manpower: @js($manpowerJson),
        manpowerSearch: '',
        manpowerExpertise: '',
        manpowerStatus: 'active',
        includeRecentProjects: true,
        selectedPersonnelIds: [],

        get filteredManpower() {
            const q = this.manpowerSearch.trim().toLowerCase();
            return this.manpower.filter(p => {
                if (this.manpowerStatus === 'active' && p.is_archived) return false;
                if (this.manpowerStatus === 'archived' && !p.is_archived) return false;
                if (this.manpowerStatus === 'assigned' && !p.is_assigned) return false;
                if (this.manpowerStatus === 'not_assigned' && (p.is_archived || p.is_assigned)) return false;

                if (this.manpowerExpertise && p.expertise !== this.manpowerExpertise) return false;

                if (q) {
                    const matchId = p.employee_id.toLowerCase().includes(q);
                    const matchName = p.full_name.toLowerCase().includes(q);
                    const matchExp = p.expertise.toLowerCase().includes(q);
                    const matchProj = (p.project_name && p.project_name.toLowerCase().includes(q)) ||
                                      (p.project_code && p.project_code.toLowerCase().includes(q));
                    if (!matchId && !matchName && !matchExp && !matchProj) return false;
                }
                return true;
            });
        },

        togglePersonnel(id) {
            const idx = this.selectedPersonnelIds.indexOf(id);
            if (idx > -1) {
                this.selectedPersonnelIds.splice(idx, 1);
            } else {
                this.selectedPersonnelIds.push(id);
            }
        },

        isAllManpowerSelected() {
            const visible = this.filteredManpower;
            if (visible.length === 0) return false;
            return visible.every(p => this.selectedPersonnelIds.includes(p.id));
        },

        toggleSelectAllManpower(e) {
            const visibleIds = this.filteredManpower.map(p => p.id);
            if (e.target.checked) {
                visibleIds.forEach(id => {
                    if (!this.selectedPersonnelIds.includes(id)) {
                        this.selectedPersonnelIds.push(id);
                    }
                });
            } else {
                this.selectedPersonnelIds = this.selectedPersonnelIds.filter(id => !visibleIds.includes(id));
            }
        },

        submitManpowerExport(mode, format = 'pdf', disposition = 'download') {
            if (mode === 'selected' && this.selectedPersonnelIds.length === 0) return;
            this.$refs.mnpExportMode.value = mode;
            this.$refs.mnpExportFormat.value = format;
            this.$refs.mnpExportDisposition.value = disposition;
            this.$refs.manpowerExportForm.target = disposition === 'stream' ? '_blank' : '_self';
            this.$nextTick(() => {
                this.$refs.manpowerExportForm.submit();
            });
        },

        /* ── 4. Revenue Status & Financial Comparison State (Without Taxes) ── */
        availableYears: @js($availableYears),
        revenueMode: 'monthly',
        revenueYear: '{{ $currentYear }}',
        revenueStatus: '',
        revenueScope: 'active',

        get revenueFilteredProjects() {
            return this.projects.filter(p => {
                if (this.revenueScope === 'active' && p.is_archived) return false;
                if (this.revenueScope === 'archived' && !p.is_archived) return false;
                if (this.revenueStatus && p.status !== this.revenueStatus) return false;
                if (this.revenueMode === 'monthly' && this.revenueYear !== 'all') {
                    if (Number(p.year) !== Number(this.revenueYear)) return false;
                }
                return true;
            });
        },

        get revenueChartBuckets() {
            const baseProjects = this.projects.filter(p => {
                if (this.revenueScope === 'active' && p.is_archived) return false;
                if (this.revenueScope === 'archived' && !p.is_archived) return false;
                if (this.revenueStatus && p.status !== this.revenueStatus) return false;
                return true;
            });

            if (this.revenueMode === 'yearly') {
                const yearsAsc = [...this.availableYears].sort((a, b) => a - b);
                return yearsAsc.map(yr => {
                    const yrItems = baseProjects.filter(p => Number(p.year) === Number(yr));
                    const contract = yrItems.reduce((sum, p) => sum + Number(p.contract_price || 0), 0);
                    const spend = yrItems.reduce((sum, p) => sum + Number(p.actual_spend || 0), 0);
                    const remaining = contract - spend;
                    const util = contract > 0 ? Math.round((spend / contract) * 1000) / 10 : 0;
                    return {
                        key: String(yr),
                        shortLabel: String(yr),
                        fullLabel: `Year ${yr}`,
                        count: yrItems.length,
                        contract_price: contract,
                        actual_spend: spend,
                        remaining_budget: remaining,
                        utilization: util
                    };
                });
            }

            const monthNames = [
                { num: 1, short: 'Jan', full: 'January' },
                { num: 2, short: 'Feb', full: 'February' },
                { num: 3, short: 'Mar', full: 'March' },
                { num: 4, short: 'Apr', full: 'April' },
                { num: 5, short: 'May', full: 'May' },
                { num: 6, short: 'Jun', full: 'June' },
                { num: 7, short: 'Jul', full: 'July' },
                { num: 8, short: 'Aug', full: 'August' },
                { num: 9, short: 'Sep', full: 'September' },
                { num: 10, short: 'Oct', full: 'October' },
                { num: 11, short: 'Nov', full: 'November' },
                { num: 12, short: 'Dec', full: 'December' }
            ];

            return monthNames.map(m => {
                const mItems = baseProjects.filter(p => {
                    if (this.revenueYear !== 'all' && Number(p.year) !== Number(this.revenueYear)) return false;
                    return Number(p.month) === m.num;
                });
                const contract = mItems.reduce((sum, p) => sum + Number(p.contract_price || 0), 0);
                const spend = mItems.reduce((sum, p) => sum + Number(p.actual_spend || 0), 0);
                const remaining = contract - spend;
                const util = contract > 0 ? Math.round((spend / contract) * 1000) / 10 : 0;
                return {
                    key: `m-${m.num}`,
                    shortLabel: m.short,
                    fullLabel: this.revenueYear === 'all' ? m.full : `${m.full} ${this.revenueYear}`,
                    count: mItems.length,
                    contract_price: contract,
                    actual_spend: spend,
                    remaining_budget: remaining,
                    utilization: util
                };
            });
        },

        get revenueChartMax() {
            const buckets = this.revenueChartBuckets;
            let maxVal = 0;
            buckets.forEach(b => {
                maxVal = Math.max(maxVal, b.contract_price, b.actual_spend, Math.abs(b.remaining_budget));
            });
            return maxVal > 0 ? maxVal : 100000;
        },

        get revenueSummary() {
            const list = this.revenueFilteredProjects;
            const contract = list.reduce((s, p) => s + Number(p.contract_price || 0), 0);
            const spend = list.reduce((s, p) => s + Number(p.actual_spend || 0), 0);
            const remaining = contract - spend;
            const util = contract > 0 ? Math.round((spend / contract) * 1000) / 10 : 0;

            // Calculate comparison against previous period (previous year or previous active month)
            const baseProjects = this.projects.filter(p => {
                if (this.revenueScope === 'active' && p.is_archived) return false;
                if (this.revenueScope === 'archived' && !p.is_archived) return false;
                if (this.revenueStatus && p.status !== this.revenueStatus) return false;
                return true;
            });

            let compTitle = 'vs. Previous Year';
            let compDelta = 0;
            let compPct = null;
            let prevTotal = 0;

            if (this.revenueMode === 'monthly' && this.revenueYear !== 'all') {
                const prevYr = Number(this.revenueYear) - 1;
                compTitle = `vs. Year ${prevYr}`;
                prevTotal = baseProjects
                    .filter(p => Number(p.year) === prevYr)
                    .reduce((s, p) => s + Number(p.remaining_budget ?? (Number(p.contract_price || 0) - Number(p.actual_spend || 0))), 0);
                compDelta = remaining - prevTotal;
                if (prevTotal > 0) {
                    compPct = Math.round(((remaining - prevTotal) / prevTotal) * 1000) / 10;
                }
            } else {
                const curYr = Number(this.availableYears[0] || new Date().getFullYear());
                const prevYr = curYr - 1;
                compTitle = `${curYr} vs. ${prevYr}`;
                const curYrTotal = baseProjects
                    .filter(p => Number(p.year) === curYr)
                    .reduce((s, p) => s + Number(p.remaining_budget ?? (Number(p.contract_price || 0) - Number(p.actual_spend || 0))), 0);
                prevTotal = baseProjects
                    .filter(p => Number(p.year) === prevYr)
                    .reduce((s, p) => s + Number(p.remaining_budget ?? (Number(p.contract_price || 0) - Number(p.actual_spend || 0))), 0);
                compDelta = curYrTotal - prevTotal;
                if (prevTotal > 0) {
                    compPct = Math.round(((curYrTotal - prevTotal) / prevTotal) * 1000) / 10;
                }
            }

            return {
                count: list.length,
                contract_price: contract,
                actual_spend: spend,
                remaining_budget: remaining,
                utilization: util,
                compTitle: compTitle,
                compDelta: compDelta,
                compPct: compPct,
                prevTotal: prevTotal
            };
        },

        submitRevenueExport(format = 'pdf', disposition = 'download') {
            this.$refs.revExportFormat.value = format;
            this.$refs.revExportDisposition.value = disposition;
            this.$refs.revenueExportForm.target = disposition === 'stream' ? '_blank' : '_self';
            this.$nextTick(() => {
                this.$refs.revenueExportForm.submit();
            });
        },

        formatCurrency(val) {
            return '₱' + Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        formatCompactCurrency(val) {
            const n = Number(val || 0);
            const abs = Math.abs(n);
            const sign = n < 0 ? '-' : '';
            if (abs >= 1000000) {
                return sign + '₱' + (abs / 1000000).toFixed(1) + 'M';
            }
            if (abs >= 1000) {
                return sign + '₱' + (abs / 1000).toFixed(0) + 'K';
            }
            return sign + '₱' + abs.toFixed(0);
        },

        selectedChartBucket: null,

        chartX(idx) {
            const count = Math.max(1, this.revenueChartBuckets.length);
            return Math.round((66 + ((idx + 0.5) / count) * 678) * 10) / 10;
        },

        chartY(val) {
            const max = this.revenueChartMax;
            const clamped = Math.max(0, Math.min(max, Number(val || 0)));
            const ratio = max > 0 ? clamped / max : 0;
            return Math.round((194 - ratio * 176) * 10) / 10;
        },

        chartBarHeight(val) {
            const max = this.revenueChartMax;
            const clamped = Math.max(0, Math.min(max, Math.abs(Number(val || 0))));
            const ratio = max > 0 ? clamped / max : 0;
            return Math.max(2, Math.round(ratio * 176 * 10) / 10);
        },

        revenuePolyline(field) {
            return this.revenueChartBuckets
                .map((b, idx) => `${this.chartX(idx)},${this.chartY(Math.max(0, Number(b[field] || 0)))}`)
                .join(' ');
        }
    }"
>
    <style>
        .reports-module .mb-6 {
            margin-bottom: 1.5rem;
        }
        .reports-module .mb-4 {
            margin-bottom: 1rem;
        }
        .reports-module .reports-tabs-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: var(--space-5);
            padding-bottom: var(--space-3);
            border-bottom: 1px solid var(--color-border);
        }
        .reports-module .reports-tabs-list {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            background: transparent;
            padding: 0;
            border: none;
        }
        .reports-module .reports-tab-btn {
            height: 38px;
            padding: 0 1rem;
            font-size: 0.8125rem;
            font-weight: 600;
            display: inline-flex !important;
            align-items: center;
            gap: 0.45rem;
            border-radius: var(--radius-md);
            white-space: nowrap;
            width: auto !important;
        }
        .reports-module .reports-toolbar-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .reports-module .reports-filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .reports-module .reports-search-box {
            position: relative;
            width: 240px;
            max-width: 100%;
            flex: 0 1 240px;
        }
        .reports-module .reports-search-box .search-input-icon {
            position: absolute;
            left: 0.65rem;
            top: 50%;
            transform: translateY(-50%);
            width: 14px;
            height: 14px;
            color: var(--color-muted);
            pointer-events: none;
        }
        .reports-module .reports-search-box input.form-control {
            width: 100% !important;
            height: 36px;
            padding: 0 0.7rem 0 2rem;
            font-size: 0.8125rem;
            border-radius: var(--radius-md);
        }
        .reports-module select.reports-select {
            width: auto !important;
            min-width: 140px;
            max-width: 215px;
            height: 36px;
            padding: 0 1.85rem 0 0.7rem;
            font-size: 0.8125rem;
            line-height: 1.2;
            display: inline-block !important;
            flex: 0 0 auto;
        }
        .reports-module .reports-btn {
            height: 36px;
            padding: 0 0.85rem;
            font-size: 0.8125rem;
            font-weight: 600;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            white-space: nowrap;
            flex: 0 0 auto;
            width: auto !important;
        }
        .reports-module .rev-chart-bars-grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(42px, 1fr));
            gap: 8px;
            align-items: end;
            height: 186px;
        }
        .reports-module .rev-chart-bucket {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            height: 186px;
            cursor: pointer;
            padding: 0 2px;
            border-radius: 4px;
            transition: background-color 0.15s ease;
        }
        .reports-module .rev-chart-bucket:hover {
            background-color: rgba(37, 99, 235, 0.05);
        }
        .reports-module .rev-chart-bar-triplet {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 3px;
            width: 100%;
            height: 180px;
        }
        .reports-module .rev-bar {
            flex: 1 1 0%;
            min-width: 7px;
            max-width: 16px;
            border-radius: 3px 3px 0 0;
            transition: height 0.25s ease, opacity 0.2s ease;
        }
        .reports-module .rev-bar--contract {
            background: linear-gradient(180deg, #10B981 0%, #059669 100%);
        }
        .reports-module .rev-bar--spend {
            background: linear-gradient(180deg, #F59E0B 0%, #D97706 100%);
        }
        .reports-module .rev-bar--revenue {
            background: linear-gradient(180deg, #60A5FA 0%, #2563EB 100%);
        }
        .reports-module .rev-bar--deficit {
            background: linear-gradient(180deg, #F87171 0%, #DC2626 100%);
        }
    </style>

    {{-- Page Header --}}
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
        <div>
            <h1>Reports &amp; Insights</h1>
            <p class="page-header__description">
                Compile and download official PDF reports for projects information, materials movement inventories, and field manpower rosters.
            </p>
        </div>
    </div>

    {{-- Organizational KPI Strip (Dashboard Status Card Design Layout) --}}
    <div class="costing-kpi-grid dashboard-stats-grid mb-6">
        {{-- Card 1: Projects Register --}}
        <div
            class="costing-kpi-card costing-kpi-card--interactive costing-kpi-card--contract"
            role="button"
            tabindex="0"
            @click="activeTab = 'projects'"
            @keydown.enter.prevent="activeTab = 'projects'"
            @keydown.space.prevent="activeTab = 'projects'"
            title="Switch to Project Dossier Reports"
        >
            {{-- Background Watermark Icon --}}
            <svg class="costing-kpi-card__watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>

            <div>
                <div class="costing-kpi-card__header">
                    <span class="costing-kpi-card__label">Projects Register</span>
                    <span class="costing-kpi-card__icon-badge costing-kpi-card__icon-badge--contract" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </span>
                </div>

                <div class="costing-kpi-card__body">
                    <div class="costing-kpi-card__value numeric">{{ $metrics['total_projects'] }}</div>
                    <p class="costing-kpi-card__description">
                        {{ $metrics['active_projects'] }} ongoing, {{ $metrics['completed_projects'] }} completed, and {{ $metrics['pending_projects'] }} pending projects.
                    </p>
                </div>
            </div>

            <div class="costing-kpi-card__footer">
                <span class="costing-kpi-card__tag">
                    <span class="costing-kpi-card__tag-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                    </span>
                    {{ $metrics['active_projects'] }} Ongoing
                </span>
                <span class="costing-kpi-card__pill costing-kpi-card__pill--neutral">
                    <span>Dossiers</span>
                    <span class="costing-kpi-card__arrow" aria-hidden="true">&rarr;</span>
                </span>
            </div>
        </div>

        {{-- Card 2: Contract Portfolio (Without Taxes) --}}
        <div
            class="costing-kpi-card costing-kpi-card--interactive costing-kpi-card--completed"
            role="button"
            tabindex="0"
            @click="activeTab = 'revenue'; document.getElementById('revenue-chart-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
            @keydown.enter.prevent="activeTab = 'revenue'"
            @keydown.space.prevent="activeTab = 'revenue'"
            title="View Revenue Status & Export Revenue Data (Without Taxes)"
        >
            {{-- Background Watermark Icon --}}
            <svg class="costing-kpi-card__watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>

            <div>
                <div class="costing-kpi-card__header">
                    <span class="costing-kpi-card__label">Contract Portfolio</span>
                    <span class="costing-kpi-card__icon-badge costing-kpi-card__icon-badge--completed" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </span>
                </div>

                <div class="costing-kpi-card__body">
                    <div
                        class="costing-kpi-card__value numeric text-completed"
                        style="font-size: clamp(1.2rem, 1.55vw, 1.6rem); word-break: break-word;"
                        title="₱{{ number_format($metrics['total_revenue'], 2) }}"
                    >
                        ₱{{ number_format($metrics['total_revenue'], 2) }}
                    </div>
                    <p class="costing-kpi-card__description">
                        Spend: <span class="font-mono">₱{{ number_format($metrics['total_expenses'], 2) }}</span> · Rem: <span class="font-mono">₱{{ number_format($metrics['remaining_budget'], 2) }}</span> <strong>(Without Taxes)</strong>
                    </p>
                </div>
            </div>

            <div class="costing-kpi-card__footer">
                <span class="costing-kpi-card__tag">
                    <span class="costing-kpi-card__tag-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </span>
                    Without Taxes
                </span>
                <span class="costing-kpi-card__pill costing-kpi-card__pill--completed">
                    <span class="status-pill status-pill--completed">{{ $metrics['budget_utilization'] }}% Used</span>
                    <span class="costing-kpi-card__arrow" aria-hidden="true">&rarr;</span>
                </span>
            </div>
        </div>

        {{-- Card 3: Materials Catalog --}}
        <div
            class="costing-kpi-card costing-kpi-card--interactive costing-kpi-card--pending"
            role="button"
            tabindex="0"
            @click="activeTab = 'materials'"
            @keydown.enter.prevent="activeTab = 'materials'"
            @keydown.space.prevent="activeTab = 'materials'"
            title="Switch to Materials Movement List"
        >
            {{-- Background Watermark Icon --}}
            <svg class="costing-kpi-card__watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                <line x1="12" y1="22.08" x2="12" y2="12"></line>
            </svg>

            <div>
                <div class="costing-kpi-card__header">
                    <span class="costing-kpi-card__label">Materials Catalog</span>
                    <span class="costing-kpi-card__icon-badge costing-kpi-card__icon-badge--pending" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </span>
                </div>

                <div class="costing-kpi-card__body">
                    <div class="costing-kpi-card__value numeric text-pending">{{ $metrics['total_materials'] }}</div>
                    <p class="costing-kpi-card__description">
                        {{ number_format($metrics['total_material_units']) }} units in stock ({{ $metrics['fast_moving_materials'] }} fast-moving, {{ $metrics['slow_moving_materials'] }} slow-moving).
                    </p>
                </div>
            </div>

            <div class="costing-kpi-card__footer">
                <span class="costing-kpi-card__tag">
                    <span class="costing-kpi-card__tag-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                            <polyline points="17 6 23 6 23 12"></polyline>
                        </svg>
                    </span>
                    {{ $metrics['fast_moving_materials'] }} Fast-Moving
                </span>
                <span class="costing-kpi-card__pill costing-kpi-card__pill--pending">
                    <span class="status-pill status-pill--pending">Catalog</span>
                    <span class="costing-kpi-card__arrow" aria-hidden="true">&rarr;</span>
                </span>
            </div>
        </div>

        {{-- Card 4: Field Manpower --}}
        <div
            class="costing-kpi-card costing-kpi-card--interactive costing-kpi-card--ongoing"
            role="button"
            tabindex="0"
            @click="activeTab = 'manpower'"
            @keydown.enter.prevent="activeTab = 'manpower'"
            @keydown.space.prevent="activeTab = 'manpower'"
            title="Switch to Manpower Roster Reports"
        >
            {{-- Background Watermark Icon --}}
            <svg class="costing-kpi-card__watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>

            <div>
                <div class="costing-kpi-card__header">
                    <span class="costing-kpi-card__label">Field Manpower</span>
                    <span class="costing-kpi-card__icon-badge costing-kpi-card__icon-badge--ongoing" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </span>
                </div>

                <div class="costing-kpi-card__body">
                    <div class="costing-kpi-card__value numeric text-ongoing">{{ $metrics['active_personnel'] }}</div>
                    <p class="costing-kpi-card__description">
                        {{ $metrics['assigned_personnel'] }} currently deployed on site and {{ $metrics['available_personnel'] }} available in pool.
                    </p>
                </div>
            </div>

            <div class="costing-kpi-card__footer">
                <span class="costing-kpi-card__tag">
                    <span class="costing-kpi-card__tag-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                    </span>
                    Roster Deployment
                </span>
                <span class="costing-kpi-card__pill costing-kpi-card__pill--ongoing">
                    <span class="status-pill status-pill--ongoing">{{ $metrics['manpower_utilization'] }}% Deployed</span>
                    <span class="costing-kpi-card__arrow" aria-hidden="true">&rarr;</span>
                </span>
            </div>
        </div>
    </div>

    {{-- Report Module Switcher Tabs (Clean Web Tabs without Background Grouping Rectangle) --}}
    <div class="reports-tabs-bar">
        <div class="reports-tabs-list" role="tablist" aria-label="Report categories">
            <button
                type="button"
                role="tab"
                :aria-selected="(activeTab === 'projects').toString()"
                class="btn btn-sm reports-tab-btn"
                :class="activeTab === 'projects' ? 'btn-primary' : 'btn-secondary'"
                @click="activeTab = 'projects'"
                id="tab-btn-projects"
            >
                <svg style="width: 15px; height: 15px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M6 2a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7l-5-5H6zm5 1v4h4l-4-4z" clip-rule="evenodd"/>
                </svg>
                <span>Project Data Export</span>
                <span class="badge font-mono" style="background: rgba(255,255,255,0.2); color: inherit; font-size: 0.7rem;" x-text="projects.length"></span>
            </button>

            <button
                type="button"
                role="tab"
                :aria-selected="(activeTab === 'revenue').toString()"
                class="btn btn-sm reports-tab-btn"
                :class="activeTab === 'revenue' ? 'btn-primary' : 'btn-secondary'"
                @click="activeTab = 'revenue'"
                id="tab-btn-revenue"
            >
                <svg style="width: 15px; height: 15px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                </svg>
                <span>Revenue Data Export</span>
            </button>

            <button
                type="button"
                role="tab"
                :aria-selected="(activeTab === 'materials').toString()"
                class="btn btn-sm reports-tab-btn"
                :class="activeTab === 'materials' ? 'btn-primary' : 'btn-secondary'"
                @click="activeTab = 'materials'"
                id="tab-btn-materials"
            >
                <svg style="width: 15px; height: 15px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M4 3a2 2 0 1 0 0 4h12a2 2 0 1 0 0-4H4zm0 6a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2H4zm2 3h8v3H6v-3z"/>
                </svg>
                <span>Materials List Export</span>
                <span class="badge font-mono" style="background: rgba(255,255,255,0.2); color: inherit; font-size: 0.7rem;" x-text="materials.length"></span>
            </button>

            <button
                type="button"
                role="tab"
                :aria-selected="(activeTab === 'manpower').toString()"
                class="btn btn-sm reports-tab-btn"
                :class="activeTab === 'manpower' ? 'btn-primary' : 'btn-secondary'"
                @click="activeTab = 'manpower'"
                id="tab-btn-manpower"
            >
                <svg style="width: 15px; height: 15px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M9 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM17 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 0 0-1.5-4.33A5 5 0 0 1 19 16v1h-6.07zM6 11a5 5 0 0 1 5 5v1H1v-1a5 5 0 0 1 5-5z"/>
                </svg>
                <span>Manpower List Export</span>
                <span class="badge font-mono" style="background: rgba(255,255,255,0.2); color: inherit; font-size: 0.7rem;" x-text="manpower.length"></span>
            </button>
        </div>

        <span class="badge badge-accent font-mono" style="font-size: 0.75rem;">A4 PORTRAIT</span>
    </div>

    {{-- =========================================================================
         TAB 1: PROJECT DATA EXPORT
         ========================================================================= --}}
    <div x-show="activeTab === 'projects'" x-transition.opacity id="panel-projects-report">

        {{-- Project PDF Configuration & Quick Export Card --}}
        <div class="card card-glass mb-6">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-2);">
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <span style="font-weight: 700;">Customize project data template</span>
                </div>
            </div>
            <div class="card-body" style="padding: var(--space-4) var(--space-6);">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--space-6); align-items: center;">
                    {{-- Left: Hierarchical Order Checkboxes (1. Overview, 2. Elevator Tech Specs, 3. Financials & Budget, 4. Expenditures, 5. Assigned Manpower) --}}
                    <div>
                        <p class="text-muted" style="font-size: 0.8125rem; margin-bottom: var(--space-3);">
                            Exported project PDFs are structured chronologically and hierarchically by importance level. Toggle sections to include in your download:
                        </p>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <label style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.75rem; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.8125rem; cursor: pointer;">
                                <input type="checkbox" class="form-check-input" x-model="sections.overview">
                                <span class="font-mono text-muted" style="font-size: 0.7rem; font-weight: 700;">01</span>
                                <strong>Project Overview</strong>
                            </label>
                            <label style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.75rem; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.8125rem; cursor: pointer;">
                                <input type="checkbox" class="form-check-input" x-model="sections.specs">
                                <span class="font-mono text-muted" style="font-size: 0.7rem; font-weight: 700;">02</span>
                                <strong>Elevator Technical Specs</strong>
                            </label>
                            <label style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.75rem; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.8125rem; cursor: pointer;">
                                <input type="checkbox" class="form-check-input" x-model="sections.financials">
                                <span class="font-mono text-muted" style="font-size: 0.7rem; font-weight: 700;">03</span>
                                <strong>Financials &amp; Budget</strong>
                            </label>
                            <label style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.75rem; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.8125rem; cursor: pointer;">
                                <input type="checkbox" class="form-check-input" x-model="sections.expenditures">
                                <span class="font-mono text-muted" style="font-size: 0.7rem; font-weight: 700;">04</span>
                                <strong>Expenditures</strong>
                            </label>
                            <label style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.75rem; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.8125rem; cursor: pointer;">
                                <input type="checkbox" class="form-check-input" x-model="sections.manpower">
                                <span class="font-mono text-muted" style="font-size: 0.7rem; font-weight: 700;">05</span>
                                <strong>Assigned Manpower</strong>
                            </label>
                        </div>
                    </div>

                    {{-- Right: Direct Quick-Select Exporter --}}
                    <div style="background: var(--color-surface-alt); padding: var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                        <label for="quick-project-select" class="form-label" style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em; margin-bottom: 6px;">
                            Generate customized project data template
                        </label>
                        <div class="reports-filter-group">
                            <select id="quick-project-select" x-model="quickProjectId" class="form-control form-control--compact reports-select" style="flex: 1 1 180px; max-width: 100%;">
                                <option value="">-- Select a Project to Export --</option>
                                @foreach ($projects as $proj)
                                    <option value="{{ $proj->id }}">
                                        {{ $proj->project_code }} — {{ $proj->name }} ({{ ucfirst($proj->status) }})
                                    </option>
                                @endforeach
                            </select>
                            <button
                                type="button"
                                class="btn btn-primary btn-sm reports-btn"
                                :disabled="!quickProjectId"
                                @click="exportQuickProject('download')"
                                id="btn-quick-export-project-pdf"
                            >
                                <svg style="width: 14px; height: 14px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M3 17a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1zm3.293-7.707a1 1 0 0 1 1.414 0L9 10.586V3a1 1 0 1 1 2 0v7.586l1.293-1.293a1 1 0 1 1 1.414 1.414l-3 3a1 1 0 0 1-1.414 0l-3-3a1 1 0 0 1 0-1.414z" clip-rule="evenodd"/>
                                </svg>
                                Download PDF
                            </button>
                            <button
                                type="button"
                                class="btn btn-secondary btn-sm reports-btn"
                                :disabled="!quickProjectId"
                                @click="exportQuickProject('stream')"
                                title="Open PDF preview in browser"
                            >
                                Preview
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Search & Filter Bar for Projects --}}
        <div class="card card-glass mb-4">
            <div class="card-body" style="padding: var(--space-3) var(--space-5);">
                <div class="reports-toolbar-row">
                    <div class="reports-filter-group">
                        <div class="reports-search-box">
                            <svg class="search-input-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                            </svg>
                            <input
                                type="text"
                                id="report-project-search"
                                x-model="projectSearch"
                                placeholder="Search project..."
                                class="form-control"
                                aria-label="Search projects for export"
                            >
                        </div>

                        <label for="report-project-scope" class="visually-hidden">Project Repository Scope</label>
                        <select id="report-project-scope" x-model="projectScope" class="form-control form-control--compact filter-select reports-select">
                            <option value="active">Active Projects</option>
                            <option value="archived">Archived Projects</option>
                            <option value="all">All Projects</option>
                        </select>

                        <label for="report-project-status" class="visually-hidden">Filter by Project Status</label>
                        <select id="report-project-status" x-model="projectStatus" class="form-control form-control--compact filter-select reports-select">
                            <option value="">All Statuses</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="pending">Pending</option>
                            <option value="delayed">Delayed</option>
                            <option value="completed">Completed</option>
                        </select>

                        <button
                            type="button"
                            class="btn btn-secondary btn-sm reports-btn"
                            x-show="projectSearch !== '' || projectStatus !== '' || projectScope !== 'active'"
                            x-cloak
                            @click="projectSearch = ''; projectStatus = ''; projectScope = 'active';"
                            style="color: var(--color-danger);"
                        >
                            ✕ Reset
                        </button>
                    </div>

                    <div class="text-muted font-mono" style="font-size: 0.75rem;">
                        Showing <strong x-text="filteredProjects.length"></strong> of <strong x-text="projects.length"></strong> projects
                    </div>
                </div>
            </div>
        </div>

        {{-- Projects Export Table --}}
        <div class="card card-glass">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-2);">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-weight: 700;">Select Project to Export as PDF</span>
                    <span class="badge badge-accent font-mono" style="font-size: 0.75rem;" x-text="`${filteredProjects.length} project(s)`"></span>
                </div>
                <span class="text-muted" style="font-size: 0.8125rem;">
                    Exports include DEX Logo, Overview, Elevator Technical Specs, Financials &amp; Budget, Expenditures &amp; Assigned Manpower
                </span>
            </div>

            <div class="card-body" x-show="filteredProjects.length === 0" x-cloak>
                <div class="empty-state">
                    <h2 class="empty-state__title">No matching projects found</h2>
                    <p class="empty-state__description">Adjust your search or status filter to locate the project you wish to export.</p>
                    <button type="button" class="btn btn-secondary btn-sm reports-btn" @click="projectSearch = ''; projectStatus = ''; projectScope = 'all';" style="margin-top: 0.5rem;">
                        Show All Projects
                    </button>
                </div>
            </div>

            <div class="table-wrapper" x-show="filteredProjects.length > 0">
                <table class="data-table" id="reports-projects-table">
                    <caption class="visually-hidden">Projects available for PDF export</caption>
                    <thead>
                        <tr>
                            <th scope="col">Project Code &amp; Name</th>
                            <th scope="col">Client &amp; Type</th>
                            <th scope="col">Elevator Tech Specs</th>
                            <th scope="col" class="text-right">Financials (Contract / Spend)</th>
                            <th scope="col" class="text-center">Records</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-right" style="min-width: 190px;">Export Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="proj in filteredProjects" :key="proj.id">
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.4rem;">
                                        <a :href="proj.show_url" class="font-mono font-bold" style="color: var(--color-accent); text-decoration: none;" x-text="proj.project_code"></a>
                                        <template x-if="proj.is_archived">
                                            <span class="badge" style="background: var(--color-danger-light); color: var(--color-danger); font-size: 0.65rem;">Archived</span>
                                        </template>
                                    </div>
                                    <div style="font-weight: 600; color: var(--color-ink);" x-text="proj.name"></div>
                                    <div class="text-muted font-mono" style="font-size: 0.72rem;" x-text="`Target: ${proj.target_date}`"></div>
                                </td>
                                <td>
                                    <div style="font-weight: 500;" x-text="proj.client_name"></div>
                                    <span class="badge" style="background: var(--color-surface-alt); border: 1px solid var(--color-border); font-size: 0.7rem;" x-text="proj.project_type"></span>
                                </td>
                                <td>
                                    <div class="font-mono" style="font-size: 0.78rem; color: var(--color-ink);">
                                        <span x-text="proj.rated_capacity"></span> · <span x-text="proj.lifting_speed"></span>
                                    </div>
                                    <div class="text-muted font-mono" style="font-size: 0.72rem;" x-text="proj.no_of_stops"></div>
                                </td>
                                <td class="text-right font-mono">
                                    <div class="text-success font-bold" x-text="formatCurrency(proj.contract_price)" title="Contract Price"></div>
                                    <div class="text-muted" style="font-size: 0.75rem;" x-text="`Spend: ${formatCurrency(proj.actual_spend)} (${proj.spend_percentage}%)`"></div>
                                </td>
                                <td class="text-center font-mono" style="font-size: 0.75rem;">
                                    <div><strong x-text="proj.costs_count"></strong> expenses</div>
                                    <div class="text-muted"><strong x-text="proj.manpower_count"></strong> personnel</div>
                                </td>
                                <td>
                                    <span class="status-pill" :class="`status-pill--${proj.status}`" x-text="proj.status.charAt(0).toUpperCase() + proj.status.slice(1)"></span>
                                </td>
                                <td class="text-right">
                                    <div style="display: inline-flex; gap: 0.35rem; align-items: center; justify-content: flex-end;">
                                        <a
                                            :href="buildProjectPdfUrl(proj.pdf_url, 'stream')"
                                            target="_blank"
                                            class="btn btn-secondary btn-sm reports-btn"
                                            title="Preview Project PDF in browser"
                                        >
                                            Preview
                                        </a>
                                        <a
                                            :href="buildProjectPdfUrl(proj.pdf_url, 'download')"
                                            class="btn btn-primary btn-sm reports-btn"
                                            title="Download Official Project PDF Report"
                                        >
                                            <svg style="width: 14px; height: 14px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M3 17a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1zm3.293-7.707a1 1 0 0 1 1.414 0L9 10.586V3a1 1 0 1 1 2 0v7.586l1.293-1.293a1 1 0 1 1 1.414 1.414l-3 3a1 1 0 0 1-1.414 0l-3-3a1 1 0 0 1 0-1.414z" clip-rule="evenodd"/>
                                            </svg>
                                            Export PDF
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- =========================================================================
         TAB 2: REVENUE DATA EXPORT (WITHOUT TAXES)
         ========================================================================= --}}
    <div x-show="activeTab === 'revenue'" x-cloak x-transition.opacity id="panel-revenue-report">

        {{-- Revenue Status & Financial Performance Graphical Section (Without Taxes) --}}
        <div class="card card-glass mb-6" id="revenue-chart-section" style="margin-bottom: 1.5rem;">
            {{-- Hidden Form for Submitting Revenue Export --}}
            <form x-ref="revenueExportForm" method="POST" action="{{ route('reports.revenue.export') }}" style="display: none;">
                @csrf
                <input type="hidden" name="comparison_mode" :value="revenueMode">
                <input type="hidden" name="year" :value="revenueYear">
                <input type="hidden" name="status" :value="revenueStatus">
                <input type="hidden" name="scope" :value="revenueScope">
                <input type="hidden" name="format" x-ref="revExportFormat" value="pdf">
                <input type="hidden" name="disposition" x-ref="revExportDisposition" value="download">
            </form>

            <div class="card-header" style="display: flex; flex-direction: column; align-items: stretch; gap: var(--space-3);">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <h2 class="card-header__title" style="margin: 0; font-size: 1rem;">Revenue Status &amp; Financial Performance (Without Taxes)</h2>
                        <span class="badge font-mono" style="background: rgba(16, 185, 129, 0.12); color: #059669; border: 1px solid rgba(16, 185, 129, 0.25); font-size: 0.7rem; font-weight: 700;">
                            WITHOUT TAXES
                        </span>
                    </div>
                    <span class="card-header__subtitle" style="display: block; font-size: 0.8125rem; color: var(--color-muted); margin-top: 2px;">
                        Compare projects contract price (without taxes), actual spends, and total remaining budget across previous months or years.
                    </span>
                </div>

                {{-- Filter Controls (Left) & Export Actions (Far Right Corner) --}}
                <div class="reports-toolbar-row" style="width: 100%; padding-top: 0.5rem; border-top: 1px solid var(--color-border);">
                    {{-- Left: Comparison & Status Filters --}}
                    <div class="reports-filter-group">
                        <button
                            type="button"
                            class="btn btn-sm reports-btn"
                            :class="revenueMode === 'monthly' ? 'btn-primary' : 'btn-secondary'"
                            @click="revenueMode = 'monthly'; selectedChartBucket = null;"
                            id="btn-revenue-mode-monthly"
                        >
                            Monthly Comparison
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm reports-btn"
                            :class="revenueMode === 'yearly' ? 'btn-primary' : 'btn-secondary'"
                            @click="revenueMode = 'yearly'; selectedChartBucket = null;"
                            id="btn-revenue-mode-yearly"
                        >
                            Yearly Comparison
                        </button>

                        {{-- Year Selector (Monthly Mode) --}}
                        <template x-if="revenueMode === 'monthly'">
                            <select
                                id="revenue-year-filter"
                                x-model="revenueYear"
                                @change="selectedChartBucket = null"
                                class="form-control form-control--compact filter-select reports-select"
                                aria-label="Select year for monthly revenue comparison"
                            >
                                <template x-for="yr in availableYears" :key="'rev-yr-' + yr">
                                    <option :value="String(yr)" x-text="`Year ${yr}`"></option>
                                </template>
                                <option value="all">All Years (Combined)</option>
                            </select>
                        </template>

                        {{-- Project Status Filter --}}
                        <select
                            id="revenue-status-filter"
                            x-model="revenueStatus"
                            @change="selectedChartBucket = null"
                            class="form-control form-control--compact filter-select reports-select"
                            aria-label="Filter revenue chart by project status"
                        >
                            <option value="">All Statuses</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="completed">Completed</option>
                            <option value="pending">Pending</option>
                            <option value="delayed">Delayed</option>
                        </select>
                    </div>

                    {{-- Right Corner: Export Revenue Data Buttons --}}
                    <div class="reports-filter-group" style="margin-left: auto;">
                        <button
                            type="button"
                            class="btn btn-primary btn-sm reports-btn"
                            @click="submitRevenueExport('pdf', 'download')"
                            id="btn-export-revenue-pdf"
                            title="Download Revenue Data Report (Without Taxes) as PDF"
                        >
                            <svg style="width: 14px; height: 14px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M3 17a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1zm3.293-7.707a1 1 0 0 1 1.414 0L9 10.586V3a1 1 0 1 1 2 0v7.586l1.293-1.293a1 1 0 1 1 1.414 1.414l-3 3a1 1 0 0 1-1.414 0l-3-3a1 1 0 0 1 0-1.414z" clip-rule="evenodd"/>
                            </svg>
                            Generate Revenue PDF
                        </button>
                        <button
                            type="button"
                            class="btn btn-secondary btn-sm reports-btn"
                            @click="submitRevenueExport('pdf', 'stream')"
                            title="Preview Revenue PDF Report in browser"
                        >
                            Preview PDF
                        </button>
                        <button
                            type="button"
                            class="btn btn-secondary btn-sm reports-btn"
                            @click="submitRevenueExport('csv', 'download')"
                            id="btn-export-revenue-csv"
                            title="Export Revenue Data (Without Taxes) as CSV spreadsheet"
                        >
                            Export CSV
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding: var(--space-5) var(--space-6);">
                {{-- 4 Revenue Summary Strip Cards (Without Taxes) --}}
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-5);">
                    <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-left: 3px solid #10B981; border-radius: var(--radius-md); padding: var(--space-3) var(--space-4);">
                        <div style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-muted);">
                            Total Contract Value
                        </div>
                        <div class="font-mono text-completed" style="font-size: 1.25rem; font-weight: 700; margin-top: 2px;" x-text="formatCurrency(revenueSummary.contract_price)"></div>
                        <div class="text-muted" style="font-size: 0.75rem; margin-top: 2px;">
                            Across <strong x-text="revenueSummary.count"></strong> project(s)
                        </div>
                    </div>

                    <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-left: 3px solid #D97706; border-radius: var(--radius-md); padding: var(--space-3) var(--space-4);">
                        <div style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-muted);">
                            Total Actual Spends
                        </div>
                        <div class="font-mono text-pending" style="font-size: 1.25rem; font-weight: 700; margin-top: 2px;" x-text="formatCurrency(revenueSummary.actual_spend)"></div>
                        <div class="text-muted" style="font-size: 0.75rem; margin-top: 2px;">
                            <strong class="font-mono" x-text="`${revenueSummary.utilization}%`"></strong> of contract budget spent
                        </div>
                    </div>

                    <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-left: 3px solid #2563EB; border-radius: var(--radius-md); padding: var(--space-3) var(--space-4);">
                        <div style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-muted);">
                            Total revenue / remaining budget (without tax)
                        </div>
                        <div
                            class="font-mono"
                            :class="revenueSummary.remaining_budget >= 0 ? 'text-ongoing' : 'text-danger'"
                            style="font-size: 1.25rem; font-weight: 700; margin-top: 2px;"
                            x-text="formatCurrency(revenueSummary.remaining_budget)"
                        ></div>
                        <div class="text-muted" style="font-size: 0.75rem; margin-top: 2px;">
                            Net revenue balance (Without Taxes)
                        </div>
                    </div>

                    <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-left: 3px solid var(--color-accent); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4);">
                        <div style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-muted);" x-text="`Period Comparison (${revenueSummary.compTitle})`"></div>
                        <div
                            class="font-mono"
                            :class="revenueSummary.compDelta >= 0 ? 'text-completed' : 'text-danger'"
                            style="font-size: 1.15rem; font-weight: 700; margin-top: 2px;"
                            x-text="(revenueSummary.compDelta >= 0 ? '+' : '') + formatCurrency(revenueSummary.compDelta)"
                        ></div>
                        <div class="text-muted" style="font-size: 0.75rem; margin-top: 2px;">
                            <template x-if="revenueSummary.compPct !== null">
                                <span x-text="`${revenueSummary.compPct >= 0 ? '+' : ''}${revenueSummary.compPct}% vs. previous year`"></span>
                            </template>
                            <template x-if="revenueSummary.compPct === null">
                                <span x-text="`Previous period: ${formatCurrency(revenueSummary.prevTotal)}`"></span>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Graphical 3-Bar Chart (X-Axis: Year/Months, Y-Axis: Value/Amounts) --}}
                <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-4) var(--space-5);">
                    {{-- Chart Legend & Title Row --}}
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: var(--space-4);">
                        <div style="font-size: 0.8125rem; font-weight: 700; color: var(--color-ink);">
                            <span x-text="revenueMode === 'yearly' ? 'Yearly Total Contract Value, Actual Spends & Total Revenue (Without Taxes)' : `Monthly Total Contract Value, Actual Spends & Total Revenue (${revenueYear === 'all' ? 'All Years' : revenueYear}) — Without Taxes`"></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; font-size: 0.75rem;">
                            <span style="display: inline-flex; align-items: center; gap: 6px;">
                                <span style="width: 11px; height: 11px; border-radius: 3px; background: #10B981; display: inline-block;"></span>
                                <strong>Total contract value</strong>
                            </span>
                            <span style="display: inline-flex; align-items: center; gap: 6px;">
                                <span style="width: 11px; height: 11px; border-radius: 3px; background: #D97706; display: inline-block;"></span>
                                <strong>Actual spends</strong>
                            </span>
                            <span style="display: inline-flex; align-items: center; gap: 6px;">
                                <span style="width: 11px; height: 11px; border-radius: 3px; background: #2563EB; display: inline-block;"></span>
                                <strong>Total revenue</strong>
                            </span>
                        </div>
                    </div>

                    {{-- Bar Chart Plot Area --}}
                    <div style="display: flex; gap: 0.75rem; align-items: stretch;">
                        {{-- Y-Axis Scale Labels (Value / Amounts) --}}
                        <div class="font-mono text-muted" style="display: flex; flex-direction: column; justify-content: space-between; font-size: 0.68rem; text-align: right; min-width: 58px; height: 210px; padding-bottom: 24px;">
                            <span x-text="formatCompactCurrency(revenueChartMax)"></span>
                            <span x-text="formatCompactCurrency(revenueChartMax * 0.75)"></span>
                            <span x-text="formatCompactCurrency(revenueChartMax * 0.5)"></span>
                            <span x-text="formatCompactCurrency(revenueChartMax * 0.25)"></span>
                            <span>₱0</span>
                        </div>

                        {{-- Columns Container --}}
                        <div style="flex: 1; position: relative; height: 210px; display: flex; flex-direction: column; justify-content: flex-end;">
                            {{-- Horizontal Grid Lines --}}
                            <div style="position: absolute; inset: 0 0 24px 0; display: flex; flex-direction: column; justify-content: space-between; pointer-events: none;">
                                <div style="border-top: 1px dashed var(--color-border); width: 100%;"></div>
                                <div style="border-top: 1px dashed var(--color-border); width: 100%;"></div>
                                <div style="border-top: 1px dashed var(--color-border); width: 100%;"></div>
                                <div style="border-top: 1px dashed var(--color-border); width: 100%;"></div>
                                <div style="border-top: 1px solid var(--color-border); width: 100%;"></div>
                            </div>

                            {{-- 3-Bar Groups per Period --}}
                            <div class="rev-chart-bars-grid">
                                <template x-for="bucket in revenueChartBuckets" :key="bucket.key">
                                    <div
                                        class="rev-chart-bucket"
                                        @mouseenter="selectedChartBucket = bucket"
                                        @click="selectedChartBucket = bucket"
                                        :title="`${bucket.fullLabel} (${bucket.count} project/s)\nTotal contract value: ${formatCurrency(bucket.contract_price)}\nActual spends: ${formatCurrency(bucket.actual_spend)}\nTotal revenue: ${formatCurrency(bucket.remaining_budget)}`"
                                    >
                                        <div class="rev-chart-bar-triplet">
                                            {{-- Bar 1: Total contract value --}}
                                            <div
                                                class="rev-bar rev-bar--contract"
                                                :style="{
                                                    height: (bucket.contract_price > 0 ? Math.max(8, Math.min(180, Math.round((bucket.contract_price / revenueChartMax) * 180))) : 4) + 'px',
                                                    opacity: bucket.contract_price > 0 ? '1' : '0.25'
                                                }"
                                            ></div>
                                            {{-- Bar 2: Actual spends --}}
                                            <div
                                                class="rev-bar rev-bar--spend"
                                                :style="{
                                                    height: (bucket.actual_spend > 0 ? Math.max(8, Math.min(180, Math.round((bucket.actual_spend / revenueChartMax) * 180))) : 4) + 'px',
                                                    opacity: bucket.actual_spend > 0 ? '1' : '0.25'
                                                }"
                                            ></div>
                                            {{-- Bar 3: Total revenue --}}
                                            <div
                                                class="rev-bar"
                                                :class="bucket.remaining_budget >= 0 ? 'rev-bar--revenue' : 'rev-bar--deficit'"
                                                :style="{
                                                    height: (Math.abs(bucket.remaining_budget) > 0 ? Math.max(8, Math.min(180, Math.round((Math.abs(bucket.remaining_budget) / revenueChartMax) * 180))) : 4) + 'px',
                                                    opacity: (bucket.contract_price > 0 || bucket.actual_spend > 0) ? '1' : '0.25'
                                                }"
                                            ></div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- X-Axis Labels (Year / Months) --}}
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(42px, 1fr)); gap: 8px; height: 24px; align-items: center; padding-top: 4px;">
                                <template x-for="bucket in revenueChartBuckets" :key="'lbl-' + bucket.key">
                                    <div class="font-mono" style="text-align: center; font-size: 0.7rem; font-weight: 600; color: var(--color-muted);" x-text="bucket.shortLabel"></div>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Interactive Hovered / Selected Period Summary Strip --}}
                    <div
                        x-show="selectedChartBucket !== null"
                        x-transition.opacity
                        style="margin-top: 0.75rem; padding: 0.55rem 0.85rem; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; font-size: 0.78rem;"
                    >
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span class="badge badge-accent font-mono" x-text="selectedChartBucket ? selectedChartBucket.fullLabel : ''"></span>
                            <span class="text-muted" x-text="selectedChartBucket ? `${selectedChartBucket.count} project(s)` : ''"></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap;">
                            <span>
                                <strong style="color: #059669;">Total contract value:</strong>
                                <span class="font-mono font-bold" x-text="selectedChartBucket ? formatCurrency(selectedChartBucket.contract_price) : ''"></span>
                            </span>
                            <span>
                                <strong style="color: #D97706;">Actual spends:</strong>
                                <span class="font-mono font-bold" x-text="selectedChartBucket ? formatCurrency(selectedChartBucket.actual_spend) : ''"></span>
                            </span>
                            <span>
                                <strong style="color: #2563EB;">Total revenue:</strong>
                                <span class="font-mono font-bold" x-text="selectedChartBucket ? formatCurrency(selectedChartBucket.remaining_budget) : ''"></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Project-by-Project Revenue Ledger (Without Taxes) --}}
        <div class="card card-glass">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-2);">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-weight: 700;">Project-by-Project Revenue &amp; Remaining Budget Ledger (Without Taxes)</span>
                    <span class="badge badge-accent font-mono" style="font-size: 0.75rem;" x-text="`${revenueFilteredProjects.length} project(s)`"></span>
                </div>
                <span class="text-muted" style="font-size: 0.8125rem;">
                    All contract values and total revenue / remaining budget amounts are displayed without taxes
                </span>
            </div>
            <div class="table-wrapper" x-show="revenueFilteredProjects.length > 0">
                <table class="data-table" id="reports-revenue-projects-table">
                    <thead>
                        <tr>
                            <th scope="col">Project Code &amp; Name</th>
                            <th scope="col">Client</th>
                            <th scope="col">Project Date</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-right">Total Contract Value</th>
                            <th scope="col" class="text-right">Actual Spends</th>
                            <th scope="col" class="text-right">Total revenue / remaining budget (without tax)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="proj in revenueFilteredProjects" :key="'rev-proj-' + proj.id">
                            <tr>
                                <td>
                                    <a :href="proj.show_url" class="font-mono font-bold" style="color: var(--color-accent); text-decoration: none;" x-text="proj.project_code"></a>
                                    <div style="font-weight: 600; color: var(--color-ink);" x-text="proj.name"></div>
                                </td>
                                <td x-text="proj.client_name"></td>
                                <td class="font-mono" x-text="proj.report_date"></td>
                                <td>
                                    <span class="status-pill" :class="`status-pill--${proj.status}`" x-text="proj.status.charAt(0).toUpperCase() + proj.status.slice(1)"></span>
                                </td>
                                <td class="text-right font-mono font-bold text-success" x-text="formatCurrency(proj.contract_price)"></td>
                                <td class="text-right font-mono" x-text="formatCurrency(proj.actual_spend)"></td>
                                <td
                                    class="text-right font-mono font-bold"
                                    :class="proj.remaining_budget >= 0 ? 'text-ongoing' : 'text-danger'"
                                    x-text="formatCurrency(proj.remaining_budget)"
                                ></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- =========================================================================
         TAB 3: MATERIALS LIST EXPORT (Selected Materials or Fast/Slow Moving)
         ========================================================================= --}}
    <div x-show="activeTab === 'materials'" x-cloak x-transition.opacity id="panel-materials-report">

        {{-- Hidden Form for Submitting Materials Export --}}
        <form x-ref="materialsExportForm" method="POST" action="{{ route('reports.materials.export') }}" style="display: none;">
            @csrf
            <input type="hidden" name="export_mode" x-ref="matExportMode" value="filtered">
            <input type="hidden" name="format" x-ref="matExportFormat" value="pdf">
            <input type="hidden" name="disposition" x-ref="matExportDisposition" value="download">
            <input type="hidden" name="demand_status" :value="materialDemandStatus">
            <input type="hidden" name="stock_filter" :value="materialStockFilter">
            <input type="hidden" name="search" :value="materialSearch">
            <template x-for="id in selectedMaterialIds" :key="'mat-id-' + id">
                <input type="hidden" name="material_ids[]" :value="id">
            </template>
        </form>

        {{-- Materials Filter & Export Action Bar --}}
        <div class="card card-glass mb-4">
            <div class="card-body" style="padding: var(--space-3) var(--space-5);">
                <div class="reports-toolbar-row" style="margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--color-border);">
                    {{-- Quick Demand Movement Filter Buttons (Horizontally Aligned) --}}
                    <div class="reports-filter-group">
                        <button
                            type="button"
                            class="btn btn-sm reports-btn"
                            :class="materialDemandStatus === '' ? 'btn-primary' : 'btn-secondary'"
                            @click="materialDemandStatus = ''"
                        >
                            All Materials ({{ $metrics['total_materials'] }})
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm reports-btn"
                            :class="materialDemandStatus === 'fast-moving' ? 'btn-primary' : 'btn-secondary'"
                            @click="materialDemandStatus = 'fast-moving'"
                            id="btn-filter-fast-moving"
                        >
                            Fast-Moving ({{ $metrics['fast_moving_materials'] }})
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm reports-btn"
                            :class="materialDemandStatus === 'slow-moving' ? 'btn-primary' : 'btn-secondary'"
                            @click="materialDemandStatus = 'slow-moving'"
                            id="btn-filter-slow-moving"
                        >
                            Slow-Moving ({{ $metrics['slow_moving_materials'] }})
                        </button>
                    </div>

                    {{-- Export Buttons (Horizontally Aligned) --}}
                    <div class="reports-filter-group">
                        <button
                            type="button"
                            class="btn btn-primary btn-sm reports-btn"
                            @click="submitMaterialsExport(selectedMaterialIds.length > 0 ? 'selected' : 'filtered', 'pdf', 'download')"
                            id="btn-export-selected-materials-pdf"
                        >
                            <svg style="width: 14px; height: 14px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M3 17a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1zm3.293-7.707a1 1 0 0 1 1.414 0L9 10.586V3a1 1 0 1 1 2 0v7.586l1.293-1.293a1 1 0 1 1 1.414 1.414l-3 3a1 1 0 0 1-1.414 0l-3-3a1 1 0 0 1 0-1.414z" clip-rule="evenodd"/>
                            </svg>
                            <span>Export Selected<template x-if="selectedMaterialIds.length > 0"> (<span x-text="selectedMaterialIds.length"></span>)</template> to PDF</span>
                        </button>

                        <button
                            type="button"
                            class="btn btn-secondary btn-sm reports-btn"
                            @click="submitMaterialsExport(selectedMaterialIds.length > 0 ? 'selected' : 'filtered', 'pdf', 'stream')"
                            title="Preview Materials PDF Report in browser"
                        >
                            Preview PDF
                        </button>

                        <button
                            type="button"
                            class="btn btn-secondary btn-sm reports-btn"
                            @click="submitMaterialsExport(selectedMaterialIds.length > 0 ? 'selected' : 'filtered', 'csv', 'download')"
                            title="Download Materials list as CSV spreadsheet"
                        >
                            Export CSV
                        </button>
                    </div>
                </div>

                {{-- Compact Search & Horizontal Dropdown Filters --}}
                <div class="reports-toolbar-row">
                    <div class="reports-filter-group">
                        <div class="reports-search-box">
                            <svg class="search-input-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                            </svg>
                            <input
                                type="text"
                                id="report-material-search"
                                x-model="materialSearch"
                                placeholder="Search material..."
                                class="form-control"
                                aria-label="Search materials for export"
                            >
                        </div>

                        <label for="report-material-demand" class="visually-hidden">Filter by Demand Movement</label>
                        <select id="report-material-demand" x-model="materialDemandStatus" class="form-control form-control--compact filter-select reports-select">
                            <option value="">All Demand Statuses</option>
                            <option value="fast-moving">Fast-moving</option>
                            <option value="slow-moving">Slow-moving</option>
                        </select>

                        <label for="report-material-stock" class="visually-hidden">Filter by Stock Availability</label>
                        <select id="report-material-stock" x-model="materialStockFilter" class="form-control form-control--compact filter-select reports-select">
                            <option value="all">All Stock Levels</option>
                            <option value="in_stock">In Stock (&gt; 0)</option>
                            <option value="low_stock">Low Stock (&lt; 5)</option>
                            <option value="out_of_stock">Out of Stock (0)</option>
                        </select>

                        <button
                            type="button"
                            class="btn btn-secondary btn-sm reports-btn"
                            x-show="materialSearch !== '' || materialDemandStatus !== '' || materialStockFilter !== 'all'"
                            x-cloak
                            @click="materialSearch = ''; materialDemandStatus = ''; materialStockFilter = 'all';"
                            style="color: var(--color-danger);"
                        >
                            ✕ Reset
                        </button>
                    </div>

                    <div class="text-muted font-mono" style="font-size: 0.75rem;">
                        Showing <strong x-text="filteredMaterials.length"></strong> of <strong x-text="materials.length"></strong> items
                    </div>
                </div>
            </div>
        </div>

        {{-- Interactive Bulk Selection Banner for Materials --}}
        <div class="bulk-toolbar mb-4" x-show="selectedMaterialIds.length > 0" x-cloak x-transition>
            <div class="bulk-toolbar__left">
                <span class="bulk-toolbar__badge-wrap">
                    <span class="badge badge-accent font-mono" x-text="selectedMaterialIds.length"></span>
                    <strong>Materials Selected for Export</strong>
                </span>
                <button type="button" class="btn btn-secondary btn-sm bulk-toolbar__deselect-btn reports-btn" @click="selectedMaterialIds = []">
                    ✕ Deselect All
                </button>
            </div>
        </div>

        {{-- Materials Inventory Table --}}
        <div class="card card-glass">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-2);">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-weight: 700;">Materials Catalog Selection &amp; Movement List</span>
                    <span class="badge badge-accent font-mono" style="font-size: 0.75rem;" x-text="`${filteredMaterials.length} item(s)`"></span>
                </div>
                <span class="text-muted" style="font-size: 0.8125rem;">
                    Tick checkboxes to export specific materials, or use the filter above to export Fast-Moving / Slow-Moving lists.
                </span>
            </div>

            <div class="card-body" x-show="filteredMaterials.length === 0" x-cloak>
                <div class="empty-state">
                    <h2 class="empty-state__title">No materials match your filter</h2>
                    <p class="empty-state__description">Clear the search or demand status filter to view catalog materials.</p>
                    <button type="button" class="btn btn-secondary btn-sm reports-btn" @click="materialSearch = ''; materialDemandStatus = ''; materialStockFilter = 'all';" style="margin-top: 0.5rem;">
                        Reset Filters
                    </button>
                </div>
            </div>

            <div class="table-wrapper" x-show="filteredMaterials.length > 0">
                <table class="data-table" id="reports-materials-table">
                    <caption class="visually-hidden">Materials catalog list for report export</caption>
                    <thead>
                        <tr>
                            <th scope="col" style="width: 44px; text-align: center;">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    @change="toggleSelectAllMaterials($event)"
                                    :checked="isAllMaterialsSelected()"
                                    aria-label="Select all filtered materials"
                                >
                            </th>
                            <th scope="col">Item Code</th>
                            <th scope="col">Material Name &amp; Description</th>
                            <th scope="col">Model / Technical Specs</th>
                            <th scope="col" class="text-right">Avail Qty</th>
                            <th scope="col" class="text-right">Unit Price (PHP)</th>
                            <th scope="col" class="text-right">Unit Cost (PHP)</th>
                            <th scope="col" class="text-center">Demand Movement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="mat in filteredMaterials" :key="mat.id">
                            <tr :class="{ 'row-selected': selectedMaterialIds.includes(mat.id) }" @click="toggleMaterial(mat.id)" style="cursor: pointer;">
                                <td style="text-align: center;" @click.stop>
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        :value="mat.id"
                                        :checked="selectedMaterialIds.includes(mat.id)"
                                        @change="toggleMaterial(mat.id)"
                                        :aria-label="`Select material ${mat.resource_code}`"
                                    >
                                </td>
                                <td class="font-mono font-bold" style="color: var(--color-accent);" x-text="mat.resource_code"></td>
                                <td>
                                    <div style="font-weight: 600; color: var(--color-ink);" x-text="mat.name"></div>
                                    <template x-if="mat.description">
                                        <div class="text-muted" style="font-size: 0.75rem; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="mat.description"></div>
                                    </template>
                                </td>
                                <td class="font-mono" style="font-size: 0.8125rem;" x-text="mat.model"></td>
                                <td class="text-right font-mono font-bold" :class="mat.quantity_available < 5 ? 'text-danger' : ''" x-text="mat.quantity_available"></td>
                                <td class="text-right font-mono text-success" x-text="formatCurrency(mat.price)"></td>
                                <td class="text-right font-mono text-muted" x-text="formatCurrency(mat.unit_cost)"></td>
                                <td class="text-center">
                                    <span
                                        class="status-pill"
                                        :class="mat.demand_status === 'fast-moving' ? 'status-pill--completed' : 'status-pill--ongoing'"
                                        x-text="mat.demand_status === 'fast-moving' ? 'Fast-moving' : 'Slow-moving'"
                                    ></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- =========================================================================
         TAB 4: MANPOWER LIST EXPORT (Selected Personnel, Expertise Filter & Recent Projects Option)
         ========================================================================= --}}
    <div x-show="activeTab === 'manpower'" x-cloak x-transition.opacity id="panel-manpower-report">

        {{-- Hidden Form for Submitting Manpower Export --}}
        <form x-ref="manpowerExportForm" method="POST" action="{{ route('reports.manpower.export') }}" style="display: none;">
            @csrf
            <input type="hidden" name="export_mode" x-ref="mnpExportMode" value="filtered">
            <input type="hidden" name="format" x-ref="mnpExportFormat" value="pdf">
            <input type="hidden" name="disposition" x-ref="mnpExportDisposition" value="download">
            <input type="hidden" name="expertise" :value="manpowerExpertise">
            <input type="hidden" name="status" :value="manpowerStatus">
            <input type="hidden" name="search" :value="manpowerSearch">
            <input type="hidden" name="include_recent_projects" :value="includeRecentProjects ? '1' : '0'">
            <template x-for="id in selectedPersonnelIds" :key="'mnp-id-' + id">
                <input type="hidden" name="personnel_ids[]" :value="id">
            </template>
        </form>

        {{-- Manpower Filter, Recent Projects Option & Export Action Bar --}}
        <div class="card card-glass mb-4">
            <div class="card-body" style="padding: var(--space-3) var(--space-5);">
                <div class="reports-toolbar-row" style="margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--color-border);">
                    {{-- Option Toggle: Include Recent Projects for Personnel --}}
                    <label
                        for="toggle-include-recent-projects"
                        style="display: inline-flex; align-items: center; gap: 0.5rem; height: 36px; padding: 0 0.85rem; background: var(--color-accent-light); border: 1px solid var(--color-accent); border-radius: var(--radius-md); cursor: pointer; font-size: 0.8125rem; color: var(--color-ink); margin-bottom: 0;"
                    >
                        <input
                            type="checkbox"
                            id="toggle-include-recent-projects"
                            class="form-check-input"
                            x-model="includeRecentProjects"
                            style="width: 15px; height: 15px; accent-color: var(--color-accent); cursor: pointer; margin: 0;"
                        >
                        <span>
                            <strong>Include recent projects for each personnel</strong>
                        </span>
                    </label>

                    {{-- Export Action Buttons (Horizontally Aligned) --}}
                    <div class="reports-filter-group">
                        <button
                            type="button"
                            class="btn btn-primary btn-sm reports-btn"
                            @click="submitManpowerExport(selectedPersonnelIds.length > 0 ? 'selected' : 'filtered', 'pdf', 'download')"
                            id="btn-export-selected-manpower-pdf"
                        >
                            <svg style="width: 14px; height: 14px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M3 17a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1zm3.293-7.707a1 1 0 0 1 1.414 0L9 10.586V3a1 1 0 1 1 2 0v7.586l1.293-1.293a1 1 0 1 1 1.414 1.414l-3 3a1 1 0 0 1-1.414 0l-3-3a1 1 0 0 1 0-1.414z" clip-rule="evenodd"/>
                            </svg>
                            <span>Export Selected<template x-if="selectedPersonnelIds.length > 0"> (<span x-text="selectedPersonnelIds.length"></span>)</template> to PDF</span>
                        </button>

                        <button
                            type="button"
                            class="btn btn-secondary btn-sm reports-btn"
                            @click="submitManpowerExport(selectedPersonnelIds.length > 0 ? 'selected' : 'filtered', 'pdf', 'stream')"
                            title="Preview Manpower PDF Report in browser"
                        >
                            Preview PDF
                        </button>

                        <button
                            type="button"
                            class="btn btn-secondary btn-sm reports-btn"
                            @click="submitManpowerExport(selectedPersonnelIds.length > 0 ? 'selected' : 'filtered', 'csv', 'download')"
                            title="Download Manpower Roster as CSV spreadsheet"
                        >
                            Export CSV
                        </button>
                    </div>
                </div>

                {{-- Compact Search & Horizontal Expertise / Status Filter Bar --}}
                <div class="reports-toolbar-row">
                    <div class="reports-filter-group">
                        <div class="reports-search-box">
                            <svg class="search-input-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                            </svg>
                            <input
                                type="text"
                                id="report-manpower-search"
                                x-model="manpowerSearch"
                                placeholder="Search personnel..."
                                class="form-control"
                                aria-label="Search manpower for export"
                            >
                        </div>

                        <label for="report-manpower-expertise" class="visually-hidden">Filter by Expertise</label>
                        <select id="report-manpower-expertise" x-model="manpowerExpertise" class="form-control form-control--compact filter-select reports-select">
                            <option value="">All Expertise Roles</option>
                            @foreach ($expertiseOptions as $opt)
                                <option value="{{ $opt }}">{{ $opt }}</option>
                            @endforeach
                        </select>

                        <label for="report-manpower-status" class="visually-hidden">Filter by Deployment Status</label>
                        <select id="report-manpower-status" x-model="manpowerStatus" class="form-control form-control--compact filter-select reports-select">
                            <option value="active">Active Roster</option>
                            <option value="assigned">Currently Assigned</option>
                            <option value="not_assigned">Available (Not Assigned)</option>
                            <option value="archived">Archived / Inactive</option>
                            <option value="all">All Personnel</option>
                        </select>

                        <button
                            type="button"
                            class="btn btn-secondary btn-sm reports-btn"
                            x-show="manpowerSearch !== '' || manpowerExpertise !== '' || manpowerStatus !== 'active'"
                            x-cloak
                            @click="manpowerSearch = ''; manpowerExpertise = ''; manpowerStatus = 'active';"
                            style="color: var(--color-danger);"
                        >
                            ✕ Reset
                        </button>
                    </div>

                    <div class="text-muted font-mono" style="font-size: 0.75rem;">
                        Showing <strong x-text="filteredManpower.length"></strong> of <strong x-text="manpower.length"></strong> personnel
                    </div>
                </div>
            </div>
        </div>

        {{-- Interactive Bulk Selection Banner for Manpower (Without Redundant Download Buttons) --}}
        <div class="bulk-toolbar mb-4" x-show="selectedPersonnelIds.length > 0" x-cloak x-transition>
            <div class="bulk-toolbar__left">
                <span class="bulk-toolbar__badge-wrap">
                    <span class="badge badge-accent font-mono" x-text="selectedPersonnelIds.length"></span>
                    <strong>Personnel Selected for Export</strong>
                </span>
                <button type="button" class="btn btn-secondary btn-sm bulk-toolbar__deselect-btn reports-btn" @click="selectedPersonnelIds = []">
                    ✕ Deselect All
                </button>
            </div>
        </div>

        {{-- Manpower Selection & Preview Table --}}
        <div class="card card-glass">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-2);">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-weight: 700;">Manpower Roster Selection &amp; Expertise List</span>
                    <span class="badge badge-accent font-mono" style="font-size: 0.75rem;" x-text="`${filteredManpower.length} personnel`"></span>
                </div>
                <span class="text-muted" style="font-size: 0.8125rem;">
                    Select specific personnel or filter by expertise. Toggle "Include recent projects" above to include deployment history.
                </span>
            </div>

            <div class="card-body" x-show="filteredManpower.length === 0" x-cloak>
                <div class="empty-state">
                    <h2 class="empty-state__title">No personnel match your filter</h2>
                    <p class="empty-state__description">Clear the expertise or status filter to view registered field personnel.</p>
                    <button type="button" class="btn btn-secondary btn-sm" @click="manpowerSearch = ''; manpowerExpertise = ''; manpowerStatus = 'all';" style="margin-top: 0.5rem;">
                        Reset Filters
                    </button>
                </div>
            </div>

            <div class="table-wrapper" x-show="filteredManpower.length > 0">
                <table class="data-table" id="reports-manpower-table">
                    <caption class="visually-hidden">Field personnel list for report export</caption>
                    <thead>
                        <tr>
                            <th scope="col" style="width: 44px; text-align: center;">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    @change="toggleSelectAllManpower($event)"
                                    :checked="isAllManpowerSelected()"
                                    aria-label="Select all filtered personnel"
                                >
                            </th>
                            <th scope="col">Employee ID</th>
                            <th scope="col">Full Name</th>
                            <th scope="col">Expertise / Role</th>
                            <th scope="col">Deployment Status</th>
                            <th scope="col">Current Project</th>
                            <th scope="col" x-show="includeRecentProjects">Recent Projects History</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="person in filteredManpower" :key="person.id">
                            <tr :class="{ 'row-selected': selectedPersonnelIds.includes(person.id) }" @click="togglePersonnel(person.id)" style="cursor: pointer;">
                                <td style="text-align: center;" @click.stop>
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        :value="person.id"
                                        :checked="selectedPersonnelIds.includes(person.id)"
                                        @change="togglePersonnel(person.id)"
                                        :aria-label="`Select personnel ${person.employee_id}`"
                                    >
                                </td>
                                <td class="font-mono font-bold" style="color: var(--color-accent);" x-text="person.employee_id"></td>
                                <td>
                                    <div style="font-weight: 600; color: var(--color-ink);" x-text="person.full_name"></div>
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--color-surface-alt); border: 1px solid var(--color-border); font-weight: 600;" x-text="person.expertise"></span>
                                </td>
                                <td>
                                    <span
                                        class="status-pill"
                                        :class="person.status_label === 'Assigned' ? 'status-pill--completed' : (person.status_label === 'Archived' ? 'status-pill--delayed' : 'status-pill--pending')"
                                        x-text="person.status_label"
                                    ></span>
                                </td>
                                <td>
                                    <template x-if="person.project_code">
                                        <div>
                                            <span class="font-mono font-bold" style="color: var(--color-accent); font-size: 0.78rem;" x-text="person.project_code"></span>
                                            <div style="font-size: 0.8125rem; font-weight: 500;" x-text="person.project_name"></div>
                                        </div>
                                    </template>
                                    <template x-if="!person.project_code">
                                        <span class="text-muted" style="font-size: 0.8125rem;">Unassigned</span>
                                    </template>
                                </td>
                                <td x-show="includeRecentProjects">
                                    <template x-if="person.recent_projects && person.recent_projects.length > 0">
                                        <div style="display: flex; flex-direction: column; gap: 4px;">
                                            <template x-for="(rp, rpIdx) in person.recent_projects" :key="rpIdx">
                                                <div style="font-size: 0.75rem; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-left: 3px solid var(--color-accent); padding: 3px 8px; border-radius: var(--radius-sm);">
                                                    <div>
                                                        <span class="font-mono font-bold" style="color: var(--color-accent);" x-text="rp.project_code"></span>
                                                        · <strong x-text="rp.project_name"></strong>
                                                        <span class="text-muted" x-text="`(${rp.project_status})`"></span>
                                                    </div>
                                                    <div class="text-muted font-mono" style="font-size: 0.68rem;" x-text="`${rp.assigned_at} → ${rp.released_at}`"></div>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="!person.recent_projects || person.recent_projects.length === 0">
                                        <span class="text-muted" style="font-size: 0.75rem; font-style: italic;">No recent projects recorded</span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
