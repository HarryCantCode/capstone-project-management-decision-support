@extends('layouts.app')

@section('title', 'Decision Support')
@section('meta-description', 'In-memory what-if scenario simulations for project feasibility, schedule delays, budget adjustments, and specification-driven resource allocation.')

@section('content')
<script>
    window.dssModuleState = function () {
        return {
        activeTab: '{{ $initialTab ?? 'simulator' }}',
        simMode: 'feasibility',

        /* ── Minimize / Collapse Section States (Progressive Disclosure) ── */
        showFormulas: false,
        showFeasInputs: true,
        showAllocSpecs: true,
        showTemplateDrawer: false,

        /* ── Data Hydrated from RuleEngineService & Catalog ── */
        matrix: @js($matrix),
        catalogAlerts: @js($catalogAlerts),
        personnelPool: @js($personnelForSim),
        resourcesPool: @js($resourcesForSim),
        expertiseOptions: @js($expertiseOptions),

        /* ── Tab 1A: Project Feasibility Simulator State (In-Memory) ── */
        feasStartDate: '{{ now()->addDays(7)->format('Y-m-d') }}',
        feasEndDate: '{{ now()->addDays(97)->format('Y-m-d') }}',
        feasBudget: 3500000,
        feasRoles: [
            { expertise: 'Project Manager', count: 1 },
            { expertise: 'Site Engineer', count: 1 },
            { expertise: 'Safety Officer', count: 1 },
            { expertise: 'Technician', count: 2 }
        ],
        feasMaterials: @js(
            collect($resourcesForSim)->take(2)->map(fn ($r) => [
                'resource_id' => $r['id'],
                'quantity' => 2,
            ])->values()->all()
        ),

        addFeasRole() {
            const used = this.feasRoles.map(r => r.expertise);
            const nextRole = this.expertiseOptions.find(e => !used.includes(e)) || this.expertiseOptions[0] || 'Technician';
            this.feasRoles.push({ expertise: nextRole, count: 1 });
        },

        removeFeasRole(idx) {
            if (this.feasRoles.length > 1) {
                this.feasRoles.splice(idx, 1);
            }
        },

        addFeasMaterial() {
            const firstRes = this.resourcesPool[0];
            if (!firstRes) return;
            this.feasMaterials.push({ resource_id: firstRes.id, quantity: 1 });
        },

        removeFeasMaterial(idx) {
            this.feasMaterials.splice(idx, 1);
        },

        get feasibilityResult() {
            const budget = Math.max(0, Number(this.feasBudget || 0));
            const startStr = this.feasStartDate || '{{ now()->format('Y-m-d') }}';
            const endStr = this.feasEndDate || startStr;
            const startMs = Date.parse(startStr);
            const endMs = Math.max(startMs + 86400000, Date.parse(endStr) || (startMs + 86400000 * 60));
            const plannedDays = Math.max(1, Math.round((endMs - startMs) / 86400000));

            const unassigned = this.personnelPool.filter(p => !p.project_id);
            const releasable = this.personnelPool.filter(p => {
                if (!p.project_id) return false;
                if (p.project_status === 'completed') return true;
                if (p.project_target_date && Date.parse(p.project_target_date) < startMs) return true;
                return false;
            });

            const roleMap = {};
            this.feasRoles.forEach(r => {
                const count = Math.max(0, parseInt(r.count || 0, 10));
                if (!r.expertise || count <= 0) return;
                roleMap[r.expertise] = (roleMap[r.expertise] || 0) + count;
            });

            const roleGaps = [];
            const suggestedCrew = [];
            let totalReqHeadcount = 0;
            let totalManpowerDeficit = 0;
            let totalReleasableUsed = 0;

            Object.entries(roleMap).forEach(([expertise, needed]) => {
                totalReqHeadcount += needed;
                const availNowList = unassigned
                    .filter(p => p.expertise === expertise)
                    .sort((a, b) => (a.historical_load - b.historical_load) || a.employee_id.localeCompare(b.employee_id));
                const freeByStartList = releasable
                    .filter(p => p.expertise === expertise)
                    .sort((a, b) => (a.historical_load - b.historical_load) || a.employee_id.localeCompare(b.employee_id));

                const availNow = availNowList.length;
                const freeByStart = freeByStartList.length;
                const effectiveAvail = availNow + freeByStart;
                const deficit = Math.max(0, needed - effectiveAvail);
                totalManpowerDeficit += deficit;

                if (availNow < needed && effectiveAvail >= needed) {
                    totalReleasableUsed += (needed - availNow);
                }

                const status = deficit > 0 ? 'critical' : (availNow < needed ? 'warning' : 'ok');
                const coveragePct = needed > 0 ? Math.min(100, Math.round((effectiveAvail / needed) * 100)) : 100;
                const rgFormula = freeByStart > 0
                    ? `Gap = max(0, ${needed} Req − (${availNow} Avail + ${freeByStart} Free by Start)) = ${deficit}`
                    : `Gap = max(0, ${needed} Req − ${availNow} Avail) = ${deficit}`;

                roleGaps.push({
                    expertise,
                    required: needed,
                    available_now: availNow,
                    free_by_start: freeByStart,
                    deficit,
                    coverage_pct: coveragePct,
                    status,
                    formula: rgFormula
                });

                availNowList.slice(0, needed).forEach(cand => {
                    suggestedCrew.push({
                        employee_id: cand.employee_id,
                        full_name: cand.full_name,
                        expertise: cand.expertise,
                        historical_load: cand.historical_load,
                        availability: 'Available Now'
                    });
                });

                if (availNow < needed) {
                    freeByStartList.slice(0, needed - availNow).forEach(cand => {
                        suggestedCrew.push({
                            employee_id: cand.employee_id,
                            full_name: cand.full_name,
                            expertise: cand.expertise,
                            historical_load: cand.historical_load,
                            availability: `Free after ${cand.project_code}`
                        });
                    });
                }
            });

            const matMap = {};
            this.feasMaterials.forEach(m => {
                const id = parseInt(m.resource_id || 0, 10);
                const qty = Math.max(0, parseInt(m.quantity || 0, 10));
                if (id <= 0 || qty <= 0) return;
                matMap[id] = (matMap[id] || 0) + qty;
            });

            const materialGaps = [];
            let totalMaterialCost = 0;
            let totalMaterialDeficitItems = 0;

            Object.entries(matMap).forEach(([resIdStr, reqQty]) => {
                const res = this.resourcesPool.find(r => Number(r.id) === Number(resIdStr));
                if (!res) return;

                const avail = Number(res.quantity_available || 0);
                const deficit = Math.max(0, reqQty - avail);
                const reorderQty = deficit > 0 ? Math.ceil(deficit * 1.10) : 0;
                const lineCost = reqQty * Number(res.price || 0);
                totalMaterialCost += lineCost;

                if (deficit > 0) totalMaterialDeficitItems++;

                const status = deficit > 0 ? 'critical' : ((avail - reqQty) < 5 ? 'warning' : 'ok');
                const stockPct = reqQty > 0 ? Math.min(100, Math.round((avail / reqQty) * 100)) : 100;

                materialGaps.push({
                    resource_code: res.resource_code,
                    name: res.name,
                    required_qty: reqQty,
                    available_stock: avail,
                    remaining_after: avail - reqQty,
                    deficit,
                    reorder_qty: reorderQty,
                    stock_pct: stockPct,
                    unit_price: Number(res.price || 0),
                    line_cost: lineCost,
                    status,
                    formula: deficit > 0
                        ? `Deficit = ${reqQty} − ${avail} = ${deficit} → Reorder = ⌈${deficit} × 1.10⌉ = ${reorderQty} units`
                        : `Sufficient (${avail} avail − ${reqQty} req = ${avail - reqQty} left)`
                });
            });

            const remainingMargin = budget - totalMaterialCost;
            const materialSharePct = budget > 0 ? Math.round((totalMaterialCost / budget) * 1000) / 10 : 0;

            let status = 'ok';
            let ratingColor = 'Green';
            let ratingLabel = 'Feasible — Ready to Accept';

            if (totalManpowerDeficit > 0 || totalMaterialDeficitItems > 0 || (budget > 0 && totalMaterialCost > budget)) {
                status = 'critical';
                ratingColor = 'Red';
                ratingLabel = 'Not Feasible — Critical Resource or Budget Gaps';
            } else if (totalReleasableUsed > 0 || materialSharePct >= 70) {
                status = 'warning';
                ratingColor = 'Yellow';
                ratingLabel = 'Conditionally Feasible — Tight Margin / Wait Crew Release';
            }

            return {
                status,
                ratingColor,
                ratingLabel,
                plannedDays,
                budget,
                totalMaterialCost,
                remainingMargin,
                materialSharePct,
                totalReqHeadcount,
                totalManpowerDeficit,
                totalMaterialDeficitItems,
                roleGaps,
                materialGaps,
                suggestedCrew
            };
        },

        /* ── Tab 2B: Delay Propagation Simulator State (In-Memory) ── */
        delayProjectId: {{ $selectedProject ? (int) $selectedProject->id : ($matrix[0]['id'] ?? 'null') }},
        delaySlipDays: 14,

        get delaySimResult() {
            const proj = this.matrix.find(p => Number(p.id) === Number(this.delayProjectId)) || this.matrix[0] || null;
            if (!proj) return null;

            const slip = Math.max(0, Math.min(365, parseInt(this.delaySlipDays || 0, 10)));
            const origDateStr = proj.schedule?.target_completion_date || proj.target_date;
            const origDateMs = Date.parse(origDateStr) || Date.now();
            const origDateFormatted = new Date(origDateMs).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const newDateObj = new Date(origDateMs + slip * 86400000);
            const newDateFormatted = newDateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

            const assignedCrew = (proj.manpower?.assigned_crew || []).map(c => ({
                ...c,
                original_release: origDateFormatted,
                delayed_release: newDateFormatted,
                slip_days: slip
            }));

            const lockedRoles = [...new Set(assignedCrew.map(c => c.expertise))];

            const downstream = [];
            this.matrix.forEach(other => {
                if (Number(other.id) === Number(proj.id) || other.project_status === 'completed') return;
                const oStartMs = Date.parse(other.start_date) || Date.now();
                const oEndMs = Date.parse(other.target_date) || (oStartMs + 86400000 * 60);

                if (oStartMs <= newDateObj.getTime() && oEndMs >= origDateMs) {
                    const overlapStart = Math.max(oStartMs, origDateMs);
                    const overlapEnd = Math.min(oEndMs, newDateObj.getTime());
                    const overlapDays = Math.max(1, Math.round((overlapEnd - overlapStart) / 86400000));

                    downstream.push({
                        project_code: other.project_code,
                        name: other.name,
                        status: other.project_status,
                        start_date: other.start_date,
                        target_date: other.target_date,
                        overlap_days: Math.min(slip, overlapDays),
                        overlap_pct: slip > 0 ? Math.min(100, Math.round((Math.min(slip, overlapDays) / slip) * 100)) : 0,
                        competing_roles: lockedRoles.length > 0 ? lockedRoles.slice(0, 3).join(', ') : 'General Field Crew',
                        impact_status: other.project_status === 'pending' ? 'warning' : 'critical'
                    });
                }
            });

            const status = slip === 0 ? 'ok' : ((slip >= 14 || downstream.length > 0) ? 'critical' : 'warning');
            const origDays = Math.max(1, Number(proj.schedule?.total_planned_days || 60));
            const slipRatioPct = Math.min(50, Math.round((slip / (origDays + slip)) * 100));

            return {
                project: proj,
                slipDays: slip,
                origDays,
                slipRatioPct,
                origTargetDate: origDateFormatted,
                newTargetDate: newDateFormatted,
                affectedCrew: assignedCrew,
                downstreamProjects: downstream,
                status,
                formula: `New Target Date = ${origDateStr} + ${slip} day(s) = ${newDateFormatted} · ${assignedCrew.length} crew member(s) held`
            };
        },

        /* ── Tab 2C: Budget / Price Adjustment Simulator State (In-Memory) ── */
        budgetProjectId: {{ $selectedProject ? (int) $selectedProject->id : ($matrix[0]['id'] ?? 'null') }},
        simMaterialDeltaPct: 10,
        simOtherDeltaPct: 0,
        simContingencyPhp: 0,

        get budgetSimResult() {
            const proj = this.matrix.find(p => Number(p.id) === Number(this.budgetProjectId)) || this.matrix[0] || null;
            if (!proj) return null;

            const contractPrice = Number(proj.contract_price || 0);
            const origTotalSpend = Number(proj.actual_spend || 0);
            const origMatSpend = Number(proj.inventory?.total_material_spend || 0);
            const origOtherSpend = Math.max(0, origTotalSpend - origMatSpend);

            const matDelta = Number(this.simMaterialDeltaPct || 0);
            const otherDelta = Number(this.simOtherDeltaPct || 0);
            const contingency = Math.max(0, Number(this.simContingencyPhp || 0));

            const simMatSpend = Math.max(0, origMatSpend * (1 + matDelta / 100));
            const simOtherSpend = Math.max(0, origOtherSpend * (1 + otherDelta / 100));
            const simTotalSpend = simMatSpend + simOtherSpend + contingency;

            const spendDelta = simTotalSpend - origTotalSpend;
            const origRemaining = contractPrice - origTotalSpend;
            const simRemaining = contractPrice - simTotalSpend;

            const origUtilPct = contractPrice > 0 ? Math.round((origTotalSpend / contractPrice) * 1000) / 10 : 0;
            const simUtilPct = contractPrice > 0 ? Math.round((simTotalSpend / contractPrice) * 1000) / 10 : 0;

            const chartMax = Math.max(contractPrice, origTotalSpend, simTotalSpend, 100000);

            let status = 'ok';
            let healthLabel = 'On Track';
            if (simUtilPct > 100) {
                status = 'critical';
                healthLabel = 'Over Budget';
            } else if (simUtilPct >= 85) {
                status = 'warning';
                healthLabel = 'At Risk';
            }

            return {
                project: proj,
                contractPrice,
                origMatSpend,
                origOtherSpend,
                origTotalSpend,
                origRemaining,
                origUtilPct,
                simMatSpend,
                simOtherSpend,
                contingency,
                simTotalSpend,
                simRemaining,
                simUtilPct,
                spendDelta,
                chartMax,
                status,
                healthLabel,
                formula: `Simulated Spend = (Materials ${this.formatCurrency(origMatSpend)} × ${(100 + matDelta).toFixed(1)}%) + (Other ${this.formatCurrency(origOtherSpend)} × ${(100 + otherDelta).toFixed(1)}%) + ${this.formatCurrency(contingency)} = ${this.formatCurrency(simTotalSpend)}`
            };
        },

        /* ── Tab 3: Generate Resource Allocation (Spec-Driven & Template Builder) ── */
        allocProjectId: {{ $selectedProject ? (int) $selectedProject->id : ($matrix[0]['id'] ?? 'null') }},
        allocPreset: 'auto_spec',
        allocTemplateName: 'Auto-Spec Resource Template',
        newCustomTemplateName: '',
        customTemplates: [],
        allocRoles: [
            { expertise: 'Project Manager', count: 1 },
            { expertise: 'Site Engineer', count: 1 },
            { expertise: 'Safety Officer', count: 1 },
            { expertise: 'Technician', count: 1 },
            { expertise: 'Foreman', count: 1 }
        ],
        allocMaterials: @js(
            collect($resourcesForSim)->take(2)->map(fn ($r) => [
                'resource_id' => $r['id'],
                'quantity' => 2,
            ])->values()->all()
        ),

        init() {
            try {
                const saved = localStorage.getItem('dex_dss_alloc_templates_v1');
                if (saved) {
                    this.customTemplates = JSON.parse(saved) || [];
                }
            } catch (e) {
                this.customTemplates = [];
            }
            if (this.activeAllocProject) {
                this.applySpecTemplate('auto_spec');
            }
        },

        get activeAllocProject() {
            if (!this.allocProjectId) return this.matrix[0] || null;
            return this.matrix.find(p => Number(p.id) === Number(this.allocProjectId)) || this.matrix[0] || null;
        },

        addAllocRole() {
            const used = this.allocRoles.map(r => r.expertise);
            const nextRole = this.expertiseOptions.find(e => !used.includes(e)) || this.expertiseOptions[0] || 'Technician';
            this.allocRoles.push({ expertise: nextRole, count: 1 });
        },

        removeAllocRole(idx) {
            this.allocRoles.splice(idx, 1);
        },

        addAllocMaterial() {
            const usedIds = this.allocMaterials.map(m => Number(m.resource_id));
            const nextRes = this.resourcesPool.find(r => !usedIds.includes(Number(r.id))) || this.resourcesPool[0];
            if (!nextRes) return;
            this.allocMaterials.push({ resource_id: nextRes.id, quantity: 1 });
        },

        removeAllocMaterial(idx) {
            this.allocMaterials.splice(idx, 1);
        },

        applySpecTemplate(presetKey = null) {
            if (presetKey) {
                this.allocPreset = presetKey;
            }
            const proj = this.activeAllocProject;
            if (!proj) return;

            const specs = proj.specs || {};
            const stops = Math.max(2, parseInt(specs.no_of_stops || 4, 10));
            const capStr = String(specs.rated_capacity || '800');
            const capMatch = capStr.match(/\d+/);
            const capacityKg = capMatch ? parseInt(capMatch[0], 10) : 800;
            const projType = String(specs.project_type || 'Passenger Elevator');

            // Check custom saved templates first
            if (String(this.allocPreset).startsWith('custom:')) {
                const customId = String(this.allocPreset).replace('custom:', '');
                const found = this.customTemplates.find(t => String(t.id) === customId);
                if (found) {
                    this.allocTemplateName = found.name;
                    this.allocRoles = JSON.parse(JSON.stringify(found.roles || []));
                    this.allocMaterials = JSON.parse(JSON.stringify(found.materials || []));
                    return;
                }
            }

            let scaleFactor = 1;
            let techCount = 1;
            let foremanCount = 1;

            if (this.allocPreset === 'low_rise') {
                scaleFactor = 1;
                techCount = 1;
                foremanCount = 1;
                this.allocTemplateName = 'Low-Rise Standard Template (2–5 Stops)';
            } else if (this.allocPreset === 'mid_rise') {
                scaleFactor = 2;
                techCount = 2;
                foremanCount = 1;
                this.allocTemplateName = 'Mid-Rise Commercial Template (6–12 Stops)';
            } else if (this.allocPreset === 'high_rise') {
                scaleFactor = 3;
                techCount = 3;
                foremanCount = 2;
                this.allocTemplateName = 'High-Rise / Heavy Freight Template (13+ Stops)';
            } else {
                // auto_spec: derive directly from project's elevator specifications & missing crew roles
                if (stops >= 12 || capacityKg >= 1350 || projType.toLowerCase().includes('freight')) {
                    scaleFactor = 3;
                    techCount = 2;
                    foremanCount = 2;
                } else if (stops >= 6 || capacityKg >= 1000) {
                    scaleFactor = 2;
                    techCount = 2;
                    foremanCount = 1;
                } else {
                    scaleFactor = 1;
                    techCount = 1;
                    foremanCount = 1;
                }
                this.allocTemplateName = `Spec-Matched: ${projType} (${stops} Stops · ${specs.rated_capacity || 'Standard'})`;
            }

            // Build Manpower Roles: prioritize missing core roles on the project, or standard crew
            const missingRoles = proj.manpower?.missing_roles || [];
            if (this.allocPreset === 'auto_spec' && missingRoles.length > 0) {
                this.allocRoles = missingRoles.map(role => ({
                    expertise: role,
                    count: role === 'Technician' ? techCount : (role === 'Foreman' ? foremanCount : 1)
                }));
            } else if (this.allocPreset === 'auto_spec' && missingRoles.length === 0) {
                // All 5 core roles already covered on project -> default to 1 extra Technician or empty
                this.allocRoles = [
                    { expertise: 'Technician', count: 1 }
                ];
            } else {
                this.allocRoles = [
                    { expertise: 'Project Manager', count: 1 },
                    { expertise: 'Site Engineer', count: 1 },
                    { expertise: 'Safety Officer', count: 1 },
                    { expertise: 'Technician', count: techCount },
                    { expertise: 'Foreman', count: foremanCount }
                ];
            }

            // Build Material Template scaled by stops / capacity while respecting catalog stock & budget
            const availResources = this.resourcesPool.filter(r => Number(r.quantity_available) > 0);
            const pickPool = availResources.length > 0 ? availResources : this.resourcesPool;
            const takeCount = Math.min(pickPool.length, scaleFactor === 1 ? 2 : (scaleFactor === 2 ? 3 : 4));

            this.allocMaterials = pickPool.slice(0, takeCount).map((r, i) => {
                const desiredQty = Math.max(1, scaleFactor + (i === 0 ? Math.min(4, Math.floor(stops / 3)) : 0));
                const cappedQty = r.quantity_available > 0 ? Math.min(desiredQty, r.quantity_available) : desiredQty;
                return {
                    resource_id: r.id,
                    quantity: Math.max(1, cappedQty)
                };
            });
        },

        saveCustomTemplate() {
            const name = (this.newCustomTemplateName || this.allocTemplateName || '').trim();
            if (!name) return;
            const proj = this.activeAllocProject;
            const newTpl = {
                id: String(Date.now()),
                name,
                spec_hint: proj ? `${proj.specs?.project_type || 'Elevator'} · ${proj.specs?.no_of_stops || 0} Stops` : 'Custom Scenario',
                roles: JSON.parse(JSON.stringify(this.allocRoles)),
                materials: JSON.parse(JSON.stringify(this.allocMaterials))
            };
            this.customTemplates.push(newTpl);
            try {
                localStorage.setItem('dex_dss_alloc_templates_v1', JSON.stringify(this.customTemplates));
            } catch (e) {}
            this.allocPreset = 'custom:' + newTpl.id;
            this.allocTemplateName = name;
            this.newCustomTemplateName = '';
            this.showTemplateDrawer = false;
        },

        deleteCustomTemplate(id) {
            this.customTemplates = this.customTemplates.filter(t => String(t.id) !== String(id));
            try {
                localStorage.setItem('dex_dss_alloc_templates_v1', JSON.stringify(this.customTemplates));
            } catch (e) {}
            if (this.allocPreset === 'custom:' + id) {
                this.applySpecTemplate('auto_spec');
            }
        },

        get allocValidation() {
            const proj = this.activeAllocProject;
            if (!proj) {
                return {
                    hasErrors: true,
                    errors: ['No target project selected.'],
                    warnings: [],
                    roleChecks: [],
                    materialChecks: [],
                    matchedCrew: [],
                    totalMaterialCost: 0,
                    totalPersonnelCount: 0,
                    remainingBudgetBefore: 0,
                    remainingBudgetAfter: 0,
                    newUtilPct: 0,
                    status: 'critical'
                };
            }

            const errors = [];
            const warnings = [];

            if (proj.project_status === 'completed') {
                errors.push(`Project ${proj.project_code} is already completed and cannot receive new resource allocations.`);
            }

            // 1. Aggregate & Validate Manpower Roles against Unassigned Pool
            const unassigned = this.personnelPool.filter(p => !p.project_id);
            const roleMap = {};
            this.allocRoles.forEach(r => {
                const cnt = Math.max(0, parseInt(r.count || 0, 10));
                if (!r.expertise || cnt <= 0) return;
                roleMap[r.expertise] = (roleMap[r.expertise] || 0) + cnt;
            });

            const roleChecks = [];
            const matchedCrew = [];
            let totalPersonnelCount = 0;

            Object.entries(roleMap).forEach(([expertise, needed]) => {
                totalPersonnelCount += needed;
                const candidates = unassigned
                    .filter(p => p.expertise === expertise)
                    .sort((a, b) => (a.historical_load - b.historical_load) || a.employee_id.localeCompare(b.employee_id));

                const availCount = candidates.length;
                const deficit = Math.max(0, needed - availCount);
                const status = deficit > 0 ? 'critical' : 'ok';

                if (deficit > 0) {
                    errors.push(`Not enough available manpower for "${expertise}": requested ${needed}, but only ${availCount} unassigned in pool.`);
                }

                const picked = candidates.slice(0, needed);
                picked.forEach(c => matchedCrew.push(c));

                roleChecks.push({
                    expertise,
                    needed,
                    available: availCount,
                    deficit,
                    status,
                    coverage_pct: needed > 0 ? Math.min(100, Math.round((availCount / needed) * 100)) : 100,
                    picked_names: picked.map(p => `${p.employee_id} (${p.full_name})`).join(', ')
                });
            });

            // 2. Aggregate & Validate Materials against Catalog Stock & Minimum Cost
            const matMap = {};
            this.allocMaterials.forEach(m => {
                const id = parseInt(m.resource_id || 0, 10);
                const qty = Math.max(0, parseInt(m.quantity || 0, 10));
                if (id <= 0 || qty <= 0) return;
                matMap[id] = (matMap[id] || 0) + qty;
            });

            const materialChecks = [];
            let totalMaterialCost = 0;

            Object.entries(matMap).forEach(([resIdStr, reqQty]) => {
                const res = this.resourcesPool.find(r => Number(r.id) === Number(resIdStr));
                if (!res) return;

                const avail = Number(res.quantity_available || 0);
                const unitPrice = Number(res.price || 0);
                const lineCost = reqQty * unitPrice;
                totalMaterialCost += lineCost;

                const deficit = Math.max(0, reqQty - avail);
                const remainingAfter = avail - reqQty;
                const status = deficit > 0 ? 'critical' : (remainingAfter < 5 ? 'warning' : 'ok');

                if (deficit > 0) {
                    errors.push(`Not enough material stock for ${res.resource_code} (${res.name}): requested ${reqQty} ${res.unit || 'units'}, only ${avail} available.`);
                } else if (remainingAfter < 5) {
                    warnings.push(`Low post-allocation stock for ${res.resource_code}: ${remainingAfter} ${res.unit || 'units'} will remain.`);
                }

                if (lineCost < 100) {
                    errors.push(`Material line cost for ${res.resource_code} (${this.formatCurrency(lineCost)}) is below the minimum ₱100.00 project cost threshold.`);
                }

                materialChecks.push({
                    id: res.id,
                    resource_code: res.resource_code,
                    name: res.name,
                    unit: res.unit || 'units',
                    unit_price: unitPrice,
                    req_qty: reqQty,
                    available: avail,
                    remaining_after: remainingAfter,
                    deficit,
                    line_cost: lineCost,
                    status,
                    stock_pct: reqQty > 0 ? Math.min(100, Math.round((avail / reqQty) * 100)) : 100
                });
            });

            // 3. Validate Project Remaining Budget Ceiling
            const contractPrice = Number(proj.contract_price || 0);
            const currentSpend = Number(proj.actual_spend || 0);
            const remainingBudgetBefore = Math.max(0, contractPrice - currentSpend);
            const remainingBudgetAfter = remainingBudgetBefore - totalMaterialCost;
            const newSpend = currentSpend + totalMaterialCost;
            const newUtilPct = contractPrice > 0 ? Math.round((newSpend / contractPrice) * 1000) / 10 : 0;

            if (totalMaterialCost > remainingBudgetBefore) {
                errors.push(`Insufficient project budget: material allocation (${this.formatCurrency(totalMaterialCost)}) exceeds ${proj.project_code}'s remaining budget (${this.formatCurrency(remainingBudgetBefore)}).`);
            } else if (newUtilPct >= 85 && totalMaterialCost > 0) {
                warnings.push(`Budget utilization will reach ${newUtilPct}% (At-Risk threshold ≥ 85%).`);
            }

            if (totalPersonnelCount === 0 && materialChecks.length === 0) {
                errors.push('Select at least one manpower role or one material item to allocate.');
            }

            const status = errors.length > 0 ? 'critical' : (warnings.length > 0 ? 'warning' : 'ok');

            return {
                hasErrors: errors.length > 0,
                errors,
                warnings,
                roleChecks,
                materialChecks,
                matchedCrew,
                totalMaterialCost,
                totalPersonnelCount,
                contractPrice,
                currentSpend,
                newSpend,
                remainingBudgetBefore,
                remainingBudgetAfter,
                newUtilPct,
                status
            };
        },

        formatCurrency(val) {
            return '₱' + Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        formatCompactCurrency(val) {
            const n = Number(val || 0);
            const abs = Math.abs(n);
            const sign = n < 0 ? '-' : '';
            if (abs >= 1000000) return sign + '₱' + (abs / 1000000).toFixed(1) + 'M';
            if (abs >= 1000) return sign + '₱' + (abs / 1000).toFixed(0) + 'K';
            return sign + '₱' + abs.toFixed(0);
        },

        badgeStyle(status) {
            if (status === 'critical') {
                return 'background: var(--color-danger-light); color: var(--color-danger); border: 1px solid rgba(192, 57, 43, 0.28);';
            }
            if (status === 'warning') {
                return 'background: var(--status-ongoing-bg); color: var(--status-ongoing-text); border: 1px solid rgba(201, 138, 44, 0.28);';
            }
            return 'background: var(--status-completed-bg); color: var(--status-completed-text); border: 1px solid rgba(46, 125, 83, 0.28);';
        },

        statusColorHex(status) {
            if (status === 'critical') return '#DC2626';
            if (status === 'warning') return '#D97706';
            return '#10B981';
        }
        };
    };
</script>

<div
    class="dss-module"
    x-data="dssModuleState()"
>
    <style>
        .dss-module .mb-6 {
            margin-bottom: 1.5rem;
        }
        .dss-module .mb-4 {
            margin-bottom: 1rem;
        }
        .dss-module .dss-tabs-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: var(--space-5);
            padding-bottom: var(--space-3);
            border-bottom: 1px solid var(--color-border);
        }
        .dss-module .dss-tabs-list {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            background: transparent;
            padding: 0;
            border: none;
        }
        .dss-module .dss-tab-btn {
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
        .dss-module .dss-toolbar-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .dss-module .dss-filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .dss-module .dss-search-box {
            position: relative;
            width: 220px;
            max-width: 100%;
            flex: 0 1 220px;
        }
        .dss-module .dss-search-box .search-input-icon {
            position: absolute;
            left: 0.65rem;
            top: 50%;
            transform: translateY(-50%);
            width: 14px;
            height: 14px;
            color: var(--color-muted);
            pointer-events: none;
        }
        .dss-module .dss-search-box input.form-control {
            width: 100% !important;
            height: 36px;
            padding: 0 0.7rem 0 2rem;
            font-size: 0.8125rem;
            border-radius: var(--radius-md);
        }
        .dss-module select.dss-select {
            width: auto !important;
            min-width: 140px;
            max-width: 240px;
            height: 36px;
            padding: 0 1.85rem 0 0.7rem;
            font-size: 0.8125rem;
            line-height: 1.2;
            display: inline-block !important;
            flex: 0 0 auto;
        }
        .dss-module .dss-btn {
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
        .dss-module .dss-collapse-btn {
            height: 30px;
            padding: 0 0.65rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: var(--radius-sm);
            background: var(--color-surface-alt);
            border: 1px solid var(--color-border);
            color: var(--color-muted);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.15s ease;
        }
        .dss-module .dss-collapse-btn:hover {
            color: var(--color-ink);
            border-color: var(--color-accent);
        }
        .dss-module .dss-risk-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.18rem 0.55rem;
            border-radius: var(--radius-pill);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
        }
        .dss-module .dss-formula-box {
            margin-top: 0.5rem;
            padding: 0.45rem 0.65rem;
            background: var(--color-surface-alt);
            border: 1px solid var(--color-border);
            border-left: 3px solid var(--color-accent);
            border-radius: var(--radius-sm);
            font-family: var(--font-mono);
            font-size: 0.72rem;
            color: var(--color-muted);
            line-height: 1.4;
        }
        /* ── 3-Bar Portfolio Chart Classes ── */
        .dss-module .dss-chart-bars-grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(56px, 1fr));
            gap: 10px;
            align-items: end;
            height: 176px;
        }
        .dss-module .dss-chart-bucket {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            height: 176px;
            cursor: pointer;
            padding: 0 4px;
            border-radius: 6px;
            transition: background-color 0.15s ease;
        }
        .dss-module .dss-chart-bucket:hover,
        .dss-module .dss-chart-bucket.is-selected {
            background-color: rgba(44, 95, 124, 0.08);
        }
        .dss-module .dss-chart-bar-triplet {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 3px;
            width: 100%;
            height: 170px;
        }
        .dss-module .dss-bar {
            flex: 1 1 0%;
            min-width: 8px;
            max-width: 16px;
            border-radius: 3px 3px 0 0;
            transition: height 0.25s ease, opacity 0.2s ease;
        }
        .dss-module .dss-bar--burn-ok {
            background: linear-gradient(180deg, #10B981 0%, #059669 100%);
        }
        .dss-module .dss-bar--burn-warn {
            background: linear-gradient(180deg, #F59E0B 0%, #D97706 100%);
        }
        .dss-module .dss-bar--burn-crit {
            background: linear-gradient(180deg, #F87171 0%, #DC2626 100%);
        }
        .dss-module .dss-bar--elapsed {
            background: linear-gradient(180deg, #60A5FA 0%, #2563EB 100%);
        }
        .dss-module .dss-bar--progress {
            background: linear-gradient(180deg, #818CF8 0%, #4F46E5 100%);
        }
        /* ── Visual Project Health Cards Grid ── */
        .dss-module .dss-projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
            gap: 1rem;
        }
        .dss-module .dss-project-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 1rem 1.15rem;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .dss-module .dss-project-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--color-accent);
        }
        .dss-module .dss-project-card.is-active {
            border: 2px solid var(--color-accent);
            background: var(--color-accent-light);
        }
        .dss-module .dss-mini-meter {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .dss-module .dss-mini-meter-track {
            width: 100%;
            height: 7px;
            background: var(--color-surface-alt);
            border-radius: 999px;
            overflow: hidden;
            border: 1px solid var(--color-border);
        }
        .dss-module .dss-mini-meter-fill {
            height: 100%;
            border-radius: 999px;
            transition: width 0.3s ease;
        }
    </style>

    {{-- Page Header --}}
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
        <div>
            <h1>Decision Support</h1>
            <p class="page-header__description">
                In-memory what-if scenario simulations for project feasibility, schedule delays, budget adjustments, and specification-driven resource allocation.
            </p>
        </div>
    </div>

    {{-- Flash Status / Validation Error Banner for Resource Allocation --}}
    @if (session('status'))
        <div class="card mb-4" style="border-left: 4px solid var(--status-completed-text); background: var(--status-completed-bg); color: var(--status-completed-text); padding: 0.85rem 1.25rem; border-radius: var(--radius-md); font-size: 0.85rem; font-weight: 600;">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error') || $errors->any())
        <div class="card mb-4" style="border-left: 4px solid var(--color-danger); background: var(--color-danger-light); color: var(--color-danger); padding: 0.85rem 1.25rem; border-radius: var(--radius-md); font-size: 0.85rem;">
            @if (session('error'))
                <div style="font-weight: 700; margin-bottom: {{ $errors->any() ? '0.35rem' : '0' }};">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <ul style="margin: 0; padding-left: 1.25rem;">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    {{-- Web Navigation Tabs (What-If Simulator vs. Generate Resource Allocation) --}}
    <div class="dss-tabs-bar" role="tablist" aria-label="Decision Support Views">
        <div class="dss-tabs-list">
            <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'simulator'"
                class="btn dss-tab-btn"
                :class="activeTab === 'simulator' ? 'btn-primary' : 'btn-secondary'"
                @click="activeTab = 'simulator'"
                id="tab-btn-dss-simulator"
            >
                <svg style="width: 15px; height: 15px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/>
                </svg>
                <span>What-If Simulator</span>
            </button>

            <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'allocation'"
                class="btn dss-tab-btn"
                :class="activeTab === 'allocation' ? 'btn-primary' : 'btn-secondary'"
                @click="activeTab = 'allocation'"
                id="tab-btn-dss-allocation"
            >
                <svg style="width: 15px; height: 15px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM14 11a1 1 0 011 1v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1a1 1 0 110-2h1v-1a1 1 0 011-1z"/>
                </svg>
                <span>Generate Resource Allocation</span>
            </button>
        </div>
    </div>

    {{-- =========================================================================
         TAB 1: WHAT-IF SCENARIO SIMULATOR (Visual Charts & Collapsible Controls)
         ========================================================================= --}}
    <div x-show="activeTab === 'simulator'" x-transition.opacity id="panel-dss-simulator">

        {{-- Simulator Mode Selector Toolbar --}}
        <div class="card card-glass mb-6" style="margin-bottom: 1.5rem;">
            <div class="card-body" style="padding: var(--space-3) var(--space-5);">
                <div class="dss-toolbar-row">
                    <div class="dss-filter-group">
                        <button
                            type="button"
                            class="btn btn-sm dss-btn"
                            :class="simMode === 'feasibility' ? 'btn-primary' : 'btn-secondary'"
                            @click="simMode = 'feasibility'"
                            id="btn-sim-mode-feasibility"
                        >
                            1. Project Feasibility ("Can we take this project?")
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm dss-btn"
                            :class="simMode === 'delay' ? 'btn-primary' : 'btn-secondary'"
                            @click="simMode = 'delay'"
                            id="btn-sim-mode-delay"
                        >
                            2. Delay Propagation ("Slip project by X days")
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm dss-btn"
                            :class="simMode === 'budget' ? 'btn-primary' : 'btn-secondary'"
                            @click="simMode = 'budget'"
                            id="btn-sim-mode-budget"
                        >
                            3. Budget / Price Adjustment ("Recalculate cost &amp; margin")
                        </button>
                    </div>

                    <span class="text-muted font-mono" style="font-size: 0.75rem;">
                        In-Memory Sandbox · Zero DB writes
                    </span>
                </div>
            </div>
        </div>

        {{-- ─────────────────────────────────────────────────────────────────────
             SIMULATOR 1: PROJECT FEASIBILITY (Visual Capacity Bars & Collapsible Inputs)
             ───────────────────────────────────────────────────────────────────── --}}
        <div x-show="simMode === 'feasibility'" id="sim-panel-feasibility">
            <div class="card card-glass mb-6">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                        <h2 class="card-header__title" style="margin: 0; font-size: 1rem;">Project Feasibility Simulator</h2>
                        <span
                            class="dss-risk-pill"
                            :style="badgeStyle(feasibilityResult.status)"
                            style="font-size: 0.76rem; padding: 0.25rem 0.75rem;"
                            x-text="`${feasibilityResult.ratingColor.toUpperCase()} · ${feasibilityResult.ratingLabel}`"
                        ></span>
                    </div>
                    <button type="button" class="dss-collapse-btn" @click="showFeasInputs = !showFeasInputs">
                        <span x-text="showFeasInputs ? '− Minimize Inputs' : '+ Configure Inputs'"></span>
                    </button>
                </div>

                <div class="card-body" style="padding: var(--space-5) var(--space-6);">
                    {{-- Collapsible Inputs Panel --}}
                    <div x-show="showFeasInputs" x-transition.opacity style="margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--color-border);">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 0.85rem; margin-bottom: 1rem;">
                            <div>
                                <label for="feas-start-date" class="form-label" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase;">Proposed Start Date</label>
                                <input type="date" id="feas-start-date" x-model="feasStartDate" class="form-control" style="height: 36px; font-size: 0.8125rem;">
                            </div>
                            <div>
                                <label for="feas-end-date" class="form-label" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase;">Target Completion Date</label>
                                <input type="date" id="feas-end-date" x-model="feasEndDate" class="form-control" style="height: 36px; font-size: 0.8125rem;">
                            </div>
                            <div>
                                <label for="feas-budget" class="form-label" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase;">Proposed Budget (₱)</label>
                                <input type="number" id="feas-budget" x-model.number="feasBudget" min="0" step="50000" class="form-control font-mono" style="height: 36px; font-size: 0.8125rem;">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem;">
                            {{-- Required Roles Builder --}}
                            <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.85rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                    <span style="font-size: 0.78rem; font-weight: 700;">Required Manpower Roles</span>
                                    <button type="button" class="btn btn-secondary btn-sm dss-btn" style="height: 28px; font-size: 0.72rem;" @click="addFeasRole()">+ Add Role</button>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                                    <template x-for="(rItem, rIdx) in feasRoles" :key="'feas-role-' + rIdx">
                                        <div style="display: flex; align-items: center; gap: 0.4rem;">
                                            <select x-model="rItem.expertise" class="form-control form-control--compact dss-select" style="flex: 1; max-width: 100%;" aria-label="Select required role expertise">
                                                <template x-for="opt in expertiseOptions" :key="'opt-' + opt">
                                                    <option :value="opt" x-text="opt"></option>
                                                </template>
                                            </select>
                                            <input type="number" x-model.number="rItem.count" min="1" max="50" class="form-control font-mono" style="width: 72px !important; height: 36px; font-size: 0.8125rem;" aria-label="Required headcount">
                                            <button type="button" class="btn btn-secondary btn-sm dss-btn" @click="removeFeasRole(rIdx)" style="color: var(--color-danger); padding: 0 0.55rem;">✕</button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Required Materials Builder --}}
                            <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.85rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                    <span style="font-size: 0.78rem; font-weight: 700;">Required Catalog Materials</span>
                                    <button type="button" class="btn btn-secondary btn-sm dss-btn" style="height: 28px; font-size: 0.72rem;" @click="addFeasMaterial()">+ Add Material</button>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                                    <template x-for="(mItem, mIdx) in feasMaterials" :key="'feas-mat-' + mIdx">
                                        <div style="display: flex; align-items: center; gap: 0.4rem;">
                                            <select x-model.number="mItem.resource_id" class="form-control form-control--compact dss-select" style="flex: 1; max-width: 100%;" aria-label="Select required catalog material">
                                                <template x-for="res in resourcesPool" :key="'res-opt-' + res.id">
                                                    <option :value="res.id" x-text="`${res.resource_code} — ${res.name} (Stock: ${res.quantity_available})`"></option>
                                                </template>
                                            </select>
                                            <input type="number" x-model.number="mItem.quantity" min="1" max="999" class="form-control font-mono" style="width: 76px !important; height: 36px; font-size: 0.8125rem;" aria-label="Required quantity">
                                            <button type="button" class="btn btn-secondary btn-sm dss-btn" @click="removeFeasMaterial(mIdx)" style="color: var(--color-danger); padding: 0 0.55rem;">✕</button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Visual Feasibility Output Charts (Manpower Capacity Bars & Material Stock Bars) --}}
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.25rem;">
                        {{-- Left Visual Chart: Manpower Role Capacity vs Requirement --}}
                        <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1rem 1.15rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                                <strong style="font-size: 0.84rem;">Manpower Role Readiness Chart</strong>
                                <span class="font-mono text-muted" style="font-size: 0.72rem;" x-text="`${feasibilityResult.totalManpowerDeficit} Deficit`"></span>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <template x-for="rg in feasibilityResult.roleGaps" :key="'vis-rg-' + rg.expertise">
                                    <div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.76rem; margin-bottom: 3px;">
                                            <strong x-text="rg.expertise"></strong>
                                            <span class="font-mono" :style="{ color: statusColorHex(rg.status) }" x-text="rg.free_by_start > 0 ? `${rg.available_now} Avail + ${rg.free_by_start} Free / ${rg.required} Req` : `${rg.available_now} Avail / ${rg.required} Req`"></span>
                                        </div>
                                        <div class="dss-mini-meter-track" style="height: 10px;">
                                            <div class="dss-mini-meter-fill" :style="{ width: rg.coverage_pct + '%', background: statusColorHex(rg.status) }"></div>
                                        </div>
                                        <div x-show="showFormulas" class="dss-formula-box" x-text="rg.formula"></div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Right Visual Chart: Material Stock vs Requirement & Budget Margin --}}
                        <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1rem 1.15rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                                <strong style="font-size: 0.84rem;">Materials Stock &amp; Budget Allocation Chart</strong>
                                <span class="font-mono font-bold" :class="feasibilityResult.remainingMargin >= 0 ? 'text-completed' : 'text-danger'" style="font-size: 0.75rem;" x-text="`Margin: ${formatCurrency(feasibilityResult.remainingMargin)}`"></span>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <template x-for="mg in feasibilityResult.materialGaps" :key="'vis-mg-' + mg.resource_code">
                                    <div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.76rem; margin-bottom: 3px;">
                                            <strong x-text="`${mg.resource_code} — ${mg.name}`"></strong>
                                            <span class="font-mono" :style="{ color: statusColorHex(mg.status) }" x-text="mg.deficit > 0 ? `Stock: ${mg.available_stock}/${mg.required_qty} (Reorder +${mg.reorder_qty})` : `Stock: ${mg.available_stock}/${mg.required_qty} OK`"></span>
                                        </div>
                                        <div class="dss-mini-meter-track" style="height: 10px;">
                                            <div class="dss-mini-meter-fill" :style="{ width: mg.stock_pct + '%', background: statusColorHex(mg.status) }"></div>
                                        </div>
                                        <div x-show="showFormulas" class="dss-formula-box" x-text="mg.formula"></div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ─────────────────────────────────────────────────────────────────────
             SIMULATOR 2: DELAY PROPAGATION (Visual Timeline Shift Chart)
             ───────────────────────────────────────────────────────────────────── --}}
        <div x-show="simMode === 'delay'" x-cloak id="sim-panel-delay">
            <template x-if="delaySimResult">
                <div class="card card-glass mb-6">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <h2 class="card-header__title" style="margin: 0; font-size: 1rem;">Delay Propagation Visual Simulator</h2>
                            <span class="card-header__subtitle" style="display: block; font-size: 0.8125rem; color: var(--color-muted); margin-top: 2px;">
                                Drag or adjust the slip days to visualize how schedule delays lock crew and impact downstream projects.
                            </span>
                        </div>
                        <span class="dss-risk-pill" :style="badgeStyle(delaySimResult.status)" x-text="`+${delaySimResult.slipDays} Days Slip Impact`"></span>
                    </div>

                    <div class="card-body" style="padding: var(--space-5) var(--space-6);">
                        <div class="dss-toolbar-row" style="margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid var(--color-border);">
                            <div class="dss-filter-group">
                                <select id="sim-delay-project" x-model="delayProjectId" class="form-control form-control--compact dss-select" style="min-width: 230px;" aria-label="Select project to delay">
                                    <template x-for="p in matrix" :key="'d-proj-' + p.id">
                                        <option :value="p.id" x-text="`${p.project_code} — ${p.name}`"></option>
                                    </template>
                                </select>

                                <input type="range" x-model.number="delaySlipDays" min="0" max="90" step="1" style="width: 140px; accent-color: var(--color-accent);" aria-label="Slip days slider">
                                <input type="number" id="sim-delay-days" x-model.number="delaySlipDays" min="0" max="365" class="form-control font-mono" style="width: 80px !important; height: 36px; font-size: 0.8125rem;" aria-label="Slip days input">

                                <button type="button" class="btn btn-secondary btn-sm dss-btn" @click="delaySlipDays = 7">+7d</button>
                                <button type="button" class="btn btn-secondary btn-sm dss-btn" @click="delaySlipDays = 14">+14d</button>
                                <button type="button" class="btn btn-secondary btn-sm dss-btn" @click="delaySlipDays = 30">+30d</button>
                            </div>
                        </div>

                        {{-- Visual Gantt-Style Timeline Shift Bar --}}
                        <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1rem 1.25rem; margin-bottom: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.78rem; margin-bottom: 6px;">
                                <strong x-text="`${delaySimResult.project.project_code} Schedule Window + Simulated Slip`"></strong>
                                <span class="font-mono font-bold text-danger" x-text="`+${delaySimResult.slipDays}d Extension (New End: ${delaySimResult.newTargetDate} | Previous end date: ${delaySimResult.origTargetDate})`"></span>
                            </div>
                            <div style="display: flex; width: 100%; height: 18px; border-radius: 999px; overflow: hidden; background: var(--color-surface); border: 1px solid var(--color-border);">
                                <div :style="{ width: (100 - delaySimResult.slipRatioPct) + '%', background: '#2563EB' }" title="Original Planned Duration"></div>
                                <div :style="{ width: delaySimResult.slipRatioPct + '%', background: 'repeating-linear-gradient(45deg, #DC2626, #DC2626 6px, #F87171 6px, #F87171 12px)' }" title="Simulated Delay Slip Window"></div>
                            </div>
                            <div x-show="showFormulas" class="dss-formula-box" style="margin-top: 0.65rem;" x-text="delaySimResult.formula"></div>
                        </div>

                        {{-- Visual Cards for Held Crew & Downstream Overlaps --}}
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem;">
                            <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1rem;">
                                <div style="font-size: 0.8rem; font-weight: 700; margin-bottom: 0.65rem;">
                                    Held Personnel (<span x-text="delaySimResult.affectedCrew.length"></span> locked until <span class="font-mono" x-text="delaySimResult.newTargetDate"></span>)
                                </div>
                                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                    <template x-if="delaySimResult.affectedCrew.length === 0">
                                        <span class="text-muted" style="font-size: 0.78rem;">No personnel currently assigned to this project.</span>
                                    </template>
                                    <template x-for="ac in delaySimResult.affectedCrew" :key="'ac-chip-' + ac.employee_id">
                                        <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.45rem 0.7rem; font-size: 0.76rem;">
                                            <span class="font-mono font-bold text-accent" x-text="ac.employee_id"></span> ·
                                            <strong x-text="ac.full_name"></strong>
                                            <span class="text-muted" x-text="`(${ac.expertise})`"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1rem;">
                                <div style="font-size: 0.8rem; font-weight: 700; margin-bottom: 0.65rem;">
                                    Downstream Overlapping Projects (<span x-text="delaySimResult.downstreamProjects.length"></span>)
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                                    <template x-if="delaySimResult.downstreamProjects.length === 0">
                                        <span class="text-muted" style="font-size: 0.78rem;">No downstream projects overlap with this slip window.</span>
                                    </template>
                                    <template x-for="dp in delaySimResult.downstreamProjects" :key="'dp-bar-' + dp.project_code">
                                        <div>
                                            <div style="display: flex; justify-content: space-between; font-size: 0.76rem; margin-bottom: 3px;">
                                                <span><strong class="font-mono text-accent" x-text="dp.project_code"></strong> <span x-text="dp.name"></span></span>
                                                <span class="font-mono text-danger font-bold" x-text="`${dp.overlap_days}d overlap`"></span>
                                            </div>
                                            <div class="dss-mini-meter-track">
                                                <div class="dss-mini-meter-fill" :style="{ width: dp.overlap_pct + '%', background: '#DC2626' }"></div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- ─────────────────────────────────────────────────────────────────────
             SIMULATOR 3: BUDGET / PRICE ADJUSTMENT (Visual 3-Bar Comparison Chart)
             ───────────────────────────────────────────────────────────────────── --}}
        <div x-show="simMode === 'budget'" x-cloak id="sim-panel-budget">
            <template x-if="budgetSimResult">
                <div class="card card-glass mb-6">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <h2 class="card-header__title" style="margin: 0; font-size: 1rem;">Budget &amp; Price Adjustment Visual Simulator</h2>
                            <span class="card-header__subtitle" style="display: block; font-size: 0.8125rem; color: var(--color-muted); margin-top: 2px;">
                                Adjust material price % or labor/other cost % to visually compare Current vs. Simulated Spend and Margin.
                            </span>
                        </div>
                        <span class="dss-risk-pill" :style="badgeStyle(budgetSimResult.status)" x-text="`Simulated: ${budgetSimResult.healthLabel} (${budgetSimResult.simUtilPct}%)`"></span>
                    </div>

                    <div class="card-body" style="padding: var(--space-5) var(--space-6);">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--color-border);">
                            <div>
                                <label for="sim-budget-project" class="form-label" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase;">Select Project</label>
                                <select id="sim-budget-project" x-model="budgetProjectId" class="form-control form-control--compact dss-select" style="width: 100% !important; max-width: 100%;">
                                    <template x-for="p in matrix" :key="'b-proj-' + p.id">
                                        <option :value="p.id" x-text="`${p.project_code} — ${p.name}`"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label for="sim-mat-delta" class="form-label" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase;">Material Price Change (<span class="font-mono" x-text="`${simMaterialDeltaPct >= 0 ? '+' : ''}${simMaterialDeltaPct}%`"></span>)</label>
                                <input type="range" id="sim-mat-delta" x-model.number="simMaterialDeltaPct" min="-40" max="100" step="5" style="width: 100%; accent-color: var(--color-accent);">
                            </div>
                            <div>
                                <label for="sim-other-delta" class="form-label" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase;">Labor / Other Change (<span class="font-mono" x-text="`${simOtherDeltaPct >= 0 ? '+' : ''}${simOtherDeltaPct}%`"></span>)</label>
                                <input type="range" id="sim-other-delta" x-model.number="simOtherDeltaPct" min="-40" max="100" step="5" style="width: 100%; accent-color: var(--color-accent);">
                            </div>
                            <div>
                                <label for="sim-contingency" class="form-label" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase;">Extra Contingency (₱)</label>
                                <input type="number" id="sim-contingency" x-model.number="simContingencyPhp" min="0" step="10000" class="form-control font-mono" style="height: 36px; font-size: 0.8125rem;">
                            </div>
                        </div>

                        {{-- Visual Horizontal Bar Chart: Contract Budget vs Current Spend vs Simulated Spend --}}
                        <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1.15rem 1.35rem; display: flex; flex-direction: column; gap: 1rem;">
                            <div>
                                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 4px;">
                                    <strong>Total Contract Value (Ceiling)</strong>
                                    <span class="font-mono font-bold text-completed" x-text="formatCurrency(budgetSimResult.contractPrice)"></span>
                                </div>
                                <div class="dss-mini-meter-track" style="height: 14px;">
                                    <div class="dss-mini-meter-fill" :style="{ width: Math.min(100, Math.round((budgetSimResult.contractPrice / budgetSimResult.chartMax) * 100)) + '%', background: '#10B981' }"></div>
                                </div>
                            </div>

                            <div>
                                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 4px;">
                                    <strong>Current Actual Spend (<span x-text="`${budgetSimResult.origUtilPct}%`"></span>)</strong>
                                    <span class="font-mono font-bold" style="color: #2563EB;" x-text="`${formatCurrency(budgetSimResult.origTotalSpend)} (Rem: ${formatCurrency(budgetSimResult.origRemaining)})`"></span>
                                </div>
                                <div class="dss-mini-meter-track" style="height: 14px;">
                                    <div class="dss-mini-meter-fill" :style="{ width: Math.min(100, Math.round((budgetSimResult.origTotalSpend / budgetSimResult.chartMax) * 100)) + '%', background: '#2563EB' }"></div>
                                </div>
                            </div>

                            <div>
                                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 4px;">
                                    <strong>Simulated Spend (<span x-text="`${budgetSimResult.simUtilPct}%`"></span>)</strong>
                                    <span class="font-mono font-bold" :style="{ color: statusColorHex(budgetSimResult.status) }" x-text="`${formatCurrency(budgetSimResult.simTotalSpend)} (Simulated Margin: ${formatCurrency(budgetSimResult.simRemaining)})`"></span>
                                </div>
                                <div class="dss-mini-meter-track" style="height: 14px;">
                                    <div class="dss-mini-meter-fill" :style="{ width: Math.min(100, Math.round((budgetSimResult.simTotalSpend / budgetSimResult.chartMax) * 100)) + '%', background: statusColorHex(budgetSimResult.status) }"></div>
                                </div>
                            </div>
                        </div>

                        <div x-show="showFormulas" class="dss-formula-box" style="margin-top: 0.85rem;" x-text="budgetSimResult.formula"></div>
                    </div>
                </div>
            </template>
        </div>

    </div>

    {{-- =========================================================================
         TAB 2: GENERATE RESOURCE ALLOCATION (Spec-Driven Template & Auto-Assign)
         ========================================================================= --}}
    <div x-show="activeTab === 'allocation'" x-cloak x-transition.opacity id="panel-dss-allocation">
        <template x-if="activeAllocProject">
            <form method="POST" action="{{ route('decision-support.allocate') }}">
                @csrf
                <input type="hidden" name="project_id" :value="activeAllocProject.id">
                <input type="hidden" name="template_name" :value="allocTemplateName">

                {{-- Card 1: Project Selection, Elevator Technical Specs & Scenario Templates --}}
                <div class="card card-glass mb-6" style="margin-bottom: 1.5rem;">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                        <div>
                            <h2 class="card-header__title" style="margin: 0; font-size: 1rem;">Project Specification &amp; Scenario Template Selector</h2>
                            <span class="card-header__subtitle" style="display: block; font-size: 0.8125rem; color: var(--color-muted); margin-top: 2px;">
                                Select a target project to inspect its elevator specifications, apply or save a material/manpower scenario template, and allocate directly to the project.
                            </span>
                        </div>
                        <div class="dss-filter-group">
                            <span class="dss-risk-pill" :style="badgeStyle(allocValidation.status)" x-text="allocValidation.hasErrors ? `${allocValidation.errors.length} Validation Block(s)` : 'Pre-Flight Validated · Ready'"></span>
                            <button type="button" class="dss-collapse-btn" @click="showAllocSpecs = !showAllocSpecs">
                                <span x-text="showAllocSpecs ? '− Minimize Specs' : '+ Expand Specs'"></span>
                            </button>
                        </div>
                    </div>

                    <div class="card-body" style="padding: var(--space-4) var(--space-5);">
                        {{-- Row 1: Target Project + Template Preset + Auto-Generate + Save Template --}}
                        <div class="dss-toolbar-row" style="margin-bottom: 1rem;">
                            <div class="dss-filter-group" style="flex: 1;">
                                <div>
                                    <label for="alloc-project-select" class="form-label" style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; margin-bottom: 3px; display: block;">Target Project</label>
                                    <select
                                        id="alloc-project-select"
                                        x-model="allocProjectId"
                                        @change="applySpecTemplate('auto_spec')"
                                        class="form-control form-control--compact dss-select"
                                        style="min-width: 260px;"
                                    >
                                        <template x-for="p in matrix" :key="'alloc-p-' + p.id">
                                            <option :value="p.id" x-text="`${p.project_code} — ${p.name} (${p.project_status.toUpperCase()})`"></option>
                                        </template>
                                    </select>
                                </div>

                                <div>
                                    <label for="alloc-preset-select" class="form-label" style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; margin-bottom: 3px; display: block;">Material &amp; Manpower Scenario Template</label>
                                    <select
                                        id="alloc-preset-select"
                                        x-model="allocPreset"
                                        @change="applySpecTemplate()"
                                        class="form-control form-control--compact dss-select"
                                        style="min-width: 260px;"
                                    >
                                        <option value="auto_spec">⚡ Auto-Derived from Project Specifications</option>
                                        <option value="low_rise">Low-Rise Passenger Template (2–5 Stops)</option>
                                        <option value="mid_rise">Mid-Rise Commercial Template (6–12 Stops)</option>
                                        <option value="high_rise">High-Rise / Heavy Freight Template (13+ Stops)</option>
                                        <template x-for="tpl in customTemplates" :key="'custom-opt-' + tpl.id">
                                            <option :value="'custom:' + tpl.id" x-text="`★ Custom: ${tpl.name} (${tpl.spec_hint})`"></option>
                                        </template>
                                    </select>
                                </div>

                                <div style="align-self: flex-end; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <button
                                        type="button"
                                        class="btn btn-secondary btn-sm dss-btn"
                                        @click="applySpecTemplate('auto_spec')"
                                        title="Re-generate recommended manpower and materials from project elevator specs"
                                    >
                                        <span>⚡ Match Project Specs</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-secondary btn-sm dss-btn"
                                        @click="showTemplateDrawer = !showTemplateDrawer"
                                    >
                                        <span x-text="showTemplateDrawer ? 'Cancel Template Save' : '+ Save as Custom Template'"></span>
                                    </button>
                                </div>
                            </div>

                            <div style="align-self: flex-end;">
                                <a :href="activeAllocProject.show_url" class="btn btn-secondary btn-sm dss-btn">
                                    <span>View Project Record &rarr;</span>
                                </a>
                            </div>
                        </div>

                        {{-- Optional Save Custom Template Drawer --}}
                        <div x-show="showTemplateDrawer" x-cloak x-transition.opacity style="background: var(--color-surface-alt); border: 1px dashed var(--color-accent); border-radius: var(--radius-md); padding: 0.85rem 1rem; margin-bottom: 1rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;">
                                <div style="display: flex; align-items: center; gap: 0.65rem; flex: 1; min-width: 260px;">
                                    <input
                                        type="text"
                                        x-model="newCustomTemplateName"
                                        placeholder="Enter reusable scenario template name (e.g., 10-Stop Passenger Standard Kit)..."
                                        class="form-control"
                                        style="height: 36px; font-size: 0.8125rem; flex: 1;"
                                    >
                                    <button type="button" class="btn btn-primary btn-sm dss-btn" @click="saveCustomTemplate()">
                                        <span>Save Scenario Template</span>
                                    </button>
                                </div>
                                <template x-if="customTemplates.length > 0">
                                    <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                                        <span class="text-muted" style="font-size: 0.74rem;">Saved:</span>
                                        <template x-for="tpl in customTemplates" :key="'saved-chip-' + tpl.id">
                                            <span class="badge badge-neutral" style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.72rem;">
                                                <span x-text="tpl.name"></span>
                                                <button type="button" @click="deleteCustomTemplate(tpl.id)" style="background: none; border: none; color: var(--color-danger); cursor: pointer; font-weight: 700; padding: 0 2px;" title="Delete template">&times;</button>
                                            </span>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Collapsible Elevator Technical Specifications Strip --}}
                        <div x-show="showAllocSpecs" x-transition.opacity style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.9rem 1.1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem; flex-wrap: wrap; gap: 0.5rem;">
                                <div style="font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--color-muted);">
                                    Elevator Technical Specifications · <span class="text-accent font-mono" x-text="activeAllocProject.project_code"></span>
                                </div>
                                <div style="font-size: 0.76rem;">
                                    Active Template: <strong class="font-mono text-accent" x-text="allocTemplateName"></strong>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(135px, 1fr)); gap: 0.65rem;">
                                <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 0.5rem 0.7rem;">
                                    <div style="font-size: 0.66rem; text-transform: uppercase; color: var(--color-muted); font-weight: 700;">Elevator Type</div>
                                    <div style="font-size: 0.82rem; font-weight: 700; margin-top: 2px;" x-text="activeAllocProject.specs?.project_type || 'Passenger Elevator'"></div>
                                </div>
                                <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 0.5rem 0.7rem;">
                                    <div style="font-size: 0.66rem; text-transform: uppercase; color: var(--color-muted); font-weight: 700;">Floors / Stops</div>
                                    <div class="font-mono" style="font-size: 0.84rem; font-weight: 800; margin-top: 2px;" x-text="`${activeAllocProject.specs?.no_of_stops || 0} Stops`"></div>
                                </div>
                                <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 0.5rem 0.7rem;">
                                    <div style="font-size: 0.66rem; text-transform: uppercase; color: var(--color-muted); font-weight: 700;">Rated Capacity</div>
                                    <div class="font-mono" style="font-size: 0.82rem; font-weight: 700; margin-top: 2px;" x-text="activeAllocProject.specs?.rated_capacity || '—'"></div>
                                </div>
                                <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 0.5rem 0.7rem;">
                                    <div style="font-size: 0.66rem; text-transform: uppercase; color: var(--color-muted); font-weight: 700;">Lifting Speed</div>
                                    <div class="font-mono" style="font-size: 0.82rem; font-weight: 700; margin-top: 2px;" x-text="activeAllocProject.specs?.lifting_speed || '—'"></div>
                                </div>
                                <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 0.5rem 0.7rem;">
                                    <div style="font-size: 0.66rem; text-transform: uppercase; color: var(--color-muted); font-weight: 700;">Door &amp; Control</div>
                                    <div style="font-size: 0.78rem; font-weight: 700; margin-top: 2px;" x-text="`${activeAllocProject.specs?.door_operation || 'Center'} · ${activeAllocProject.specs?.operation || 'Simplex'}`"></div>
                                </div>
                                <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 0.5rem 0.7rem;">
                                    <div style="font-size: 0.66rem; text-transform: uppercase; color: var(--color-muted); font-weight: 700;">Remaining Budget</div>
                                    <div class="font-mono" style="font-size: 0.84rem; font-weight: 800; margin-top: 2px;" :class="allocValidation.remainingBudgetBefore > 0 ? 'text-completed' : 'text-danger'" x-text="formatCurrency(allocValidation.remainingBudgetBefore)"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Two-Column Builder: Manpower Assignment + Material Assignment Template --}}
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">

                    {{-- Left Column: Manpower Resource Allocation Builder --}}
                    <div class="card card-glass">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                            <div>
                                <h3 class="card-header__title" style="margin: 0; font-size: 0.95rem;">Manpower Allocation Builder</h3>
                                <span class="card-header__subtitle" style="display: block; font-size: 0.76rem; color: var(--color-muted); margin-top: 2px;">
                                    Auto-ranks unassigned personnel by lowest historical workload.
                                </span>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm dss-btn" @click="addAllocRole()">+ Add Role</button>
                        </div>

                        <div class="card-body" style="padding: var(--space-4) var(--space-5); display: flex; flex-direction: column; gap: 0.9rem;">
                            {{-- Missing Core Roles Hint --}}
                            <template x-if="(activeAllocProject.manpower?.missing_roles?.length || 0) > 0">
                                <div style="background: var(--status-ongoing-bg); color: var(--status-ongoing-text); border: 1px solid rgba(201, 138, 44, 0.28); border-radius: var(--radius-md); padding: 0.55rem 0.85rem; font-size: 0.76rem;">
                                    <strong>Missing Core Roles on Project:</strong>
                                    <span x-text="activeAllocProject.manpower.missing_roles.join(', ')"></span>
                                </div>
                            </template>

                            <template x-if="allocRoles.length === 0">
                                <div class="text-muted" style="font-size: 0.8rem; text-align: center; padding: 1rem; border: 1px dashed var(--color-border); border-radius: var(--radius-md);">
                                    No new manpower roles queued for this allocation. Click <strong>+ Add Role</strong> to assign crew.
                                </div>
                            </template>

                            {{-- Role Rows --}}
                            <div style="display: flex; flex-direction: column; gap: 0.55rem;">
                                <template x-for="(role, rIdx) in allocRoles" :key="'alloc-role-' + rIdx">
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <select
                                            :name="`roles[${rIdx}][expertise]`"
                                            x-model="role.expertise"
                                            class="form-control form-control--compact dss-select"
                                            style="flex: 1;"
                                            aria-label="Manpower role expertise"
                                        >
                                            <template x-for="opt in expertiseOptions" :key="'alloc-exp-' + opt">
                                                <option :value="opt" x-text="opt"></option>
                                            </template>
                                        </select>
                                        <input
                                            type="number"
                                            :name="`roles[${rIdx}][count]`"
                                            x-model.number="role.count"
                                            min="1"
                                            max="20"
                                            class="form-control font-mono"
                                            style="width: 82px !important; height: 36px; font-size: 0.8125rem;"
                                            aria-label="Required headcount"
                                        >
                                        <button type="button" class="btn btn-secondary btn-sm dss-btn" @click="removeAllocRole(rIdx)" title="Remove role">&times;</button>
                                    </div>
                                </template>
                            </div>

                            {{-- Live Manpower Pool Validation Bars & Auto-Selected Personnel Preview --}}
                            <div style="border-top: 1px solid var(--color-border); padding-top: 0.85rem; display: flex; flex-direction: column; gap: 0.65rem;">
                                <div style="font-size: 0.74rem; font-weight: 700; text-transform: uppercase; color: var(--color-muted);">
                                    Live Pool Availability &amp; Auto-Matched Crew (<span x-text="allocValidation.totalPersonnelCount"></span> requested)
                                </div>

                                <template x-for="rc in allocValidation.roleChecks" :key="'rc-bar-' + rc.expertise">
                                    <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.65rem 0.85rem;">
                                        <div style="display: flex; justify-content: space-between; font-size: 0.78rem; margin-bottom: 4px;">
                                            <strong x-text="rc.expertise"></strong>
                                            <span class="font-mono font-bold" :style="{ color: statusColorHex(rc.status) }" x-text="rc.deficit > 0 ? `Deficit: −${rc.deficit} (Avail: ${rc.available}/${rc.needed})` : `Available: ${rc.available} pool / ${rc.needed} req`"></span>
                                        </div>
                                        <div class="dss-mini-meter-track">
                                            <div class="dss-mini-meter-fill" :style="{ width: rc.coverage_pct + '%', background: statusColorHex(rc.status) }"></div>
                                        </div>
                                        <div style="font-size: 0.72rem; color: var(--color-muted); margin-top: 4px;">
                                            <template x-if="rc.picked_names">
                                                <span>Auto-Assigning: <strong class="text-main" x-text="rc.picked_names"></strong></span>
                                            </template>
                                            <template x-if="!rc.picked_names">
                                                <span class="text-danger font-bold">No unassigned personnel with this expertise available in pool.</span>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Right Column: Automatic Material Assignment Template Builder --}}
                    <div class="card card-glass">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                            <div>
                                <h3 class="card-header__title" style="margin: 0; font-size: 0.95rem;">Material Assignment Template Builder</h3>
                                <span class="card-header__subtitle" style="display: block; font-size: 0.76rem; color: var(--color-muted); margin-top: 2px;">
                                    Deducts catalog stock and logs project material costs automatically.
                                </span>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm dss-btn" @click="addAllocMaterial()">+ Add Material</button>
                        </div>

                        <div class="card-body" style="padding: var(--space-4) var(--space-5); display: flex; flex-direction: column; gap: 0.9rem;">
                            <template x-if="allocMaterials.length === 0">
                                <div class="text-muted" style="font-size: 0.8rem; text-align: center; padding: 1rem; border: 1px dashed var(--color-border); border-radius: var(--radius-md);">
                                    No materials in this scenario template. Click <strong>+ Add Material</strong> to include catalog items.
                                </div>
                            </template>

                            {{-- Material Rows --}}
                            <div style="display: flex; flex-direction: column; gap: 0.55rem;">
                                <template x-for="(mat, mIdx) in allocMaterials" :key="'alloc-mat-' + mIdx">
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <select
                                            :name="`materials[${mIdx}][resource_id]`"
                                            x-model.number="mat.resource_id"
                                            class="form-control form-control--compact dss-select"
                                            style="flex: 1;"
                                            aria-label="Material item"
                                        >
                                            <template x-for="res in resourcesPool" :key="'alloc-res-' + res.id">
                                                <option :value="res.id" x-text="`${res.resource_code} — ${res.name} (Stock: ${res.quantity_available} ${res.unit || 'units'} · ₱${Number(res.price).toLocaleString()})`"></option>
                                            </template>
                                        </select>
                                        <input
                                            type="number"
                                            :name="`materials[${mIdx}][quantity]`"
                                            x-model.number="mat.quantity"
                                            min="1"
                                            max="9999"
                                            class="form-control font-mono"
                                            style="width: 88px !important; height: 36px; font-size: 0.8125rem;"
                                            aria-label="Material quantity"
                                        >
                                        <button type="button" class="btn btn-secondary btn-sm dss-btn" @click="removeAllocMaterial(mIdx)" title="Remove material">&times;</button>
                                    </div>
                                </template>
                            </div>

                            {{-- Live Material Stock & Cost Validation Bars --}}
                            <div style="border-top: 1px solid var(--color-border); padding-top: 0.85rem; display: flex; flex-direction: column; gap: 0.65rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.74rem; font-weight: 700; text-transform: uppercase; color: var(--color-muted);">
                                    <span>Catalog Stock Sufficiency</span>
                                    <span class="font-mono text-main" x-text="`Total Template Cost: ${formatCurrency(allocValidation.totalMaterialCost)}`"></span>
                                </div>

                                <template x-for="mc in allocValidation.materialChecks" :key="'mc-bar-' + mc.id">
                                    <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.65rem 0.85rem;">
                                        <div style="display: flex; justify-content: space-between; font-size: 0.78rem; margin-bottom: 4px;">
                                            <span><strong class="font-mono text-accent" x-text="mc.resource_code"></strong> <span x-text="mc.name"></span></span>
                                            <span class="font-mono font-bold" :style="{ color: statusColorHex(mc.status) }" x-text="mc.deficit > 0 ? `Shortage: −${mc.deficit} ${mc.unit}` : `${mc.req_qty} / ${mc.available} ${mc.unit} (${formatCurrency(mc.line_cost)})`"></span>
                                        </div>
                                        <div class="dss-mini-meter-track">
                                            <div class="dss-mini-meter-fill" :style="{ width: mc.stock_pct + '%', background: statusColorHex(mc.status) }"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Strict Validation Pre-Flight Summary & Submit Action Card --}}
                <div class="card card-glass mb-6">
                    <div class="card-body" style="padding: var(--space-4) var(--space-5);">
                        {{-- Budget Impact Visual Bar --}}
                        <div style="margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 5px; flex-wrap: wrap; gap: 0.5rem;">
                                <span>
                                    <strong>Post-Allocation Project Budget Impact</strong>
                                    <span class="text-muted" x-text="`(Contract: ${formatCurrency(allocValidation.contractPrice)} · Current Spend: ${formatCurrency(allocValidation.currentSpend)})`"></span>
                                </span>
                                <span class="font-mono font-bold" :style="{ color: statusColorHex(allocValidation.status) }" x-text="`New Spend: ${formatCurrency(allocValidation.newSpend)} (${allocValidation.newUtilPct}%) · Remaining After: ${formatCurrency(allocValidation.remainingBudgetAfter)}`"></span>
                            </div>
                            <div class="dss-mini-meter-track" style="height: 11px;">
                                <div class="dss-mini-meter-fill" :style="{ width: Math.min(100, allocValidation.newUtilPct) + '%', background: statusColorHex(allocValidation.status) }"></div>
                            </div>
                        </div>

                        {{-- Strict Validation Errors (Blocks Submission) --}}
                        <template x-if="allocValidation.hasErrors">
                            <div style="background: var(--color-danger-light); border: 1px solid rgba(192, 57, 43, 0.35); color: var(--color-danger); border-radius: var(--radius-md); padding: 0.85rem 1.1rem; margin-bottom: 1rem; font-size: 0.8125rem;">
                                <div style="font-weight: 700; margin-bottom: 0.35rem;">Strict Allocation Guardrails Triggered — Cannot Allocate Until Resolved:</div>
                                <ul style="margin: 0; padding-left: 1.25rem;">
                                    <template x-for="(err, eIdx) in allocValidation.errors" :key="'alloc-err-' + eIdx">
                                        <li x-text="err"></li>
                                    </template>
                                </ul>
                            </div>
                        </template>

                        {{-- Non-Blocking Warnings --}}
                        <template x-if="!allocValidation.hasErrors && allocValidation.warnings.length > 0">
                            <div style="background: var(--status-ongoing-bg); border: 1px solid rgba(201, 138, 44, 0.35); color: var(--status-ongoing-text); border-radius: var(--radius-md); padding: 0.75rem 1.1rem; margin-bottom: 1rem; font-size: 0.8rem;">
                                <template x-for="(warn, wIdx) in allocValidation.warnings" :key="'alloc-warn-' + wIdx">
                                    <div x-text="`⚠ ${warn}`"></div>
                                </template>
                            </div>
                        </template>

                        {{-- Action Row --}}
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                            <div style="font-size: 0.8rem; color: var(--color-muted);">
                                Allocating <strong class="font-mono text-main" x-text="`${allocValidation.totalPersonnelCount} personnel`"></strong>
                                and <strong class="font-mono text-main" x-text="`${allocValidation.materialChecks.length} material item(s) (${formatCurrency(allocValidation.totalMaterialCost)})`"></strong>
                                directly to <strong class="font-mono text-accent" x-text="activeAllocProject.project_code"></strong>.
                            </div>

                            <button
                                type="submit"
                                class="btn btn-primary dss-btn"
                                :disabled="allocValidation.hasErrors"
                                :style="allocValidation.hasErrors ? 'opacity: 0.55; cursor: not-allowed;' : ''"
                                id="btn-submit-resource-allocation"
                            >
                                <svg style="width: 15px; height: 15px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Allocate Resource to Project</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </template>
    </div>
</div>
@endsection
