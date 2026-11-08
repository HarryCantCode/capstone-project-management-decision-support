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

                const status = deficit > 0 ? 'critical' : ((avail - reqQty) <= 8 ? 'warning' : 'ok');
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

        /* ── Tab 2C: Visual Supplier Comparison Node Canvas (Interactive Drag & Wire) ── */
        @php
            $firstCatalogRes = collect($resourcesForSim)->first();
            $secondCatalogRes = collect($resourcesForSim)->skip(1)->first() ?? $firstCatalogRes;
            $defaultResId = $firstCatalogRes['id'] ?? ($resourcesForSim[0]['id'] ?? null);
            $defaultResCode = $firstCatalogRes['resource_code'] ?? 'MAT-001';
            $defaultResName = $firstCatalogRes ? ($firstCatalogRes['resource_code'] . ' — ' . $firstCatalogRes['name']) : 'Catalog Material';
            // Base catalog cost on unit_cost (how much the item costs from the supplier)
            $defaultResPrice = $firstCatalogRes ? (float) ($firstCatalogRes['unit_cost'] ?: $firstCatalogRes['price'] ?: 0) : 0;

            $secondResId = $secondCatalogRes['id'] ?? $defaultResId;
            $secondResName = $secondCatalogRes ? ($secondCatalogRes['resource_code'] . ' — ' . $secondCatalogRes['name']) : $defaultResName;
            $secondResPrice = $secondCatalogRes ? (float) ($secondCatalogRes['unit_cost'] ?: $secondCatalogRes['price'] ?: 0) : $defaultResPrice;
        @endphp
        supplierTargetProjectId: {{ $selectedProject ? (int) $selectedProject->id : ($matrix[0]['id'] ?? 'null') }},
        supplierSelectedResourceId: {{ $defaultResId ? (int) $defaultResId : 'null' }},
        supplierMaterialName: @js($defaultResName),
        supplierCatalogPrice: {{ $defaultResPrice }},
        supplierRequiredQty: 2,
        supplierOrderDate: '{{ now()->toDateString() }}',
        supplierRequiredDate: '{{ now()->addDays(25)->toDateString() }}',
        supplierShowCreateModal: false,
        supplierShowPickModal: false,
        supplierShowMaterialsModal: false,
        supplierSavedToast: false,
        canvasConnectionError: '',

        // Per-material comparison section settings (Project, Qty, Order Date, Needed By) multiplied per compared material
        sectionConfigs: {},

        // Selected supplier & inline editing state inside "Choose From Existing Profiles" / Materials modal
        selectedSupplierInModal: '',
        editingPresetId: null,
        editPresetForm: {
            id: null,
            name: '',
            resource_id: null,
            material_name: '',
            unit_price: 0,
            lead_time_days: 14,
            payment_terms: 'Net 30'
        },

        // Form states for creating custom supplier (includes choosing from Materials Catalog & existing suppliers)
        newSupplierForm: {
            selected_existing_supplier: '',
            resource_id: {{ $defaultResId ? (int) $defaultResId : 'null' }},
            material_name: @js($defaultResName),
            name: '',
            unit_price: {{ $defaultResPrice > 0 ? $defaultResPrice : 150000 }},
            shipping_fee: 0,
            lead_time_days: 14,
            payment_terms: 'Net 30'
        },

        // Saved library of supplier material profiles (each supplier can store multiple materials for comparison)
        existingSupplierLibrary: [
            { id: 'lib-1', resource_id: {{ $defaultResId ? (int) $defaultResId : 'null' }}, material_name: @js($defaultResName), name: 'Fuji Elevator Components Corp.', unit_price: {{ $defaultResPrice > 0 ? round($defaultResPrice * 1.04) : 185000 }}, shipping_fee: 0, lead_time_days: 10, payment_terms: '30 Days Net' },
            { id: 'lib-2', resource_id: {{ $defaultResId ? (int) $defaultResId : 'null' }}, material_name: @js($defaultResName), name: 'Orient Hoist & Industrial Direct', unit_price: {{ $defaultResPrice > 0 ? round($defaultResPrice * 0.92) : 142000 }}, shipping_fee: 0, lead_time_days: 35, payment_terms: '50% DP / 50% Delivery' },
            { id: 'lib-3', resource_id: {{ $secondResId ? (int) $secondResId : 'null' }}, material_name: @js($secondResName), name: 'Metro Manila Hoist & Elevator Supplies', unit_price: {{ $secondResPrice > 0 ? round($secondResPrice * 1.06) : 210000 }}, shipping_fee: 0, lead_time_days: 4, payment_terms: 'COD / 7 Days' },
            { id: 'lib-4', resource_id: {{ $secondResId ? (int) $secondResId : 'null' }}, material_name: @js($secondResName), name: 'Apex Shaft Components & Cables', unit_price: {{ $secondResPrice > 0 ? round($secondResPrice * 0.95) : 165000 }}, shipping_fee: 0, lead_time_days: 16, payment_terms: '15 Days Net' },
            { id: 'lib-5', resource_id: {{ $defaultResId ? (int) $defaultResId : 'null' }}, material_name: @js($defaultResName), name: 'Global Lift Inverters & Electronics', unit_price: {{ $defaultResPrice > 0 ? round($defaultResPrice * 0.98) : 198000 }}, shipping_fee: 0, lead_time_days: 7, payment_terms: '30 Days Net' }
        ],

        // Active Supplier Node Profiles placed on the canvas (Starts with Profile A based on current catalog unit cost)
        supplierNodes: [
            {
                id: 'node-catalog-a',
                name: 'Profile A',
                is_baseline: true,
                resource_id: {{ $defaultResId ? (int) $defaultResId : 'null' }},
                material_name: @js($defaultResName),
                supplier_name: @js(($firstCatalogRes['name'] ?? 'Current Catalog Material') . ' (Catalog)'),
                unit_price: {{ $defaultResPrice }},
                shipping_fee: 0,
                lead_time_days: 14,
                payment_terms: 'Current Catalog Unit Cost',
                x: 65,
                y: 110,
                w: 250,
                h: 232
            }
        ],

        // Active Connections (wires) between nodes: array of { from: 'node-catalog-a', to: '...' }
        supplierWires: [],

        // Dragging, Zooming & Point-Click Wire Connection Interactions
        canvasZoom: 1,
        canvasPanX: 0,
        canvasPanY: 0,
        isPanning: false,
        panStartX: 0,
        panStartY: 0,

        dragNodeId: null,
        dragOffsetX: 0,
        dragOffsetY: 0,

        selectedPortNodeId: null,
        selectedPortSide: 'right',
        wireMousePos: { x: 0, y: 0 },

        get uniqueSavedSuppliers() {
            const names = [];
            const seen = new Set();
            for (const item of this.existingSupplierLibrary) {
                const n = (item.name || '').trim();
                if (n && !seen.has(n.toLowerCase())) {
                    seen.add(n.toLowerCase());
                    names.push(n);
                }
            }
            return names;
        },

        get supplierProfilesSummary() {
            const groups = [];
            const map = new Map();
            for (const item of this.existingSupplierLibrary) {
                const sName = (item.name || 'Unnamed Supplier').trim();
                const key = sName.toLowerCase();
                if (!map.has(key)) {
                    const group = {
                        key,
                        supplierName: sName,
                        count: 0,
                        previewTerms: item.payment_terms || 'Net 30',
                        previewLead: item.lead_time_days || 14
                    };
                    map.set(key, group);
                    groups.push(group);
                }
                map.get(key).count += 1;
            }
            return groups;
        },

        get selectedSupplierItems() {
            const target = (this.selectedSupplierInModal || '').trim().toLowerCase();
            if (!target) return [];
            return this.existingSupplierLibrary.filter(
                item => (item.name || '').trim().toLowerCase() === target
            );
        },

        openPickSupplierModal() {
            this.syncCanvasNodesToLibrary(false);
            this.editingPresetId = null;
            this.supplierShowMaterialsModal = false;
            this.supplierShowPickModal = true;
        },

        openSupplierMaterialsModal(supplierName) {
            this.selectedSupplierInModal = (supplierName || '').trim();
            this.editingPresetId = null;
            this.supplierShowPickModal = false;
            this.supplierShowMaterialsModal = true;
        },

        backToSupplierProfileListModal() {
            this.editingPresetId = null;
            this.supplierShowMaterialsModal = false;
            this.supplierShowPickModal = true;
        },

        selectSupplierInModal(supplierName) {
            this.selectedSupplierInModal = (supplierName || '').trim();
            this.editingPresetId = null;
        },

        upsertSupplierLibraryEntry(entryData, putAtTop = true) {
            const cleanName = (entryData.name || '').trim();
            if (!cleanName) return;
            const res = this.getCatalogResourceById(entryData.resource_id);
            const resId = res ? res.id : (entryData.resource_id || null);
            const matLabel = entryData.material_name || this.formatMaterialLabel(res);

            const list = [...this.existingSupplierLibrary];
            const idx = list.findIndex(item =>
                (item.name || '').trim().toLowerCase() === cleanName.toLowerCase() &&
                Number(item.resource_id) === Number(resId)
            );

            const record = {
                id: idx >= 0 ? list[idx].id : (entryData.id || ('lib-' + Date.now() + '-' + Math.floor(Math.random() * 1000))),
                name: cleanName,
                resource_id: resId,
                material_name: matLabel,
                unit_price: Number(entryData.unit_price) || 0,
                shipping_fee: Number(entryData.shipping_fee) || 0,
                lead_time_days: Number(entryData.lead_time_days) || 14,
                payment_terms: (entryData.payment_terms || '').trim() || 'Net 30'
            };

            if (idx >= 0) {
                if (putAtTop) {
                    list.splice(idx, 1);
                    list.unshift(record);
                } else {
                    list[idx] = record;
                }
            } else {
                if (putAtTop) {
                    list.unshift(record);
                } else {
                    list.push(record);
                }
            }

            this.existingSupplierLibrary = list;
        },

        syncCanvasNodesToLibrary(persistAfter = false) {
            for (const node of this.supplierNodes) {
                if (!node || node.is_baseline) continue;
                const supName = (node.supplier_name || '').trim();
                if (!supName) continue;
                this.upsertSupplierLibraryEntry({
                    name: supName,
                    resource_id: node.resource_id,
                    material_name: node.material_name,
                    unit_price: node.unit_price,
                    shipping_fee: node.shipping_fee || 0,
                    lead_time_days: node.lead_time_days || 14,
                    payment_terms: node.payment_terms || 'Net 30'
                }, false);
            }
            if (persistAfter) {
                this.persistCurrentMaterialProfiles(false);
            }
        },

        getCatalogResourceById(resId) {
            if (!resId) return this.resourcesPool[0] || null;
            return this.resourcesPool.find(r => Number(r.id) === Number(resId)) || this.resourcesPool[0] || null;
        },

        getMaterialUnitCost(res) {
            if (!res) return 0;
            return Number(res.unit_cost || res.price || 0);
        },

        formatMaterialLabel(res) {
            if (!res) return 'Catalog Material';
            return `${res.resource_code} — ${res.name}`;
        },

        onSelectExistingSupplierInCreate() {
            const chosen = (this.newSupplierForm.selected_existing_supplier || '').trim();
            if (!chosen) {
                this.newSupplierForm.name = '';
                return;
            }
            this.newSupplierForm.name = chosen;
            // Check if this supplier already has saved material info for the selected material
            const exactMatch = this.existingSupplierLibrary.find(item =>
                (item.name || '').trim().toLowerCase() === chosen.toLowerCase() &&
                Number(item.resource_id) === Number(this.newSupplierForm.resource_id)
            );
            const anySupplierEntry = this.existingSupplierLibrary.find(item =>
                (item.name || '').trim().toLowerCase() === chosen.toLowerCase()
            );

            if (exactMatch) {
                this.newSupplierForm.unit_price = Number(exactMatch.unit_price) || 0;
                this.newSupplierForm.lead_time_days = Number(exactMatch.lead_time_days) || 14;
                this.newSupplierForm.payment_terms = exactMatch.payment_terms || 'Net 30';
            } else if (anySupplierEntry) {
                const res = this.getCatalogResourceById(this.newSupplierForm.resource_id);
                const catCost = this.getMaterialUnitCost(res);
                if (catCost > 0) this.newSupplierForm.unit_price = catCost;
                this.newSupplierForm.lead_time_days = Number(anySupplierEntry.lead_time_days) || 14;
                this.newSupplierForm.payment_terms = anySupplierEntry.payment_terms || 'Net 30';
            }
        },

        onSupplierNameInputInCreate() {
            const typed = (this.newSupplierForm.name || '').trim().toLowerCase();
            const matchedSupplier = this.uniqueSavedSuppliers.find(s => s.toLowerCase() === typed);
            this.newSupplierForm.selected_existing_supplier = matchedSupplier || '';
        },

        onCreateModalMaterialChange() {
            const res = this.getCatalogResourceById(this.newSupplierForm.resource_id);
            if (res) {
                this.newSupplierForm.resource_id = res.id;
                this.newSupplierForm.material_name = this.formatMaterialLabel(res);

                const currentSupplier = (this.newSupplierForm.name || '').trim().toLowerCase();
                const savedEntry = currentSupplier
                    ? this.existingSupplierLibrary.find(item =>
                        (item.name || '').trim().toLowerCase() === currentSupplier &&
                        Number(item.resource_id) === Number(res.id)
                    )
                    : null;

                if (savedEntry) {
                    this.newSupplierForm.unit_price = Number(savedEntry.unit_price) || 0;
                    this.newSupplierForm.lead_time_days = Number(savedEntry.lead_time_days) || 14;
                    this.newSupplierForm.payment_terms = savedEntry.payment_terms || 'Net 30';
                } else {
                    const catCost = this.getMaterialUnitCost(res);
                    if (catCost > 0) {
                        this.newSupplierForm.unit_price = catCost;
                    }
                }
            }
        },

        areNodesSameMaterial(nodeA, nodeB) {
            if (!nodeA || !nodeB) return false;
            if (nodeA.resource_id && nodeB.resource_id) {
                return Number(nodeA.resource_id) === Number(nodeB.resource_id);
            }
            return (nodeA.material_name || '').trim().toLowerCase() === (nodeB.material_name || '').trim().toLowerCase();
        },

        canConnectToSelectedPort(targetNode) {
            if (!this.selectedPortNodeId || !targetNode) return true;
            if (this.selectedPortNodeId === targetNode.id) return true;
            const sourceNode = this.supplierNodes.find(n => n.id === this.selectedPortNodeId);
            return this.areNodesSameMaterial(sourceNode, targetNode);
        },

        onNodeMaterialChange(node) {
            const res = this.getCatalogResourceById(node.resource_id);
            if (res) {
                node.resource_id = res.id;
                node.material_name = this.formatMaterialLabel(res);
                if (node.is_baseline) {
                    node.unit_price = this.getMaterialUnitCost(res);
                    node.supplier_name = `${res.name} (Catalog)`;
                } else {
                    // If this supplier has a saved quote for this material in the library, load its unit cost & lead time
                    const savedMatch = this.existingSupplierLibrary.find(item =>
                        (item.name || '').trim().toLowerCase() === (node.supplier_name || '').trim().toLowerCase() &&
                        Number(item.resource_id) === Number(res.id)
                    );
                    if (savedMatch) {
                        node.unit_price = Number(savedMatch.unit_price) || 0;
                        node.lead_time_days = Number(savedMatch.lead_time_days) || node.lead_time_days;
                        node.payment_terms = savedMatch.payment_terms || node.payment_terms;
                    } else {
                        const catCost = this.getMaterialUnitCost(res);
                        if (catCost > 0) node.unit_price = catCost;
                    }
                    this.syncCanvasNodesToLibrary(false);
                }
            }
            // Automatically disconnect any wire connected to a profile that now has a different material
            const nodeMap = new Map(this.supplierNodes.map(n => [n.id, n]));
            this.supplierWires = this.supplierWires.filter(w =>
                this.areNodesSameMaterial(nodeMap.get(w.from), nodeMap.get(w.to))
            );
            this.persistCurrentMaterialProfiles(false);
        },

        onPresetMaterialChange(preset) {
            const res = this.getCatalogResourceById(preset.resource_id);
            if (res) {
                preset.resource_id = res.id;
                preset.material_name = this.formatMaterialLabel(res);
            }
            this.existingSupplierLibrary = [...this.existingSupplierLibrary];
            this.persistCurrentMaterialProfiles(false);
        },

        startEditPreset(preset) {
            const res = this.getCatalogResourceById(preset.resource_id);
            this.editingPresetId = preset.id;
            this.editPresetForm = {
                id: preset.id,
                name: preset.name || '',
                resource_id: res ? res.id : (preset.resource_id || null),
                material_name: preset.material_name || this.formatMaterialLabel(res),
                unit_price: Number(preset.unit_price) || 0,
                lead_time_days: Number(preset.lead_time_days) || 14,
                payment_terms: preset.payment_terms || 'Net 30'
            };
        },

        onEditPresetMaterialChange() {
            const res = this.getCatalogResourceById(this.editPresetForm.resource_id);
            if (res) {
                this.editPresetForm.resource_id = res.id;
                this.editPresetForm.material_name = this.formatMaterialLabel(res);
                const catCost = this.getMaterialUnitCost(res);
                if (catCost > 0) {
                    this.editPresetForm.unit_price = catCost;
                }
            }
        },

        saveEditPreset(presetId) {
            const targetId = presetId || this.editPresetForm.id;
            const list = [...this.existingSupplierLibrary];
            const idx = list.findIndex(item => item.id === targetId);
            if (idx >= 0) {
                const res = this.getCatalogResourceById(this.editPresetForm.resource_id);
                const updatedName = (this.editPresetForm.name || '').trim() || list[idx].name;
                list[idx] = {
                    ...list[idx],
                    name: updatedName,
                    resource_id: res ? res.id : this.editPresetForm.resource_id,
                    material_name: res ? this.formatMaterialLabel(res) : this.editPresetForm.material_name,
                    unit_price: Number(this.editPresetForm.unit_price) || 0,
                    lead_time_days: Number(this.editPresetForm.lead_time_days) || 1,
                    payment_terms: (this.editPresetForm.payment_terms || '').trim() || 'Standard'
                };
                this.existingSupplierLibrary = list;
                this.selectedSupplierInModal = updatedName;
            }
            this.editingPresetId = null;
            this.persistCurrentMaterialProfiles(true);
        },

        cancelEditPreset() {
            this.editingPresetId = null;
        },

        removePresetFromLibrary(presetId) {
            this.existingSupplierLibrary = this.existingSupplierLibrary.filter(item => item.id !== presetId);
            if (this.editingPresetId === presetId) {
                this.editingPresetId = null;
            }
            const summary = this.supplierProfilesSummary;
            const currentKey = (this.selectedSupplierInModal || '').trim().toLowerCase();
            if (!summary.some(s => s.key === currentKey)) {
                this.selectedSupplierInModal = summary.length > 0 ? summary[0].supplierName : '';
                if (this.supplierShowMaterialsModal) {
                    this.supplierShowMaterialsModal = false;
                    this.supplierShowPickModal = true;
                }
            }
            this.persistCurrentMaterialProfiles(true);
        },

        removeSupplierFromLibrary(supplierName) {
            const target = (supplierName || '').trim().toLowerCase();
            this.existingSupplierLibrary = this.existingSupplierLibrary.filter(
                item => (item.name || '').trim().toLowerCase() !== target
            );
            this.editingPresetId = null;
            const summary = this.supplierProfilesSummary;
            const currentKey = (this.selectedSupplierInModal || '').trim().toLowerCase();
            if (!summary.some(s => s.key === currentKey)) {
                this.selectedSupplierInModal = summary.length > 0 ? summary[0].supplierName : '';
                if (this.supplierShowMaterialsModal) {
                    this.supplierShowMaterialsModal = false;
                    this.supplierShowPickModal = true;
                }
            }
            this.persistCurrentMaterialProfiles(true);
        },

        zoomIn() {
            this.canvasZoom = Math.min(2.0, Math.round((this.canvasZoom + 0.15) * 100) / 100);
        },

        zoomOut() {
            this.canvasZoom = Math.max(0.5, Math.round((this.canvasZoom - 0.15) * 100) / 100);
        },

        resetZoom() {
            this.canvasZoom = 1;
            this.canvasPanX = 0;
            this.canvasPanY = 0;
        },

        onCanvasWheel(e) {
            e.preventDefault();
            const delta = e.deltaY < 0 ? 0.1 : -0.1;
            this.canvasZoom = Math.min(2.0, Math.max(0.5, Math.round((this.canvasZoom + delta) * 100) / 100));
        },

        startPan(e) {
            // Only pan when clicking directly on canvas background (not on a node or button)
            if (e.target.closest('.dss-supplier-node') || e.target.closest('button') || e.target.closest('.dss-wire-port')) return;
            // Clicking empty canvas cancels any active port selection
            if (this.selectedPortNodeId) {
                this.selectedPortNodeId = null;
            }
            this.isPanning = true;
            this.panStartX = e.clientX - this.canvasPanX;
            this.panStartY = e.clientY - this.canvasPanY;
        },

        startDragNode(e, nodeId) {
            if (e.target.closest('input') || e.target.closest('select') || e.target.closest('button') || e.target.closest('.dss-wire-port')) return;
            this.dragNodeId = nodeId;
            const node = this.supplierNodes.find(n => n.id === nodeId);
            if (!node) return;
            const canvasRect = this.$refs.supplierCanvas.getBoundingClientRect();
            const worldMouseX = (e.clientX - canvasRect.left - this.canvasPanX) / this.canvasZoom;
            const worldMouseY = (e.clientY - canvasRect.top - this.canvasPanY) / this.canvasZoom;
            this.dragOffsetX = worldMouseX - node.x;
            this.dragOffsetY = worldMouseY - node.y;
        },

        onCanvasMouseMove(e) {
            const canvasRect = this.$refs.supplierCanvas?.getBoundingClientRect();
            if (!canvasRect) return;

            if (this.isPanning) {
                this.canvasPanX = e.clientX - this.panStartX;
                this.canvasPanY = e.clientY - this.panStartY;
                return;
            }

            const worldMouseX = (e.clientX - canvasRect.left - this.canvasPanX) / this.canvasZoom;
            const worldMouseY = (e.clientY - canvasRect.top - this.canvasPanY) / this.canvasZoom;

            if (this.dragNodeId) {
                const node = this.supplierNodes.find(n => n.id === this.dragNodeId);
                if (node) {
                    node.x = Math.max(10, Math.round(worldMouseX - this.dragOffsetX));
                    node.y = Math.max(10, Math.round(worldMouseY - this.dragOffsetY));
                }
            }

            if (this.selectedPortNodeId) {
                this.wireMousePos = { x: worldMouseX, y: worldMouseY };
            }
        },

        onCanvasMouseUp() {
            if (this.dragNodeId) {
                this.persistCurrentMaterialProfiles(false);
            }
            this.dragNodeId = null;
            this.isPanning = false;
        },

        // Point-to-Point Click Connection: Click a point on Profile A, then click a point on Profile B to connect/disconnect
        handlePortClick(e, nodeId, side = 'right') {
            e.stopPropagation();
            const node = this.supplierNodes.find(n => n.id === nodeId);
            if (!this.selectedPortNodeId) {
                this.canvasConnectionError = '';
                this.selectedPortNodeId = nodeId;
                this.selectedPortSide = side;
                if (node) {
                    this.wireMousePos = {
                        x: side === 'left' ? node.x : node.x + (node.w || 250),
                        y: node.y + (node.h || 232) / 2
                    };
                }
            } else if (this.selectedPortNodeId === nodeId) {
                this.selectedPortNodeId = null; // Deselect if clicking the same profile's point
            } else {
                const connected = this.toggleNodeConnection(this.selectedPortNodeId, nodeId);
                this.selectedPortNodeId = null;
                if (connected !== false) {
                    this.persistCurrentMaterialProfiles(false);
                }
            }
        },

        isNodeConnected(nodeId) {
            return this.supplierWires.some(w => w.from === nodeId || w.to === nodeId);
        },

        toggleNodeConnection(idA, idB) {
            const wireIdx = this.supplierWires.findIndex(w =>
                (w.from === idA && w.to === idB) || (w.from === idB && w.to === idA)
            );
            if (wireIdx >= 0) {
                this.supplierWires.splice(wireIdx, 1);
                this.canvasConnectionError = '';
                return true;
            } else {
                const nodeA = this.supplierNodes.find(n => n.id === idA);
                const nodeB = this.supplierNodes.find(n => n.id === idB);
                if (!this.areNodesSameMaterial(nodeA, nodeB)) {
                    this.canvasConnectionError = 'Cannot connect profiles with different materials. Only profiles with the same material can be connected.';
                    setTimeout(() => { this.canvasConnectionError = ''; }, 3800);
                    return false;
                }
                this.canvasConnectionError = '';
                this.supplierWires.push({ from: idA, to: idB });
                return true;
            }
        },

        connectNodes(idA, idB) {
            const nodeA = this.supplierNodes.find(n => n.id === idA);
            const nodeB = this.supplierNodes.find(n => n.id === idB);
            if (!this.areNodesSameMaterial(nodeA, nodeB)) {
                return false;
            }
            const exists = this.supplierWires.some(w =>
                (w.from === idA && w.to === idB) || (w.from === idB && w.to === idA)
            );
            if (!exists) {
                this.supplierWires.push({ from: idA, to: idB });
            }
            return true;
        },

        removeWire(index) {
            this.supplierWires.splice(index, 1);
            this.persistCurrentMaterialProfiles(false);
        },

        removeSupplierNode(nodeId) {
            const target = this.supplierNodes.find(n => n.id === nodeId);
            if (target && target.id === 'node-catalog-a') return; // Profile A (primary catalog baseline) stays on canvas
            this.supplierNodes = this.supplierNodes.filter(n => n.id !== nodeId);
            this.supplierWires = this.supplierWires.filter(w => w.from !== nodeId && w.to !== nodeId);
            if (this.selectedPortNodeId === nodeId) this.selectedPortNodeId = null;
            this.persistCurrentMaterialProfiles(false);
        },

        getNextProfileName() {
            const usedNames = new Set(this.supplierNodes.map(n => (n.name || '').trim().toUpperCase()));
            for (let i = 0; i < 26; i++) {
                const candidate = `Profile ${String.fromCharCode(65 + i)}`;
                if (!usedNames.has(candidate.toUpperCase())) {
                    return candidate;
                }
            }
            return `Profile ${this.supplierNodes.length + 1}`;
        },

        buildBaselineProfileNode(res, profileName = 'Profile A', nodeId = 'node-catalog-a', posX = 65, posY = 110) {
            const targetRes = res || this.resourcesPool[0] || null;
            const catalogCost = this.getMaterialUnitCost(targetRes);
            const labelName = targetRes ? `${targetRes.name} (Catalog)` : 'Current Catalog Material';
            return {
                id: nodeId,
                name: profileName,
                is_baseline: true,
                resource_id: targetRes ? targetRes.id : null,
                material_name: this.formatMaterialLabel(targetRes),
                supplier_name: labelName,
                unit_price: catalogCost,
                shipping_fee: 0,
                lead_time_days: 14,
                payment_terms: 'Current Catalog Unit Cost',
                x: posX,
                y: posY,
                w: 250,
                h: 232
            };
        },

        persistCurrentMaterialProfiles(showNotice = true) {
            this.syncCanvasNodesToLibrary(false);
            try {
                const canvasState = {
                    nodes: JSON.parse(JSON.stringify(this.supplierNodes)),
                    wires: JSON.parse(JSON.stringify(this.supplierWires)),
                    sectionConfigs: JSON.parse(JSON.stringify(this.sectionConfigs || {}))
                };
                localStorage.setItem('dex_dss_canvas_state_v4', JSON.stringify(canvasState));
                localStorage.setItem('dex_dss_supplier_library_v4', JSON.stringify(this.existingSupplierLibrary));
            } catch (e) {}

            if (showNotice) {
                this.supplierSavedToast = true;
                setTimeout(() => { this.supplierSavedToast = false; }, 2200);
            }
        },

        loadSavedCanvasState() {
            const defaultRes = this.resourcesPool[0] || null;
            try {
                // Merge saved libraries from v4, v3, and v2 so all previously created supplier profiles are preserved
                const libKeys = ['dex_dss_supplier_library_v4', 'dex_dss_supplier_library_v3', 'dex_dss_supplier_library_v2'];
                let mergedList = [];
                for (const key of libKeys) {
                    const raw = localStorage.getItem(key);
                    if (!raw) continue;
                    try {
                        const parsedLib = JSON.parse(raw);
                        if (Array.isArray(parsedLib)) {
                            for (const item of parsedLib) {
                                if (!item || !item.name) continue;
                                const res = this.getCatalogResourceById(item.resource_id);
                                const resId = res ? res.id : (item.resource_id || null);
                                const cleanName = (item.name || '').trim();
                                const exists = mergedList.some(m =>
                                    (m.name || '').trim().toLowerCase() === cleanName.toLowerCase() &&
                                    Number(m.resource_id) === Number(resId)
                                );
                                if (!exists) {
                                    mergedList.push({
                                        ...item,
                                        id: item.id || ('lib-' + Date.now() + '-' + Math.floor(Math.random() * 1000)),
                                        name: cleanName,
                                        resource_id: resId,
                                        material_name: item.material_name || this.formatMaterialLabel(res)
                                    });
                                }
                            }
                        }
                    } catch (e) {}
                }
                if (mergedList.length > 0) {
                    this.existingSupplierLibrary = mergedList;
                }

                const savedCanvas = localStorage.getItem('dex_dss_canvas_state_v4') || localStorage.getItem('dex_dss_canvas_state_v3');
                if (savedCanvas) {
                    const parsed = JSON.parse(savedCanvas);
                    if (parsed && typeof parsed.sectionConfigs === 'object' && parsed.sectionConfigs !== null) {
                        this.sectionConfigs = parsed.sectionConfigs;
                    }
                    if (parsed && Array.isArray(parsed.nodes) && parsed.nodes.length > 0) {
                        this.supplierNodes = parsed.nodes.map(n => {
                            const res = this.getCatalogResourceById(n.resource_id);
                            const syncedPrice = n.is_baseline && res ? this.getMaterialUnitCost(res) : Number(n.unit_price || 0);
                            return {
                                ...n,
                                resource_id: res ? res.id : (n.resource_id || null),
                                material_name: n.material_name || this.formatMaterialLabel(res),
                                unit_price: syncedPrice,
                                w: 250,
                                h: 232
                            };
                        });
                        const nodeById = new Map(this.supplierNodes.map(n => [n.id, n]));
                        this.supplierWires = (Array.isArray(parsed.wires) ? parsed.wires : []).filter(w =>
                            this.areNodesSameMaterial(nodeById.get(w.from), nodeById.get(w.to))
                        );
                        this.syncCanvasNodesToLibrary(true);
                        if (this.supplierProfilesSummary.length > 0) {
                            this.selectedSupplierInModal = this.supplierProfilesSummary[0].supplierName;
                        }
                        return;
                    }
                }
            } catch (e) {}

            this.supplierNodes = [this.buildBaselineProfileNode(defaultRes)];
            this.supplierWires = [];
            if (this.supplierProfilesSummary.length > 0) {
                this.selectedSupplierInModal = this.supplierProfilesSummary[0].supplierName;
            }
        },

        addCatalogBaselineToCanvas(res) {
            if (!res) return;
            const profileName = this.getNextProfileName();
            const count = this.supplierNodes.length;
            const posX = 65 + (count % 3) * 310;
            const posY = 95 + Math.floor(count / 3) * 245 + ((count % 2) * 25);
            const node = this.buildBaselineProfileNode(
                res,
                profileName,
                'node-cat-' + res.id + '-' + Date.now(),
                Math.min(posX, 690),
                Math.min(posY, 240)
            );
            this.supplierNodes.push(node);
            this.supplierShowPickModal = false;
            this.persistCurrentMaterialProfiles(false);
        },

        addBlankSupplierProfile() {
            const profileName = this.getNextProfileName();
            const letter = profileName.replace('Profile ', '');
            const newNodeId = 'node-' + Date.now();
            const count = this.supplierNodes.length;
            const posX = 65 + (count % 3) * 310;
            const posY = 95 + Math.floor(count / 3) * 245 + ((count % 2) * 25);

            // Inherit the material of the last node on canvas (or default catalog material)
            const refNode = this.supplierNodes[this.supplierNodes.length - 1] || this.supplierNodes[0];
            const res = this.getCatalogResourceById(refNode ? refNode.resource_id : this.supplierSelectedResourceId);
            const catCost = this.getMaterialUnitCost(res) || 150000;
            const defaultSupName = `Supplier ${letter}`;

            const newNode = {
                id: newNodeId,
                name: profileName,
                is_baseline: false,
                resource_id: res ? res.id : null,
                material_name: this.formatMaterialLabel(res),
                supplier_name: defaultSupName,
                unit_price: catCost > 0 ? Math.round(catCost * 0.95) : 150000,
                shipping_fee: 0,
                lead_time_days: 10,
                payment_terms: 'Net 30',
                x: Math.min(posX, 690),
                y: Math.min(posY, 240),
                w: 250,
                h: 232
            };

            this.supplierNodes.push(newNode);
            this.upsertSupplierLibraryEntry({
                name: defaultSupName,
                resource_id: newNode.resource_id,
                material_name: newNode.material_name,
                unit_price: newNode.unit_price,
                shipping_fee: 0,
                lead_time_days: newNode.lead_time_days,
                payment_terms: newNode.payment_terms
            }, true);
            this.selectedSupplierInModal = defaultSupName;
            this.persistCurrentMaterialProfiles(false);
        },

        addExistingProfileToCanvas(preset) {
            const profileName = this.getNextProfileName();
            const newNodeId = 'node-' + Date.now();
            const count = this.supplierNodes.length;
            const posX = 65 + (count % 3) * 310;
            const posY = 95 + Math.floor(count / 3) * 245 + ((count % 2) * 25);
            const res = this.getCatalogResourceById(preset.resource_id);

            const newNode = {
                id: newNodeId,
                name: profileName,
                is_baseline: false,
                resource_id: res ? res.id : (preset.resource_id || null),
                material_name: preset.material_name || this.formatMaterialLabel(res),
                supplier_name: preset.name,
                unit_price: Number(preset.unit_price) || 0,
                shipping_fee: Number(preset.shipping_fee) || 0,
                lead_time_days: Number(preset.lead_time_days) || 7,
                payment_terms: preset.payment_terms || 'Standard',
                x: Math.min(posX, 690),
                y: Math.min(posY, 240),
                w: 250,
                h: 232
            };

            this.supplierNodes.push(newNode);
            this.supplierShowPickModal = false;
            this.supplierShowMaterialsModal = false;
            this.persistCurrentMaterialProfiles(false);
        },

        openCreateSupplierModal(preselectSupplierName = '') {
            const refNode = this.supplierNodes[this.supplierNodes.length - 1] || this.supplierNodes[0];
            const res = this.getCatalogResourceById(refNode ? refNode.resource_id : this.supplierSelectedResourceId);
            const catCost = this.getMaterialUnitCost(res) || 150000;
            const cleanSup = (preselectSupplierName || '').trim();
            this.newSupplierForm = {
                selected_existing_supplier: cleanSup,
                resource_id: res ? res.id : null,
                material_name: this.formatMaterialLabel(res),
                name: cleanSup,
                unit_price: catCost > 0 ? catCost : 150000,
                shipping_fee: 0,
                lead_time_days: 14,
                payment_terms: 'Net 30'
            };
            if (cleanSup) {
                this.onSelectExistingSupplierInCreate();
            }
            this.supplierShowPickModal = false;
            this.supplierShowMaterialsModal = false;
            this.supplierShowCreateModal = true;
        },

        createAndAddCustomProfile() {
            const profileName = this.getNextProfileName();
            const letter = profileName.replace('Profile ', '');
            const cleanName = (this.newSupplierForm.name || '').trim() || `Supplier ${letter}`;
            this.newSupplierForm.name = cleanName;

            const res = this.getCatalogResourceById(this.newSupplierForm.resource_id);
            const matLabel = this.formatMaterialLabel(res);

            const newNodeId = 'node-' + Date.now();
            const count = this.supplierNodes.length;
            const posX = 65 + (count % 3) * 310;
            const posY = 95 + Math.floor(count / 3) * 245 + ((count % 2) * 25);

            const newNode = {
                id: newNodeId,
                name: profileName,
                is_baseline: false,
                resource_id: res ? res.id : null,
                material_name: matLabel,
                supplier_name: cleanName,
                unit_price: Number(this.newSupplierForm.unit_price) || 0,
                shipping_fee: Number(this.newSupplierForm.shipping_fee) || 0,
                lead_time_days: Number(this.newSupplierForm.lead_time_days) || 7,
                payment_terms: this.newSupplierForm.payment_terms || 'Standard',
                x: Math.min(posX, 690),
                y: Math.min(posY, 240),
                w: 250,
                h: 232
            };

            this.supplierNodes.push(newNode);

            // Save or update in "Choose From Existing Profiles" library and put at the top of the list
            this.upsertSupplierLibraryEntry({
                name: cleanName,
                resource_id: res ? res.id : null,
                material_name: matLabel,
                unit_price: Number(this.newSupplierForm.unit_price) || 0,
                shipping_fee: Number(this.newSupplierForm.shipping_fee) || 0,
                lead_time_days: Number(this.newSupplierForm.lead_time_days) || 7,
                payment_terms: this.newSupplierForm.payment_terms || 'Standard'
            }, true);

            this.selectedSupplierInModal = cleanName;
            this.supplierShowCreateModal = false;
            this.persistCurrentMaterialProfiles(true);
        },

        getWireEndpoints(wire) {
            const fromNode = this.supplierNodes.find(n => n.id === wire.from);
            const toNode = this.supplierNodes.find(n => n.id === wire.to);
            if (!fromNode || !toNode) return null;

            const nodeW = fromNode.w || 250;
            const nodeH = fromNode.h || 232;
            const toW = toNode.w || 250;
            const toH = toNode.h || 232;

            let startX, startY, endX, endY;
            if (fromNode.x + nodeW / 2 <= toNode.x + toW / 2) {
                startX = fromNode.x + nodeW;
                startY = fromNode.y + nodeH / 2;
                endX = toNode.x;
                endY = toNode.y + toH / 2;
            } else {
                startX = fromNode.x;
                startY = fromNode.y + nodeH / 2;
                endX = toNode.x + toW;
                endY = toNode.y + toH / 2;
            }

            return { startX, startY, endX, endY };
        },

        getWirePath(wire) {
            const pts = this.getWireEndpoints(wire);
            if (!pts) return '';

            const { startX, startY, endX, endY } = pts;
            const dx = Math.abs(endX - startX);
            const dy = Math.abs(endY - startY);
            const ctrlOffset = Math.max(dx * 0.5, 55);
            const dirX = startX <= endX ? 1 : -1;

            // Add a clean schematic S-bend when two profiles are at nearly the same Y coordinate
            const waveY = dy < 28 ? 28 : 0;
            const c1x = startX + dirX * ctrlOffset;
            const c1y = startY - waveY;
            const c2x = endX - dirX * ctrlOffset;
            const c2y = endY + waveY;

            return `M ${startX} ${startY} C ${c1x} ${c1y}, ${c2x} ${c2y}, ${endX} ${endY}`;
        },

        getWireMidpoint(wire) {
            const pts = this.getWireEndpoints(wire);
            if (!pts) return { x: 0, y: 0 };
            return {
                x: (pts.startX + pts.endX) / 2,
                y: (pts.startY + pts.endY) / 2
            };
        },

        getTempWirePath() {
            if (!this.selectedPortNodeId) return '';
            const node = this.supplierNodes.find(n => n.id === this.selectedPortNodeId);
            if (!node) return '';

            const nodeW = node.w || 250;
            const nodeH = node.h || 232;

            const startX = this.selectedPortSide === 'left' ? node.x : node.x + nodeW;
            const startY = node.y + nodeH / 2;
            const endX = this.wireMousePos.x;
            const endY = this.wireMousePos.y;
            const dx = Math.max(Math.abs(endX - startX) * 0.5, 30);

            return `M ${startX} ${startY} C ${startX + (startX <= endX ? dx : -dx)} ${startY}, ${endX + (startX <= endX ? -dx : dx)} ${endY}, ${endX} ${endY}`;
        },

        get supplierProcurementWindowDays() {
            const ord = this.supplierOrderDate ? new Date(this.supplierOrderDate) : new Date();
            const req = this.supplierRequiredDate ? new Date(this.supplierRequiredDate) : new Date(ord.getTime() + 25 * 86400000);
            ord.setHours(0, 0, 0, 0);
            req.setHours(0, 0, 0, 0);
            return Math.round((req.getTime() - ord.getTime()) / 86400000);
        },

        getSectionConfig(sectionKey) {
            const cfg = (this.sectionConfigs && this.sectionConfigs[sectionKey]) ? this.sectionConfigs[sectionKey] : null;
            return {
                project_id: cfg && cfg.project_id ? Number(cfg.project_id) : Number(this.supplierTargetProjectId || (this.matrix[0] && this.matrix[0].id) || 1),
                qty: cfg && cfg.qty ? Math.max(1, Number(cfg.qty)) : Math.max(1, Number(this.supplierRequiredQty) || 2),
                order_date: (cfg && cfg.order_date) ? cfg.order_date : (this.supplierOrderDate || '{{ now()->toDateString() }}'),
                required_date: (cfg && cfg.required_date) ? cfg.required_date : (this.supplierRequiredDate || '{{ now()->addDays(25)->toDateString() }}')
            };
        },

        updateSectionConfig(sectionKey, field, value) {
            const current = this.getSectionConfig(sectionKey);
            this.sectionConfigs = {
                ...(this.sectionConfigs || {}),
                [sectionKey]: {
                    ...current,
                    [field]: value
                }
            };
            this.persistCurrentMaterialProfiles(false);
        },

        evaluateProfileGroup(nodesGroup, sectionKey, sectionTitle) {
            const cfg = this.getSectionConfig(sectionKey);
            const targetProj = this.matrix.find(p => Number(p.id) === Number(cfg.project_id)) || this.matrix[0] || null;
            const qty = Math.max(1, Number(cfg.qty) || 1);

            let orderDate = cfg.order_date ? new Date(cfg.order_date) : new Date();
            orderDate.setHours(0, 0, 0, 0);

            let requiredDate = cfg.required_date ? new Date(cfg.required_date) : new Date(orderDate.getTime() + 25 * 86400000);
            requiredDate.setHours(0, 0, 0, 0);

            const windowDays = Math.round((requiredDate.getTime() - orderDate.getTime()) / 86400000);

            const parsed = nodesGroup.map(s => {
                const res = this.getCatalogResourceById(s.resource_id);
                const matLabel = s.material_name || this.formatMaterialLabel(res);
                const unitPrice = Math.max(0, Number(s.unit_price) || 0);
                const shipFee = Math.max(0, Number(s.shipping_fee) || 0);
                const totalCost = (unitPrice * qty) + shipFee;
                const leadDays = Math.max(1, Number(s.lead_time_days) || 1);

                // Arrival Date is calculated from Order Date + Shipping Lead Days
                const arrival = new Date(orderDate.getTime() + (leadDays * 86400000));
                // Latest Order Date to still meet Needed By Date = Needed By Date - Shipping Lead Days
                const latestOrder = new Date(requiredDate.getTime() - (leadDays * 86400000));

                const daysDiff = Math.round((requiredDate.getTime() - arrival.getTime()) / 86400000);
                const isLate = arrival.getTime() > requiredDate.getTime();
                const delayDays = isLate ? Math.abs(daysDiff) : 0;

                return {
                    id: s.id,
                    profile_label: s.name,
                    supplier_name: s.supplier_name,
                    resource_id: s.resource_id,
                    material_name: matLabel,
                    unit_price: unitPrice,
                    shipping_fee: shipFee,
                    total_cost: totalCost,
                    lead_time_days: leadDays,
                    order_date_str: orderDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
                    arrival_date_str: arrival.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
                    latest_order_date_str: latestOrder.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
                    payment_terms: s.payment_terms || 'Standard Terms',
                    is_late: isLate,
                    delay_days: delayDays,
                    days_buffer: daysDiff
                };
            });

            const minCost = Math.min(...parsed.map(p => p.total_cost));
            const maxCost = Math.max(...parsed.map(p => p.total_cost));
            const minLead = Math.min(...parsed.map(p => p.lead_time_days));
            const maxLead = Math.max(...parsed.map(p => p.lead_time_days));

            const costRange = Math.max(1, maxCost - minCost);
            const leadRange = Math.max(1, maxLead - minLead);
            const hasAnyOnTime = parsed.some(p => !p.is_late);

            let bestScore = -1;
            let bestId = null;

            const evaluated = parsed.map(p => {
                const costScore = costRange > 0 ? Math.max(0, Math.min(100, Math.round(100 - (((p.total_cost - minCost) / costRange) * 100)))) : 100;
                const speedScore = leadRange > 0 ? Math.max(0, Math.min(100, Math.round(100 - (((p.lead_time_days - minLead) / leadRange) * 100)))) : 100;

                // Order-Date Schedule Fit: rewards arriving on/before Needed By when ordered on Order Date
                let leadScore = speedScore;
                if (!p.is_late) {
                    const effWindow = Math.max(1, windowDays);
                    const fitScore = Math.min(100, 70 + Math.round((p.days_buffer / effWindow) * 30));
                    leadScore = Math.min(100, Math.round((speedScore * 0.45) + (fitScore * 0.55)));
                } else {
                    leadScore = Math.max(0, Math.round((speedScore * 0.3) - (p.delay_days * 6)));
                }

                // Balanced MCDA with Order-Date Feasibility:
                // Suppliers that arrive on time from Order Date receive full weighted score (55% Cost + 45% Schedule);
                // Suppliers that arrive late if ordered on Order Date are penalized so on-time suppliers are recommended first.
                const rawComposite = (costScore * 0.55) + (leadScore * 0.45);
                const composite = p.is_late
                    ? Math.max(5, Math.round((rawComposite - 35 - (p.delay_days * 1.5)) * 10) / 10)
                    : Math.round(rawComposite * 10) / 10;

                const badges = [];
                if (p.total_cost === minCost) badges.push({ text: 'Lowest Price', color: '#10B981', bg: 'rgba(16, 185, 129, 0.15)' });
                if (p.lead_time_days === minLead) badges.push({ text: 'Fastest Shipment', color: '#2563EB', bg: 'rgba(37, 99, 235, 0.15)' });

                let status = 'ok';
                let statusLabel = `${p.days_buffer}d Buffer (On-Time)`;
                if (p.is_late) {
                    status = 'critical';
                    statusLabel = `Late by ${p.delay_days}d (Order by ${p.latest_order_date_str})`;
                } else if (p.days_buffer <= 3) {
                    status = 'warning';
                    statusLabel = `${p.days_buffer}d Tight Buffer`;
                }

                // Prioritize on-time suppliers when selecting the Recommended winner
                const isEligibleForBest = hasAnyOnTime ? !p.is_late : true;
                if (isEligibleForBest && composite > bestScore) {
                    bestScore = composite;
                    bestId = p.id;
                }

                return {
                    ...p,
                    cost_score: costScore,
                    lead_score: leadScore,
                    composite_score: composite,
                    badges,
                    status,
                    status_label: statusLabel
                };
            });

            evaluated.forEach(e => {
                e.is_best_overall = (e.id === bestId);
                if (e.is_best_overall) {
                    e.badges.unshift({
                        text: e.is_late ? '★ Best Available (Needs Earlier Order)' : '★ Recommended Choice',
                        color: '#D97706',
                        bg: 'rgba(217, 119, 6, 0.2)'
                    });
                }
            });

            const ranked = [...evaluated].sort((a, b) => {
                if (a.is_best_overall !== b.is_best_overall) return a.is_best_overall ? -1 : 1;
                if (a.is_late !== b.is_late) return a.is_late ? 1 : -1;
                return b.composite_score - a.composite_score;
            });
            const winner = ranked[0] || null;

            return {
                sectionKey,
                materialName: sectionTitle,
                projectId: cfg.project_id,
                projectName: targetProj ? `${targetProj.project_code} — ${targetProj.name}` : '',
                requiredQty: qty,
                orderDateRaw: cfg.order_date,
                requiredDateRaw: cfg.required_date,
                orderDateStr: orderDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
                requiredDateStr: requiredDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
                windowDays,
                suppliers: evaluated,
                rankedSuppliers: ranked,
                winner,
                minCost,
                maxCost,
                potentialSavings: maxCost - minCost,
                daysSaved: maxLead - minLead
            };
        },

        // Multi-Material Comparison Sections for Connected Profiles (Same-Material Connections Only)
        get supplierComparisonSections() {
            if (this.supplierWires.length === 0) return [];

            // Build adjacency list of valid connected nodes that share the same material
            const nodeMap = new Map(this.supplierNodes.map(n => [n.id, n]));
            const adj = new Map();
            this.supplierWires.forEach(w => {
                const fromNode = nodeMap.get(w.from);
                const toNode = nodeMap.get(w.to);
                if (!fromNode || !toNode) return;
                if (!this.areNodesSameMaterial(fromNode, toNode)) return;
                if (!adj.has(w.from)) adj.set(w.from, new Set());
                if (!adj.has(w.to)) adj.set(w.to, new Set());
                adj.get(w.from).add(w.to);
                adj.get(w.to).add(w.from);
            });

            const connectedNodes = this.supplierNodes.filter(n => adj.has(n.id) && adj.get(n.id).size > 0);
            if (connectedNodes.length < 2) return [];

            // Group connected nodes by Material (resource_id) when >= 2 connected profiles share a material
            const byMaterial = new Map();
            connectedNodes.forEach(n => {
                const res = this.getCatalogResourceById(n.resource_id);
                const matKey = String(res ? res.id : (n.resource_id || n.material_name || 'default'));
                if (!byMaterial.has(matKey)) {
                    byMaterial.set(matKey, {
                        label: n.material_name || this.formatMaterialLabel(res),
                        nodes: []
                    });
                }
                byMaterial.get(matKey).nodes.push(n);
            });

            const sections = [];

            // Add a comparison section for each Material that has >= 2 connected profiles
            byMaterial.forEach((group, matKey) => {
                if (group.nodes.length >= 2) {
                    sections.push(
                        this.evaluateProfileGroup(
                            group.nodes,
                            'mat-sec-' + matKey,
                            group.label
                        )
                    );
                }
            });

            return sections;
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

            this.loadSavedCanvasState();

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
                const status = deficit > 0 ? 'critical' : (remainingAfter <= 8 ? 'warning' : 'ok');

                if (deficit > 0) {
                    errors.push(`Not enough material stock for ${res.resource_code} (${res.name}): requested ${reqQty} ${res.unit || 'units'}, only ${avail} available.`);
                } else if (remainingAfter <= 8) {
                    warnings.push(`Low post-allocation stock for ${res.resource_code}: ${remainingAfter} ${res.unit || 'units'} will remain (≤ 8).`);
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

        /* ── Supplier Comparison Canvas (Light Mode & Dark Mode Adaptive) ── */
        .dss-module .dss-supplier-canvas {
            position: relative !important;
            width: 100% !important;
            height: 500px !important;
            min-height: 500px !important;
            overflow: hidden !important;
            user-select: none;
            background-color: #E2E8F0;
            background-image: radial-gradient(#94A3B8 1.25px, transparent 1.25px);
            background-size: 20px 20px;
            box-shadow: inset 0 2px 6px rgba(15, 23, 42, 0.08);
        }
        [data-theme="dark"] .dss-module .dss-supplier-canvas,
        .dark .dss-module .dss-supplier-canvas {
            background-color: #0c0e12;
            background-image: radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px);
            background-size: 20px 20px;
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.45);
        }

        .dss-module .dss-supplier-node {
            position: absolute;
            top: 0;
            left: 0;
            cursor: grab;
            background: #FFFFFF;
            border: 1.5px solid #CBD5E1;
            border-radius: 8px;
            box-shadow: 0 10px 24px -4px rgba(15, 23, 42, 0.14), 0 2px 6px -1px rgba(15, 23, 42, 0.08);
        }
        [data-theme="dark"] .dss-module .dss-supplier-node,
        .dark .dss-module .dss-supplier-node {
            background: #181b22;
            border: 1.5px solid rgba(255, 255, 255, 0.16);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
        }

        .dss-module .dss-supplier-node-header {
            background: #F1F5F9;
            border-bottom: 1px solid #CBD5E1;
        }
        [data-theme="dark"] .dss-module .dss-supplier-node-header,
        .dark .dss-module .dss-supplier-node-header {
            background: rgba(255, 255, 255, 0.04);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .dss-module .dss-supplier-node-title {
            color: #0F172A;
        }
        [data-theme="dark"] .dss-module .dss-supplier-node-title,
        .dark .dss-module .dss-supplier-node-title {
            color: #f3f4f6;
        }

        .dss-module .dss-supplier-node-label {
            color: #475569;
        }
        [data-theme="dark"] .dss-module .dss-supplier-node-label,
        .dark .dss-module .dss-supplier-node-label {
            color: #9ca3af;
        }

        .dss-module .dss-supplier-node-input {
            width: 100%;
            height: 26px;
            font-size: 0.75rem;
            background: #F8FAFC;
            border: 1px solid #CBD5E1;
            border-radius: 4px;
            color: #0F172A;
            padding: 0 6px;
        }
        [data-theme="dark"] .dss-module .dss-supplier-node-input,
        .dark .dss-module .dss-supplier-node-input {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .dss-module .dss-supplier-node-input.is-price {
            color: #15803D;
            font-weight: 700;
        }
        [data-theme="dark"] .dss-module .dss-supplier-node-input.is-price,
        .dark .dss-module .dss-supplier-node-input.is-price {
            color: #10B981;
        }

        .dss-module .dss-supplier-node-input.is-lead {
            color: #1D4ED8;
            font-weight: 700;
        }
        [data-theme="dark"] .dss-module .dss-supplier-node-input.is-lead,
        .dark .dss-module .dss-supplier-node-input.is-lead {
            color: #60a5fa;
        }

        .dss-module .dss-wire-port {
            border: 2px solid #FFFFFF;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.25);
        }
        [data-theme="dark"] .dss-module .dss-wire-port,
        .dark .dss-module .dss-wire-port {
            border: 2px solid #0c0e12;
            box-shadow: none;
        }

        .dss-module .dss-canvas-tip {
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid #CBD5E1;
            color: #475569;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.06);
        }
        [data-theme="dark"] .dss-module .dss-canvas-tip,
        .dark .dss-module .dss-canvas-tip {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #9ca3af;
            box-shadow: none;
        }

        .dss-module .dss-modal-center-wrap {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            z-index: 100000;
            pointer-events: none;
            box-sizing: border-box;
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

            @if(!Auth::user()->hasRole('Staff'))
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
            @endif
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
                            2. Project delay simulation
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm dss-btn"
                            :class="simMode === 'supplier' ? 'btn-primary' : 'btn-secondary'"
                            @click="simMode = 'supplier'"
                            id="btn-sim-mode-supplier"
                        >
                            3. Supplier Procurement Trade-Off ("Compare Price &amp; Lead-Time")
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ─────────────────────────────────────────────────────────────────────
             SIMULATOR 1: PROJECT FEASIBILITY (Visual Capacity Bars & Inputs)
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
                </div>

                <div class="card-body" style="padding: var(--space-5) var(--space-6);">
                    {{-- Inputs Panel --}}
                    <div style="margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--color-border);">
                        <div style="max-width: 320px; margin-bottom: 1rem;">
                            <label for="feas-budget" class="form-label" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase;">Proposed Budget (₱)</label>
                            <input
                                type="text"
                                inputmode="decimal"
                                data-amount-input
                                id="feas-budget"
                                :value="window.formatAmountWithCommas ? window.formatAmountWithCommas(String(feasBudget ?? '')) : feasBudget"
                                @input="feasBudget = window.parseAmountValue ? (window.parseAmountValue($event.target.value) || 0) : (parseFloat(String($event.target.value).replace(/,/g, '')) || 0)"
                                class="form-control font-mono"
                                style="height: 36px; font-size: 0.8125rem;"
                            >
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
                            <h2 class="card-header__title" style="margin: 0; font-size: 1rem;">Project delay simulation</h2>
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
             SIMULATOR 3: INTERACTIVE SUPPLIER PROFILE COMPARISON CANVAS (Drag & Wire Flow)
             ───────────────────────────────────────────────────────────────────── --}}
        <div x-show="simMode === 'supplier'" x-cloak id="sim-panel-supplier">
            {{-- Main Interactive Canvas Container (Low-Fidelity Diagram Layout) --}}
            <div class="card card-glass mb-4" style="margin-bottom: 1rem; overflow: hidden;">
                {{-- Canvas Header Toolbar (Add Profile, Choose From Existing, Create Profile + Status & Zoom Controls) --}}
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--color-border); padding: 0.75rem 1.25rem; background: var(--color-surface); flex-wrap: wrap; gap: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <button
                            type="button"
                            class="btn btn-secondary btn-sm dss-btn"
                            @click="addBlankSupplierProfile()"
                            style="font-size: 0.75rem; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase; display: flex; align-items: center; gap: 0.35rem;"
                        >
                            <svg style="width: 13px; height: 13px;" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
                            <span>Add Profile</span>
                        </button>

                        <button
                            type="button"
                            class="btn btn-secondary btn-sm dss-btn"
                            @click="openPickSupplierModal()"
                            style="font-size: 0.75rem; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase; display: flex; align-items: center; gap: 0.35rem;"
                        >
                            <svg style="width: 13px; height: 13px;" viewBox="0 0 20 20" fill="currentColor"><path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z"/></svg>
                            <span>Choose From Existing Profiles</span>
                        </button>

                        <button
                            type="button"
                            class="btn btn-primary btn-sm dss-btn"
                            @click="openCreateSupplierModal()"
                            style="font-size: 0.75rem; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase; display: flex; align-items: center; gap: 0.35rem;"
                        >
                            <svg style="width: 13px; height: 13px;" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
                            <span>Create Profile</span>
                        </button>
                    </div>

                    {{-- Canvas Status & Zoom Controls --}}
                    <div style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
                        <div style="font-size: 0.74rem; color: var(--color-muted); display: flex; align-items: center; gap: 0.5rem;">
                            <span><strong class="font-mono text-accent" x-text="`${supplierNodes.length} Profiles`"></strong> on canvas</span>
                            <span>·</span>
                            <span><strong class="font-mono" style="color: #D97706;" x-text="`${supplierWires.length} Connection(s)`"></strong></span>
                            <span>·</span>
                            <span><strong class="font-mono text-completed" x-text="`${supplierComparisonSections.length} Material Comparison(s)`"></strong></span>
                        </div>

                        <div style="display: flex; align-items: center; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: 6px; padding: 2px 4px; gap: 3px;">
                            <button
                                type="button"
                                @click="zoomOut()"
                                title="Zoom Out"
                                style="background: transparent; border: none; color: var(--color-muted); font-size: 0.9rem; font-weight: 700; width: 26px; height: 26px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center;"
                            >−</button>

                            <span class="font-mono" style="font-size: 0.75rem; min-width: 44px; text-align: center; color: var(--color-ink); font-weight: 600;" x-text="`${Math.round(canvasZoom * 100)}%`"></span>

                            <button
                                type="button"
                                @click="zoomIn()"
                                title="Zoom In"
                                style="background: transparent; border: none; color: var(--color-muted); font-size: 0.9rem; font-weight: 700; width: 26px; height: 26px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center;"
                            >+</button>

                            <button
                                type="button"
                                @click="resetZoom()"
                                title="Reset Canvas View"
                                style="background: transparent; border: none; color: var(--color-muted); font-size: 0.68rem; text-transform: uppercase; font-weight: 700; padding: 0 6px; height: 26px; border-radius: 4px; cursor: pointer;"
                            >Reset</button>
                        </div>
                    </div>
                </div>

                {{-- Interactive Canvas Area (Light & Dark Mode Adaptive, Height 500px) --}}
                <div
                    x-ref="supplierCanvas"
                    @mousedown="startPan($event)"
                    @mousemove="onCanvasMouseMove($event)"
                    @mouseup="onCanvasMouseUp()"
                    @mouseleave="onCanvasMouseUp()"
                    @wheel="onCanvasWheel($event)"
                    class="dss-supplier-canvas"
                    style="position: relative; width: 100%; height: 500px; overflow: hidden; user-select: none;"
                >
                    {{-- Zoom & Pan Scaled World Container --}}
                    <div
                        style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; transform-origin: 0 0;"
                        :style="{
                            transform: `translate(${canvasPanX}px, ${canvasPanY}px) scale(${canvasZoom})`
                        }"
                    >
                        {{-- Established Wires (Each <svg> is inside <template> so HTML5 parser creates real SVGElements in http://www.w3.org/2000/svg namespace) --}}
                        <template x-for="(wire, wIdx) in supplierWires" :key="'wire-' + wire.from + '-' + wire.to + '-' + wIdx">
                            <div style="position: absolute; top: 0; left: 0; width: 3000px; height: 3000px; pointer-events: none; z-index: 15;">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 3000 3000"
                                    width="3000"
                                    height="3000"
                                    style="position: absolute; top: 0; left: 0; width: 3000px; height: 3000px; pointer-events: none; overflow: visible;"
                                >
                                    {{-- Wide Transparent Hit Area --}}
                                    <path
                                        :d="getWirePath(wire)"
                                        fill="none"
                                        stroke="transparent"
                                        stroke-width="18"
                                        style="pointer-events: stroke; cursor: pointer;"
                                        @click.stop="removeWire(wIdx)"
                                    />
                                    {{-- Outer Glow Cable Halo --}}
                                    <path
                                        :d="getWirePath(wire)"
                                        fill="none"
                                        stroke="rgba(217, 119, 6, 0.28)"
                                        stroke-width="8"
                                        stroke-linecap="round"
                                    />
                                    {{-- Solid Primary Connector Wire --}}
                                    <path
                                        :d="getWirePath(wire)"
                                        fill="none"
                                        stroke="#D97706"
                                        stroke-width="3.5"
                                        stroke-linecap="round"
                                    />
                                    {{-- Inner Dashed Signal Flow --}}
                                    <path
                                        :d="getWirePath(wire)"
                                        fill="none"
                                        stroke="#FEF08A"
                                        stroke-width="1.5"
                                        stroke-dasharray="6,5"
                                        stroke-linecap="round"
                                    />
                                </svg>
                                {{-- Midpoint Disconnect Button --}}
                                <button
                                    type="button"
                                    @mousedown.stop
                                    @click.stop="removeWire(wIdx)"
                                    :style="{
                                        left: getWireMidpoint(wire).x + 'px',
                                        top: getWireMidpoint(wire).y + 'px'
                                    }"
                                    style="position: absolute; transform: translate(-50%, -50%); width: 20px; height: 20px; border-radius: 50%; background: #D97706; border: 2px solid #FFFFFF; color: #FFFFFF; font-size: 12px; font-weight: 700; line-height: 1; display: flex; align-items: center; justify-content: center; cursor: pointer; pointer-events: auto; box-shadow: 0 2px 6px rgba(0,0,0,0.35); z-index: 18; padding: 0;"
                                    title="Click to disconnect wire"
                                >&times;</button>
                            </div>
                        </template>

                        {{-- In-Progress Point-Click Connection Wire Preview --}}
                        <template x-if="selectedPortNodeId">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 3000 3000"
                                width="3000"
                                height="3000"
                                style="position: absolute; top: 0; left: 0; width: 3000px; height: 3000px; pointer-events: none; z-index: 16; overflow: visible;"
                            >
                                <path
                                    :d="getTempWirePath()"
                                    fill="none"
                                    stroke="#10B981"
                                    stroke-width="2.5"
                                    stroke-dasharray="6,4"
                                    stroke-linecap="round"
                                />
                            </svg>
                        </template>

                        {{-- Draggable Supplier Profile Nodes --}}
                        <template x-for="node in supplierNodes" :key="node.id">
                            <div
                                @mousedown="startDragNode($event, node.id)"
                                :style="{
                                    transform: `translate3d(${node.x}px, ${node.y}px, 0)`,
                                    width: (node.w || 250) + 'px',
                                    zIndex: dragNodeId === node.id ? 25 : 5,
                                    borderColor: selectedPortNodeId === node.id ? '#10B981' : (isNodeConnected(node.id) ? '#D97706' : '')
                                }"
                                style="position: absolute; top: 0; left: 0; cursor: grab; transition: border-color 0.15s ease;"
                                class="dss-supplier-node"
                            >
                                {{-- Node Header: Profile Title (Profile A, B, C...) & Remove --}}
                                <div class="dss-supplier-node-header" style="display: flex; justify-content: space-between; align-items: center; padding: 0.45rem 0.75rem; border-top-left-radius: 7px; border-top-right-radius: 7px;">
                                    <div style="display: flex; align-items: center; gap: 0.4rem;">
                                        <span
                                            :style="{ background: isNodeConnected(node.id) ? '#10B981' : (node.is_baseline ? '#2563EB' : '#94A3B8') }"
                                            style="display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #C98A2C;"
                                        ></span>
                                        <strong class="dss-supplier-node-title" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em;" x-text="node.name"></strong>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.3rem;">
                                        <template x-if="node.is_baseline">
                                            <span style="font-size: 0.6rem; font-weight: 700; text-transform: uppercase; padding: 1px 5px; border-radius: 4px; background: rgba(37, 99, 235, 0.12); color: #2563EB;">Catalog</span>
                                        </template>
                                        <template x-if="node.id !== 'node-catalog-a'">
                                            <button
                                                type="button"
                                                @click.stop="removeSupplierNode(node.id)"
                                                class="dss-supplier-node-label"
                                                style="background: transparent; border: none; font-size: 0.9rem; cursor: pointer; padding: 0 4px; line-height: 1;"
                                                title="Remove this profile"
                                            >&times;</button>
                                        </template>
                                    </div>
                                </div>

                                {{-- Left Wire Connection Point --}}
                                <div
                                    @mousedown.stop
                                    @click.stop="handlePortClick($event, node.id, 'left')"
                                    :style="{
                                        background: selectedPortNodeId === node.id ? '#10B981' : (isNodeConnected(node.id) ? '#D97706' : '#94A3B8'),
                                        transform: selectedPortNodeId === node.id ? 'translateY(-50%) scale(1.25)' : 'translateY(-50%)',
                                        opacity: canConnectToSelectedPort(node) ? 1 : 0.3,
                                        cursor: canConnectToSelectedPort(node) ? 'pointer' : 'not-allowed'
                                    }"
                                    style="position: absolute; left: -9px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; border-radius: 50%; background: #C98A2C; cursor: pointer; z-index: 20; transition: transform 0.15s ease, background 0.15s ease, opacity 0.15s ease;"
                                    :title="canConnectToSelectedPort(node) ? 'Click this point, then click a point on another profile with the same material to connect or disconnect' : 'Cannot connect: this profile has a different material'"
                                    class="dss-wire-port"
                                ></div>

                                {{-- Right Wire Connection Point --}}
                                <div
                                    @mousedown.stop
                                    @click.stop="handlePortClick($event, node.id, 'right')"
                                    :style="{
                                        background: selectedPortNodeId === node.id ? '#10B981' : (isNodeConnected(node.id) ? '#D97706' : '#94A3B8'),
                                        transform: selectedPortNodeId === node.id ? 'translateY(-50%) scale(1.25)' : 'translateY(-50%)',
                                        opacity: canConnectToSelectedPort(node) ? 1 : 0.3,
                                        cursor: canConnectToSelectedPort(node) ? 'pointer' : 'not-allowed'
                                    }"
                                    style="position: absolute; right: -9px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; border-radius: 50%; background: #C98A2C; cursor: pointer; z-index: 20; transition: transform 0.15s ease, background 0.15s ease, opacity 0.15s ease;"
                                    :title="canConnectToSelectedPort(node) ? 'Click this point, then click a point on another profile with the same material to connect or disconnect' : 'Cannot connect: this profile has a different material'"
                                    class="dss-wire-port"
                                ></div>

                                {{-- Node Body: Material & Supplier Attributes --}}
                                <div style="padding: 0.6rem 0.75rem; display: flex; flex-direction: column; gap: 0.42rem;">
                                    <div>
                                        <div class="dss-supplier-node-label" style="font-size: 0.6rem; text-transform: uppercase; font-weight: 700; margin-bottom: 2px;">Catalog Material</div>
                                        <select
                                            x-model.number="node.resource_id"
                                            @change="onNodeMaterialChange(node)"
                                            class="dss-supplier-node-input"
                                            style="padding-right: 4px; cursor: pointer;"
                                        >
                                            @foreach($resourcesForSim as $resOpt)
                                                <option value="{{ $resOpt['id'] }}">{{ $resOpt['resource_code'] }} — {{ $resOpt['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <div class="dss-supplier-node-label" style="font-size: 0.6rem; text-transform: uppercase; font-weight: 700; margin-bottom: 2px;">Supplier Name</div>
                                        <input
                                            type="text"
                                            x-model="node.supplier_name"
                                            @change="persistCurrentMaterialProfiles(false)"
                                            class="dss-supplier-node-input"
                                        >
                                    </div>

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem;">
                                        <div>
                                            <div class="dss-supplier-node-label" style="font-size: 0.6rem; text-transform: uppercase; font-weight: 700; margin-bottom: 2px;">Unit Cost (₱)</div>
                                            <input
                                                type="text"
                                                inputmode="decimal"
                                                data-amount-input
                                                :value="window.formatAmountWithCommas ? window.formatAmountWithCommas(String(node.unit_price ?? '')) : node.unit_price"
                                                @input="node.unit_price = window.parseAmountValue ? (window.parseAmountValue($event.target.value) || 0) : (parseFloat(String($event.target.value).replace(/,/g, '')) || 0)"
                                                :readonly="node.is_baseline"
                                                @change="persistCurrentMaterialProfiles(false)"
                                                class="dss-supplier-node-input is-price font-mono"
                                                :title="node.is_baseline ? 'Based on current catalog unit cost' : ''"
                                            >
                                        </div>
                                        <div>
                                            <div class="dss-supplier-node-label" style="font-size: 0.6rem; text-transform: uppercase; font-weight: 700; margin-bottom: 2px;">Shipping Lead</div>
                                            <div style="display: flex; align-items: center; gap: 2px;">
                                                <input
                                                    type="number"
                                                    x-model.number="node.lead_time_days"
                                                    @change="persistCurrentMaterialProfiles(false)"
                                                    min="1"
                                                    max="180"
                                                    class="dss-supplier-node-input is-lead font-mono"
                                                >
                                                <span class="dss-supplier-node-label" style="font-size: 0.65rem; font-weight: 600;">d</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="dss-supplier-node-label" style="font-size: 0.6rem; text-transform: uppercase; font-weight: 700; margin-bottom: 2px;">Payment Terms</div>
                                        <input
                                            type="text"
                                            x-model="node.payment_terms"
                                            @change="persistCurrentMaterialProfiles(false)"
                                            placeholder="e.g. Net 30, COD"
                                            class="dss-supplier-node-input"
                                        >
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Minimized Help Hint / Same-Material Constraint Overlay --}}
                    <div
                        class="dss-canvas-tip"
                        :style="canvasConnectionError ? 'border-color: #EF4444; background: rgba(239, 68, 68, 0.14); color: #FCA5A5;' : ''"
                        style="position: absolute; bottom: 8px; left: 10px; border-radius: 4px; padding: 0.18rem 0.5rem; pointer-events: none; max-width: 480px; z-index: 20;"
                    >
                        <span
                            style="font-size: 0.62rem; font-weight: 500; letter-spacing: 0.01em;"
                            x-text="canvasConnectionError ? canvasConnectionError : (selectedPortNodeId ? 'Click another profile with the same material to connect (or click canvas to cancel)' : 'Tip: Click two profile points with the same material to connect & compare')"
                        >
                        </span>
                    </div>
                </div>
            </div>

            {{-- Bottom Section: Graphical View / Comparison Result (Multi-Material Connected Sections) --}}
            <div class="card card-glass mb-6">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1.25rem;">
                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                        <h2 class="card-header__title" style="margin: 0; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.04em;">
                            Graphical View / Result
                        </h2>
                        <template x-if="supplierComparisonSections.length > 0">
                            <span class="dss-risk-pill" style="background: rgba(37, 99, 235, 0.12); color: #60a5fa; border: 1px solid rgba(37, 99, 235, 0.3); font-size: 0.68rem; padding: 0.15rem 0.55rem;" x-text="`${supplierComparisonSections.length} Material Comparison Section(s)`"></span>
                        </template>
                    </div>
                </div>

                <div class="card-body" style="padding: 1.25rem;">
                    {{-- Empty State when no profiles are connected --}}
                    <template x-if="supplierComparisonSections.length === 0">
                        <div style="text-align: center; padding: 2rem 1rem; color: var(--color-muted); font-size: 0.85rem;">
                            No connected profiles to compare. Click a connection point on one profile and click a point on another profile with the same material above to wire and compare them.
                        </div>
                    </template>

                    {{-- Multi-Material Comparison Sections (One Section Per Connected Material Group) --}}
                    <template x-if="supplierComparisonSections.length > 0">
                        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                            <template x-for="(section, sIdx) in supplierComparisonSections" :key="section.sectionKey">
                                <div style="border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; background: var(--color-surface);">
                                    {{-- Material Comparison Section Header --}}
                                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; padding: 0.75rem 1.1rem; background: var(--color-surface-alt); border-bottom: 1px solid var(--color-border);">
                                        <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                                            <span class="font-mono" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; padding: 0.2rem 0.55rem; border-radius: 4px; background: rgba(37, 99, 235, 0.12); color: #2563EB;" x-text="`Material ${sIdx + 1}: ${section.materialName}`"></span>
                                            <template x-if="section.winner">
                                                <span class="dss-risk-pill" style="background: rgba(217, 119, 6, 0.15); color: #D97706; border: 1px solid rgba(217, 119, 6, 0.35); font-size: 0.7rem; padding: 0.18rem 0.55rem;">
                                                    Recommended: <strong x-text="section.winner.supplier_name"></strong>
                                                </span>
                                            </template>
                                        </div>

                                        <div style="display: flex; gap: 1rem; font-size: 0.75rem;" class="font-mono">
                                            <span class="text-muted">Cost Difference: <strong class="text-completed" x-text="formatCurrency(section.potentialSavings)"></strong></span>
                                            <span class="text-muted">Shipment difference: <strong style="color: #60a5fa;" x-text="`${section.daysSaved} Days`"></strong></span>
                                        </div>
                                    </div>

                                    {{-- Per-Material Section Controls: Project, Desired Qty, Order Date & Needed By Date (Multiplied per compared material section) --}}
                                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.85rem; padding: 0.65rem 1.1rem; background: var(--color-surface); border-bottom: 1px solid var(--color-border);">
                                        <div style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
                                            <div style="display: flex; align-items: center; gap: 0.35rem;">
                                                <span style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--color-muted);">Project:</span>
                                                <select
                                                    :value="section.projectId"
                                                    @change="updateSectionConfig(section.sectionKey, 'project_id', Number($event.target.value))"
                                                    class="form-control form-control--compact dss-select"
                                                    style="min-width: 205px; height: 32px; font-size: 0.78rem;"
                                                >
                                                    @foreach($matrix as $projOpt)
                                                        <option value="{{ $projOpt['id'] }}">{{ $projOpt['project_code'] }} — {{ $projOpt['name'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div style="display: flex; align-items: center; gap: 0.35rem;">
                                                <span style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--color-muted);">Qty:</span>
                                                <input
                                                    type="number"
                                                    :value="section.requiredQty"
                                                    @input="updateSectionConfig(section.sectionKey, 'qty', Math.max(1, Number($event.target.value) || 1))"
                                                    min="1"
                                                    max="999"
                                                    class="form-control font-mono"
                                                    style="width: 68px !important; height: 32px; font-size: 0.8rem;"
                                                >
                                            </div>

                                            <div style="display: flex; align-items: center; gap: 0.35rem;">
                                                <span style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--color-muted);" title="Date when the material order will be placed">Order Date:</span>
                                                <input
                                                    type="date"
                                                    :value="section.orderDateRaw"
                                                    @change="updateSectionConfig(section.sectionKey, 'order_date', $event.target.value)"
                                                    class="form-control"
                                                    style="width: 140px; height: 32px; font-size: 0.78rem;"
                                                >
                                            </div>

                                            <div style="display: flex; align-items: center; gap: 0.35rem;">
                                                <span style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--color-muted);" title="Date when the material is needed on-site">Needed By:</span>
                                                <input
                                                    type="date"
                                                    :value="section.requiredDateRaw"
                                                    @change="updateSectionConfig(section.sectionKey, 'required_date', $event.target.value)"
                                                    class="form-control"
                                                    style="width: 140px; height: 32px; font-size: 0.78rem;"
                                                >
                                            </div>

                                            <span
                                                class="dss-risk-pill font-mono"
                                                :style="section.windowDays > 0 ? 'background: rgba(37, 99, 235, 0.12); color: #2563EB; border: 1px solid rgba(37, 99, 235, 0.28);' : 'background: var(--color-danger-light); color: var(--color-danger); border: 1px solid rgba(220, 38, 38, 0.28);'"
                                                style="font-size: 0.68rem; padding: 0.18rem 0.55rem;"
                                                x-text="`${section.windowDays}d Lead Window`"
                                                title="Available days between Order Date and Needed By Date"
                                            ></span>
                                        </div>

                                        <span class="font-mono text-muted" style="font-size: 0.72rem;" x-text="`Order: ${section.orderDateStr} → Needed: ${section.requiredDateStr}`"></span>
                                    </div>

                                    {{-- Connected Supplier Cards for this Material Section --}}
                                    <div style="padding: 1rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                                        <template x-for="res in section.rankedSuppliers" :key="section.sectionKey + '-card-' + res.id">
                                            <div
                                                style="background: var(--color-surface-alt); border: 1.5px solid var(--color-border); border-radius: var(--radius-md); padding: 1rem; display: flex; flex-direction: column; justify-content: space-between; position: relative;"
                                                :style="{
                                                    borderColor: res.is_best_overall ? '#D97706' : 'var(--color-border)',
                                                    boxShadow: res.is_best_overall ? '0 4px 14px rgba(217, 119, 6, 0.12)' : 'none'
                                                }"
                                            >
                                                <div>
                                                    {{-- Header --}}
                                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.6rem;">
                                                        <div>
                                                            <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                                                                <span class="font-mono" style="font-size: 0.7rem; color: var(--color-muted); text-transform: uppercase;" x-text="res.profile_label"></span>
                                                                <span style="font-size: 0.65rem; color: var(--color-muted);">·</span>
                                                                <span class="font-mono" style="font-size: 0.68rem; color: #2563EB; font-weight: 600;" x-text="res.material_name"></span>
                                                            </div>
                                                            <div style="font-size: 0.92rem; font-weight: 700; color: var(--color-text-main); margin-top: 2px;" x-text="res.supplier_name"></div>
                                                            <div style="font-size: 0.72rem; color: var(--color-muted);" x-text="`Terms: ${res.payment_terms}`"></div>
                                                        </div>
                                                    </div>

                                                    {{-- Badges --}}
                                                    <div style="display: flex; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 0.75rem;">
                                                        <template x-for="(b, bIdx) in res.badges" :key="section.sectionKey + '-b-' + res.id + '-' + bIdx">
                                                            <span class="dss-risk-pill" :style="{ color: b.color, background: b.bg, borderColor: b.color }" style="font-size: 0.68rem; padding: 0.15rem 0.5rem; font-weight: 700;" x-text="b.text"></span>
                                                        </template>
                                                        <span class="dss-risk-pill" :style="badgeStyle(res.status) + ' font-size: 0.68rem; padding: 0.15rem 0.5rem;'" x-text="res.status_label"></span>
                                                    </div>

                                                    {{-- Key Specs List (Includes Order Date -> Arrival Date & Latest Order Cutoff) --}}
                                                    <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 6px; padding: 0.65rem 0.75rem; display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.78rem;">
                                                        <div style="display: flex; justify-content: space-between;">
                                                            <span class="text-muted">Total Cost:</span>
                                                            <strong class="font-mono" style="color: #10B981;" x-text="formatCurrency(res.total_cost)"></strong>
                                                        </div>
                                                        <div style="display: flex; justify-content: space-between;">
                                                            <span class="text-muted">Unit Cost:</span>
                                                            <span class="font-mono" x-text="`${formatCurrency(res.unit_price)} × ${section.requiredQty}`"></span>
                                                        </div>
                                                        <div style="display: flex; justify-content: space-between;">
                                                            <span class="text-muted">Est. Arrival (from Order):</span>
                                                            <strong class="font-mono" :class="res.is_late ? 'text-danger' : 'text-main'" x-text="`${res.lead_time_days}d (${res.arrival_date_str})`"></strong>
                                                        </div>
                                                        <div style="display: flex; justify-content: space-between;">
                                                            <span class="text-muted">Latest Order Date:</span>
                                                            <span class="font-mono" :class="res.is_late ? 'text-danger font-bold' : 'text-muted'" x-text="`By ${res.latest_order_date_str}`"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Modal 1: Choose From Existing Profiles (Vertical List Style of Supplier Profiles) --}}
            <div
                x-show="supplierShowPickModal"
                x-cloak
                role="dialog"
                aria-modal="true"
                style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 99999;"
            >
                <div
                    @click="supplierShowPickModal = false; cancelEditPreset()"
                    x-show="supplierShowPickModal"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(4px); z-index: 99999;"
                ></div>

                <div
                    class="dss-modal-center-wrap"
                    style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; z-index: 100000; pointer-events: none; box-sizing: border-box;"
                >
                    <div
                        @click.away="supplierShowPickModal = false; cancelEditPreset()"
                        x-show="supplierShowPickModal"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform scale-95"
                        x-transition:enter-end="opacity-100 transform scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 transform scale-100"
                        x-transition:leave-end="opacity-0 transform scale-95"
                        style="position: relative; pointer-events: auto; margin: auto; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); width: 100%; max-width: 600px; overflow: hidden; box-shadow: 0 24px 48px -8px rgba(0,0,0,0.5); z-index: 100000;"
                    >
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--color-border);">
                            <div>
                                <strong style="font-size: 0.92rem; text-transform: uppercase;">Choose From Existing Profiles</strong>
                                <div style="font-size: 0.72rem; color: var(--color-muted); margin-top: 2px;">Click a supplier profile from the list below to open the materials they are selling.</div>
                            </div>
                            <button type="button" @click="supplierShowPickModal = false; cancelEditPreset()" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--color-muted);">&times;</button>
                        </div>

                        <div style="padding: 1rem 1.25rem; max-height: 480px; overflow-y: auto; display: flex; flex-direction: column; gap: 1rem;">
                            {{-- Vertical List Style of Saved Supplier Profiles --}}
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.6rem;">
                                    <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--color-muted);">
                                        Supplier Profile List (<span x-text="supplierProfilesSummary.length"></span> Suppliers)
                                    </div>
                                    <button type="button" class="btn btn-primary btn-sm dss-btn" style="font-size: 0.72rem; padding: 0.25rem 0.65rem;" @click="openCreateSupplierModal('')">
                                        + Create New Profile
                                    </button>
                                </div>

                                <template x-if="supplierProfilesSummary.length === 0">
                                    <div style="padding: 1.15rem; text-align: center; background: var(--color-surface-alt); border: 1px dashed var(--color-border); border-radius: var(--radius-md); color: var(--color-muted); font-size: 0.8rem;">
                                        No saved supplier profiles yet. Click <strong>+ Create New Profile</strong> to add one.
                                    </div>
                                </template>

                                {{-- List-style Supplier Rows --}}
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <template x-for="sup in supplierProfilesSummary" :key="'sup-list-' + sup.key">
                                        <div
                                            @click="openSupplierMaterialsModal(sup.supplierName)"
                                            style="background: var(--color-surface-alt); border: 1.5px solid var(--color-border); border-radius: var(--radius-md); padding: 0.7rem 0.95rem; cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; transition: border-color 0.15s ease, background 0.15s ease;"
                                            onmouseover="this.style.borderColor='#D97706'"
                                            onmouseout="this.style.borderColor='var(--color-border)'"
                                        >
                                            <div style="display: flex; flex-direction: column; gap: 0.2rem;">
                                                <strong style="font-size: 0.88rem; color: var(--color-text-main);" x-text="sup.supplierName"></strong>
                                                <div style="display: flex; align-items: center; gap: 0.45rem;">
                                                    <span class="font-mono" style="font-size: 0.68rem; padding: 1px 8px; border-radius: 999px; background: rgba(37, 99, 235, 0.12); color: #2563EB; font-weight: 600;" x-text="`${sup.count} Material(s) Selling`"></span>
                                                </div>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <span style="font-size: 0.74rem; font-weight: 700; color: #D97706;">Choose Material &rarr;</span>
                                                <button
                                                    type="button"
                                                    class="btn btn-secondary btn-sm dss-btn"
                                                    @click.stop="removeSupplierFromLibrary(sup.supplierName)"
                                                    style="font-size: 0.7rem; padding: 0.2rem 0.5rem; color: #EF4444;"
                                                    title="Remove this supplier profile"
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Catalog Material Baselines (Add another Catalog Material to compare on the same canvas) --}}
                            <div>
                                <div style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--color-muted); margin-bottom: 0.5rem; padding-top: 0.5rem; border-top: 1px solid var(--color-border);">
                                    Materials Catalog Baselines (Add Another Catalog Material to Compare)
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.45rem;">
                                    <template x-for="res in resourcesPool" :key="'cat-base-' + res.id">
                                        <div style="background: var(--color-surface-alt); border: 1px dashed var(--color-border); border-radius: var(--radius-md); padding: 0.6rem 0.9rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
                                            <div>
                                                <div style="display: flex; align-items: center; gap: 0.4rem;">
                                                    <span style="font-size: 0.6rem; font-weight: 700; text-transform: uppercase; padding: 1px 5px; border-radius: 4px; background: rgba(37, 99, 235, 0.12); color: #2563EB;">Catalog</span>
                                                    <strong style="font-size: 0.8rem;" x-text="`${res.resource_code} — ${res.name}`"></strong>
                                                </div>
                                                <div style="font-size: 0.72rem; color: var(--color-muted); margin-top: 2px;">
                                                    Catalog Unit Cost: <strong class="font-mono text-completed" x-text="`₱${getMaterialUnitCost(res).toLocaleString()}`"></strong>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-secondary btn-sm dss-btn" style="font-size: 0.72rem;" @click="addCatalogBaselineToCanvas(res)">
                                                + Add Baseline
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div style="padding: 0.75rem 1.25rem; border-top: 1px solid var(--color-border); text-align: right;">
                            <button type="button" class="btn btn-secondary btn-sm dss-btn" @click="supplierShowPickModal = false; cancelEditPreset()">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Modal 1B: Pop-Up Modal Showing Materials Sold by the Clicked Supplier Profile --}}
            <div
                x-show="supplierShowMaterialsModal"
                x-cloak
                role="dialog"
                aria-modal="true"
                style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 99999;"
            >
                <div
                    @click="supplierShowMaterialsModal = false; cancelEditPreset()"
                    x-show="supplierShowMaterialsModal"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(4px); z-index: 99999;"
                ></div>

                <div
                    class="dss-modal-center-wrap"
                    style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; z-index: 100000; pointer-events: none; box-sizing: border-box;"
                >
                    <div
                        @click.away="supplierShowMaterialsModal = false; cancelEditPreset()"
                        x-show="supplierShowMaterialsModal"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform scale-95"
                        x-transition:enter-end="opacity-100 transform scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 transform scale-100"
                        x-transition:leave-end="opacity-0 transform scale-95"
                        style="position: relative; pointer-events: auto; margin: auto; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); width: 100%; max-width: 650px; overflow: hidden; box-shadow: 0 24px 48px -8px rgba(0,0,0,0.5); z-index: 100000;"
                    >
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--color-border);">
                            <div>
                                <div style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: var(--color-muted);">Supplier Profile — Materials Selling</div>
                                <strong style="font-size: 0.96rem; color: #D97706;" x-text="selectedSupplierInModal"></strong>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <button
                                    type="button"
                                    class="btn btn-primary btn-sm dss-btn"
                                    style="font-size: 0.72rem; padding: 0.25rem 0.65rem;"
                                    @click="openCreateSupplierModal(selectedSupplierInModal)"
                                >
                                    + Add Material to Supplier
                                </button>
                                <button type="button" @click="supplierShowMaterialsModal = false; cancelEditPreset()" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--color-muted);">&times;</button>
                            </div>
                        </div>

                        <div style="padding: 1rem 1.25rem; max-height: 460px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.6rem;">
                            <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--color-muted);">
                                Choose a Material Below to Add to Canvas (<span x-text="selectedSupplierItems.length"></span> Material(s) Selling)
                            </div>

                            {{-- List-Style Materials Sold by Selected Supplier --}}
                            <template x-for="preset in selectedSupplierItems" :key="'sup-item-' + preset.id">
                                <div
                                    style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: 6px; padding: 0.7rem 0.9rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap;"
                                >
                                    <div style="flex: 1; min-width: 220px;">
                                        <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                                            <span style="font-size: 0.62rem; font-weight: 700; text-transform: uppercase; padding: 1px 5px; border-radius: 4px; background: rgba(16, 185, 129, 0.12); color: #10B981;">Material</span>
                                            <strong style="font-size: 0.84rem; color: var(--color-text-main);" x-text="preset.material_name || formatMaterialLabel(getCatalogResourceById(preset.resource_id))"></strong>
                                        </div>
                                        <div style="font-size: 0.74rem; color: var(--color-muted); margin-top: 4px;" class="font-mono">
                                            Unit Cost: <strong class="text-completed">₱<span x-text="Number(preset.unit_price).toLocaleString()"></span></strong>
                                            · Lead: <strong x-text="`${preset.lead_time_days} days`"></strong>
                                            · Terms: <span x-text="preset.payment_terms"></span>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;">
                                        <button
                                            type="button"
                                            class="btn btn-primary btn-sm dss-btn"
                                            style="font-size: 0.72rem; padding: 0.25rem 0.65rem;"
                                            @click="addExistingProfileToCanvas(preset)"
                                        >
                                            + Add to Canvas
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-sm dss-btn"
                                            style="font-size: 0.72rem; padding: 0.25rem 0.55rem;"
                                            @click="startEditPreset(preset)"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-sm dss-btn"
                                            style="font-size: 0.72rem; padding: 0.25rem 0.5rem; color: #EF4444;"
                                            @click="removePresetFromLibrary(preset.id)"
                                            title="Remove this material from supplier"
                                        >
                                            &times;
                                        </button>
                                    </div>
                                </div>
                            </template>

                            {{-- Edit Material Info Form (Rendered Outside x-for to Prevent Nested x-for Issues) --}}
                            <div
                                x-show="editingPresetId !== null"
                                style="background: var(--color-surface); border: 1.5px solid #2563EB; border-radius: 6px; padding: 0.85rem; margin-top: 0.25rem; display: flex; flex-direction: column; gap: 0.65rem;"
                            >
                                <div style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #2563EB;">
                                    Edit Saved Supplier Material Information
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem;">
                                    <div>
                                        <label style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: var(--color-muted); display: block; margin-bottom: 2px;">Supplier Name</label>
                                        <input type="text" x-model="editPresetForm.name" class="form-control form-control--compact" style="height: 32px; font-size: 0.78rem; width: 100%;">
                                    </div>
                                    <div>
                                        <label style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: var(--color-muted); display: block; margin-bottom: 2px;">Material / Item They Are Selling</label>
                                        <select
                                            x-model.number="editPresetForm.resource_id"
                                            @change="onEditPresetMaterialChange()"
                                            class="form-control form-control--compact"
                                            style="height: 32px; font-size: 0.76rem; width: 100%;"
                                        >
                                            @foreach($resourcesForSim as $resOpt)
                                                @php $optCost = (float) ($resOpt['unit_cost'] ?: $resOpt['price'] ?: 0); @endphp
                                                <option value="{{ $resOpt['id'] }}">{{ $resOpt['resource_code'] }} — {{ $resOpt['name'] }} (Unit Cost: ₱{{ number_format($optCost, 0) }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.6rem;">
                                    <div>
                                        <label style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: var(--color-muted); display: block; margin-bottom: 2px;">Unit Cost (₱)</label>
                                        <input
                                            type="text"
                                            inputmode="decimal"
                                            data-amount-input
                                            :value="window.formatAmountWithCommas ? window.formatAmountWithCommas(String(editPresetForm.unit_price ?? '')) : editPresetForm.unit_price"
                                            @input="editPresetForm.unit_price = window.parseAmountValue ? (window.parseAmountValue($event.target.value) || 0) : (parseFloat(String($event.target.value).replace(/,/g, '')) || 0)"
                                            class="form-control form-control--compact font-mono"
                                            style="height: 32px; font-size: 0.78rem; width: 100%;"
                                        >
                                    </div>
                                    <div>
                                        <label style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: var(--color-muted); display: block; margin-bottom: 2px;">Shipping Lead (Days)</label>
                                        <input type="number" x-model.number="editPresetForm.lead_time_days" min="1" max="180" class="form-control form-control--compact font-mono" style="height: 32px; font-size: 0.78rem; width: 100%;">
                                    </div>
                                    <div>
                                        <label style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: var(--color-muted); display: block; margin-bottom: 2px;">Payment Terms</label>
                                        <input type="text" x-model="editPresetForm.payment_terms" class="form-control form-control--compact" style="height: 32px; font-size: 0.78rem; width: 100%;">
                                    </div>
                                </div>
                                <div style="display: flex; justify-content: flex-end; gap: 0.45rem;">
                                    <button type="button" class="btn btn-secondary btn-sm dss-btn" style="font-size: 0.72rem; padding: 0.22rem 0.65rem;" @click="cancelEditPreset()">Cancel</button>
                                    <button type="button" class="btn btn-primary btn-sm dss-btn" style="font-size: 0.72rem; padding: 0.22rem 0.75rem;" @click="saveEditPreset(editingPresetId)">Save Changes</button>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1.25rem; border-top: 1px solid var(--color-border);">
                            <button type="button" class="btn btn-secondary btn-sm dss-btn" @click="backToSupplierProfileListModal()">
                                &larr; Back to Supplier List
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm dss-btn" @click="supplierShowMaterialsModal = false; cancelEditPreset()">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Modal 2: Create Profile (Choose Saved Supplier or Type New + Choose Catalog Material) --}}
            <div
                x-show="supplierShowCreateModal"
                x-cloak
                role="dialog"
                aria-modal="true"
                style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 99999;"
            >
                <div
                    @click="supplierShowCreateModal = false"
                    x-show="supplierShowCreateModal"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(4px); z-index: 99999;"
                ></div>

                <div
                    class="dss-modal-center-wrap"
                    style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; z-index: 100000; pointer-events: none; box-sizing: border-box;"
                >
                    <div
                        @click.away="supplierShowCreateModal = false"
                        x-show="supplierShowCreateModal"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform scale-95"
                        x-transition:enter-end="opacity-100 transform scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 transform scale-100"
                        x-transition:leave-end="opacity-0 transform scale-95"
                        style="position: relative; pointer-events: auto; margin: auto; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); width: 100%; max-width: 500px; overflow: hidden; box-shadow: 0 24px 48px -8px rgba(0,0,0,0.5); z-index: 100000;"
                    >
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--color-border);">
                            <strong style="font-size: 0.92rem; text-transform: uppercase;">Create New Supplier Profile</strong>
                            <button type="button" @click="supplierShowCreateModal = false" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--color-muted);">&times;</button>
                        </div>
                        <div style="padding: 1.25rem; display: flex; flex-direction: column; gap: 0.85rem;">
                            {{-- Choose Existing Saved Supplier or Type New --}}
                            <div>
                                <label class="form-label" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase;">Choose From Saved Suppliers (Or Type New Below)</label>
                                <select
                                    x-model="newSupplierForm.selected_existing_supplier"
                                    @change="onSelectExistingSupplierInCreate()"
                                    class="form-control"
                                    style="height: 36px; font-size: 0.8125rem; width: 100%; margin-bottom: 0.45rem;"
                                >
                                    <option value="">-- Type a New Supplier Name Below --</option>
                                    <template x-for="supName in uniqueSavedSuppliers" :key="'create-sup-opt-' + supName">
                                        <option :value="supName" x-text="supName"></option>
                                    </template>
                                </select>
                                <input
                                    type="text"
                                    x-model="newSupplierForm.name"
                                    @input="onSupplierNameInputInCreate()"
                                    list="dss-saved-suppliers-datalist"
                                    placeholder="Type a new supplier name or edit selected..."
                                    class="form-control"
                                    style="height: 36px; font-size: 0.8125rem; width: 100%;"
                                >
                                <datalist id="dss-saved-suppliers-datalist">
                                    <template x-for="supName in uniqueSavedSuppliers" :key="'dl-sup-' + supName">
                                        <option :value="supName"></option>
                                    </template>
                                </datalist>
                            </div>

                            <div>
                                <label class="form-label" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase;">Material (From Materials Catalog)</label>
                                <select
                                    x-model.number="newSupplierForm.resource_id"
                                    @change="onCreateModalMaterialChange()"
                                    class="form-control"
                                    style="height: 36px; font-size: 0.8125rem; width: 100%;"
                                >
                                    @foreach($resourcesForSim as $resOpt)
                                        @php $optUnitCost = (float) ($resOpt['unit_cost'] ?: $resOpt['price'] ?: 0); @endphp
                                        <option value="{{ $resOpt['id'] }}">{{ $resOpt['resource_code'] }} — {{ $resOpt['name'] }} (Unit Cost: ₱{{ number_format($optUnitCost, 0) }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <div>
                                    <label class="form-label" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase;">Unit Cost (₱)</label>
                                    <input
                                        type="text"
                                        inputmode="decimal"
                                        data-amount-input
                                        :value="window.formatAmountWithCommas ? window.formatAmountWithCommas(String(newSupplierForm.unit_price ?? '')) : newSupplierForm.unit_price"
                                        @input="newSupplierForm.unit_price = window.parseAmountValue ? (window.parseAmountValue($event.target.value) || 0) : (parseFloat(String($event.target.value).replace(/,/g, '')) || 0)"
                                        class="form-control font-mono"
                                        style="height: 36px; font-size: 0.8125rem;"
                                    >
                                </div>
                                <div>
                                    <label class="form-label" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase;">Shipping Lead (Days)</label>
                                    <input type="number" x-model.number="newSupplierForm.lead_time_days" min="1" max="180" class="form-control font-mono" style="height: 36px; font-size: 0.8125rem;">
                                </div>
                            </div>
                            <div>
                                <label class="form-label" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase;">Payment Terms</label>
                                <input type="text" x-model="newSupplierForm.payment_terms" placeholder="e.g. 30 Days Net, COD, 50% DP" class="form-control" style="height: 36px; font-size: 0.8125rem;">
                            </div>
                        </div>
                        <div style="padding: 0.85rem 1.25rem; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end; gap: 0.5rem;">
                            <button type="button" class="btn btn-secondary btn-sm dss-btn" @click="supplierShowCreateModal = false">Cancel</button>
                            <button type="button" class="btn btn-primary btn-sm dss-btn" @click="createAndAddCustomProfile()">Save &amp; Place on Canvas</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- =========================================================================
         TAB 2: GENERATE RESOURCE ALLOCATION (Spec-Driven Template & Auto-Assign)
         ========================================================================= --}}
    @if(!Auth::user()->hasRole('Staff'))
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
                                            <span class="font-mono font-bold" :style="{ color: statusColorHex(mc.status) }" x-text="mc.deficit > 0 ? `Shortage: −${mc.deficit} · Available: ${mc.available} / ${mc.req_qty} req (${formatCurrency(mc.line_cost)})` : `Available: ${mc.available} / ${mc.req_qty} req (${formatCurrency(mc.line_cost)})`"></span>
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
    @endif
</div>
@endsection
