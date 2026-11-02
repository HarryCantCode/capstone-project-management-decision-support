@extends('layouts.app')

@section('title', 'Materials Catalog')
@section('meta-description', 'Manage elevator materials, specifications, pricing, stock levels, and demand movement.')

@section('content')
<div
    class="catalog-module"
    x-data="{
        selectedIds: [],
        addModalOpen: false,
        editModalOpen: false,
        pageIds: [{{ $resources->pluck('id')->implode(',') }}],
        editForm: {
            id: null,
            name: '',
            resource_code: '',
            model: '',
            quantity_available: 0,
            price: 0,
            unit_cost: 0,
            demand_status: 'fast-moving',
            description: '',
            actionUrl: ''
        },
        materialsMap: {
            @foreach ($resources as $res)
            {{ $res->id }}: {
                id: {{ $res->id }},
                name: @js($res->name),
                resource_code: @js($res->resource_code),
                model: @js($res->model ?? ''),
                quantity_available: {{ (int) $res->quantity_available }},
                price: {{ (float) $res->price }},
                unit_cost: {{ (float) $res->unit_cost }},
                demand_status: @js($res->demand_status ?? 'fast-moving'),
                description: @js($res->description ?? ''),
                actionUrl: @js(route('resources.update', $res))
            },
            @endforeach
        },

        openAddModal() {
            this.addModalOpen = true;
        },

        openEditModal(id) {
            const item = this.materialsMap[id];
            if (!item) return;
            this.editForm = Object.assign({}, item);
            this.editModalOpen = true;
        },

        toggleSelection(id) {
            const index = this.selectedIds.indexOf(id);
            if (index > -1) {
                this.selectedIds.splice(index, 1);
            } else {
                this.selectedIds.push(id);
            }
        },

        isAllSelected() {
            if (this.pageIds.length === 0) return false;
            return this.pageIds.every(id => this.selectedIds.includes(id));
        },

        toggleSelectAll(e) {
            if (e.target.checked) {
                this.pageIds.forEach(id => {
                    if (!this.selectedIds.includes(id)) {
                        this.selectedIds.push(id);
                    }
                });
            } else {
                this.selectedIds = this.selectedIds.filter(id => !this.pageIds.includes(id));
            }
        },

        confirmBulkDelete() {
            if (this.selectedIds.length === 0) return;
            if (confirm(`Are you sure you want to delete ${this.selectedIds.length} selected material(s) from the catalog? This action cannot be undone.`)) {
                document.getElementById('bulk-delete-form').submit();
            }
        },

        confirmSingleDelete(id, name, code, deleteUrl) {
            if (confirm(`Are you sure you want to delete material [${code}] '${name}' from the inventory?`)) {
                const form = document.getElementById('single-delete-form');
                form.action = deleteUrl;
                form.submit();
            }
        }
    }"
>
    {{-- Page Header --}}
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
        <div>
            <h1>Materials Catalog</h1>
            <p class="page-header__description">Centralized inventory of elevator parts, components, specifications, and supply records.</p>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            {{-- Bulk Delete Action Trigger --}}
            <button
                type="button"
                class="btn btn-danger btn-sm"
                x-show="selectedIds.length > 0"
                x-cloak
                @click="confirmBulkDelete()"
                style="display: inline-flex; align-items: center; gap: 0.35rem;"
            >
                <svg style="width: 15px; height: 15px;" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                Delete Selected (<span x-text="selectedIds.length"></span>)
            </button>

            @can('create', App\Models\Resource::class)
            <button
                type="button"
                class="btn btn-primary btn-sm"
                @click="openAddModal()"
                id="btn-add-material"
                style="display: inline-flex; align-items: center; gap: 0.4rem;"
            >
                <svg style="width: 16px; height: 16px;" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"/>
                </svg>
                Add Material
            </button>
            @endcan
        </div>
    </div>

    {{-- Filter & Search Toolbar --}}
    <div class="card card-glass" style="margin-bottom: var(--space-4);">
        <div class="card-body" style="padding: 1rem 1.25rem;">
            <form method="GET" action="{{ route('resources.index') }}" style="display: flex; gap: 1rem; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                <div style="display: flex; gap: 0.75rem; align-items: center; flex: 1; min-width: 280px; max-width: 460px;">
                    <div style="position: relative; width: 100%;">
                        <input
                            type="text"
                            name="search"
                            value="{{ $search ?? '' }}"
                            placeholder="Search by code, item name, model or specs..."
                            class="form-control"
                            style="padding-left: 2.25rem; font-size: 0.875rem;"
                        >
                        <svg style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--color-muted);" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                </div>

                <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: nowrap;">
                    <label for="demand_status" class="visually-hidden">Filter by demand status</label>
                    <select id="demand_status" name="demand_status" class="form-control form-control--compact" style="width: auto; min-width: 170px;" onchange="this.form.submit()">
                        <option value="">All Demand Statuses</option>
                        <option value="fast-moving" @selected(($demandStatus ?? '') === 'fast-moving')>Fast-moving</option>
                        <option value="slow-moving" @selected(($demandStatus ?? '') === 'slow-moving')>Slow-moving</option>
                    </select>

                    <button type="submit" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem; white-space: nowrap;">
                        <svg style="width: 14px; height: 14px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" />
                        </svg>
                        Filter
                    </button>
                    @if (!empty($search) || !empty($demandStatus))
                        <a href="{{ route('resources.index') }}" class="btn btn-secondary btn-sm" title="Clear filters" style="white-space: nowrap;">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Materials Inventory Table Card --}}
    <div class="card card-glass">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-weight: 600;">Materials & Components Inventory</span>
                <span class="badge badge-accent font-mono" style="font-size: 0.75rem;">{{ $resources->total() }} items</span>
            </div>

            <div x-show="selectedIds.length > 0" x-cloak style="font-size: 0.8125rem; color: var(--color-muted);">
                <span x-text="selectedIds.length"></span> item(s) selected
            </div>
        </div>

        @if ($resources->isEmpty())
            <div class="card-body">
                <div class="empty-state">
                    <h2 class="empty-state__title">No materials found</h2>
                    <p class="empty-state__description">No catalog entries match your search criteria or the catalog is empty.</p>
                    @can('create', App\Models\Resource::class)
                    <button type="button" class="btn btn-primary btn-sm" @click="openAddModal()" style="margin-top: 0.75rem;">
                        Add First Material
                    </button>
                    @endcan
                </div>
            </div>
        @else
            <div class="table-wrapper">
                <table class="data-table" id="materials-catalog-table">
                    <caption class="visually-hidden">Materials Catalog List</caption>
                    <thead>
                        <tr>
                            <th scope="col" style="width: 44px; text-align: center;">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    @change="toggleSelectAll($event)"
                                    :checked="isAllSelected()"
                                    aria-label="Select all materials on this page"
                                >
                            </th>
                            <th scope="col">Item Code</th>
                            <th scope="col" style="min-width: 180px;">Item Name & Description</th>
                            <th scope="col" style="min-width: 220px;">Model / Specs</th>
                            <th scope="col" class="text-right">Qty</th>
                            <th scope="col" class="text-right">Price (PHP)</th>
                            <th scope="col" class="text-right">Unit Cost (PHP)</th>
                            <th scope="col" class="text-center">Demand Status</th>
                            <th scope="col" class="text-right" style="min-width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resources as $resource)
                            <tr :class="{ 'row-selected': selectedIds.includes({{ $resource->id }}) }">
                                <td style="text-align: center;">
                                    <input
                                        type="checkbox"
                                        class="form-check-input item-select-box"
                                        value="{{ $resource->id }}"
                                        @change="toggleSelection({{ $resource->id }})"
                                        :checked="selectedIds.includes({{ $resource->id }})"
                                        aria-label="Select item {{ $resource->resource_code }}"
                                    >
                                </td>
                                <td>
                                    <a href="{{ route('resources.show', $resource) }}" class="font-mono" style="font-weight: 600; color: var(--color-accent); text-decoration: none;">
                                        {{ $resource->resource_code }}
                                    </a>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--color-ink);">{{ $resource->name }}</div>
                                    @if ($resource->description)
                                        <div style="font-size: 0.775rem; color: var(--color-muted); max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $resource->description }}">
                                            {{ $resource->description }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div style="font-size: 0.8125rem; color: var(--color-ink); max-width: 260px; line-height: 1.35;" title="{{ $resource->model }}">
                                        {{ $resource->model ?: '—' }}
                                    </div>
                                </td>
                                <td class="cell-numeric" style="font-weight: 600;">
                                    {{ number_format($resource->quantity_available) }}
                                </td>
                                <td class="cell-numeric font-mono" style="color: var(--color-ink);">
                                    ₱{{ number_format((float) $resource->price, 2) }}
                                </td>
                                <td class="cell-numeric font-mono" style="color: var(--color-muted);">
                                    ₱{{ number_format((float) $resource->unit_cost, 2) }}
                                </td>
                                <td class="text-center">
                                    @if ($resource->demand_status === 'fast-moving')
                                        <span class="badge badge-success" style="font-size: 0.75rem; padding: 0.2rem 0.5rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.03em;">
                                            Fast-moving
                                        </span>
                                    @else
                                        <span class="badge badge-warning" style="font-size: 0.75rem; padding: 0.2rem 0.5rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.03em; background: rgba(245, 158, 11, 0.15); color: #b45309; border: 1px solid rgba(245, 158, 11, 0.3);">
                                            Slow-moving
                                        </span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div style="display: inline-flex; align-items: center; gap: 0.4rem; justify-content: flex-end;">
                                        @can('update', $resource)
                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-sm"
                                            style="padding: 0.25rem 0.5rem; font-size: 0.8125rem;"
                                            @click="openEditModal({{ $resource->id }})"
                                            title="Configure Material"
                                        >
                                            Configure
                                        </button>
                                        @endcan

                                        @can('delete', $resource)
                                        <button
                                            type="button"
                                            class="btn btn-sm"
                                            style="padding: 0.25rem 0.45rem; color: var(--color-danger, #ef4444); border: 1px solid rgba(239, 68, 68, 0.3); background: transparent;"
                                            @click="confirmSingleDelete({{ $resource->id }}, @js($resource->name), @js($resource->resource_code), @js(route('resources.destroy', $resource)))"
                                            title="Remove Material"
                                        >
                                            <svg style="width: 14px; height: 14px;" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                        </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer pagination-wrapper">{{ $resources->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>

    {{-- Premium Add Material Modal --}}
    <div
        class="reassign-modal-overlay"
        style="display: none;"
        x-show="addModalOpen"
        x-cloak
        role="dialog"
        aria-modal="true"
        aria-labelledby="add-modal-title"
    >
        <div class="reassign-modal-backdrop" @click="addModalOpen = false"></div>
        <div class="reassign-modal-content card card-premium-modal" style="max-width: 680px; width: 100%; max-height: 90vh; display: flex; flex-direction: column; border-radius: var(--radius-lg); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid var(--color-border); overflow: hidden;">
            {{-- Modal Header --}}
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1.15rem 1.5rem; background: var(--color-surface); border-bottom: 1px solid var(--color-border);">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 36px; height: 36px; border-radius: var(--radius-md); background: rgba(56, 189, 248, 0.12); display: flex; align-items: center; justify-content: center; color: var(--color-accent);">
                        <svg style="width: 20px; height: 20px;" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M4 3a2 2 0 1 0 0 4h12a2 2 0 1 0 0-4H4zm0 6a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2H4zm2 3h8v3H6v-3z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 id="add-modal-title" style="font-size: 1.05rem; font-weight: 600; margin: 0; color: var(--color-ink);">Add Material to Catalog</h2>
                        <span style="font-size: 0.775rem; color: var(--color-muted);">Register a new elevator component into central stock</span>
                    </div>
                </div>
                <button type="button" @click="addModalOpen = false" class="flash-close" style="background: transparent; border: none; font-size: 1.35rem; cursor: pointer; color: var(--color-muted); line-height: 1; padding: 0.25rem;">&times;</button>
            </div>

            <form action="{{ route('resources.store') }}" method="POST" style="display: flex; flex-direction: column; overflow-y: auto; flex: 1;">
                @csrf
                <div class="card-body" style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem;">
                    {{-- Row 1: Item Name & Auto-Generated Fixed Code Badge --}}
                    <div style="display: grid; grid-template-columns: 1fr 180px; gap: 1rem; align-items: start;">
                        <div class="form-group-premium">
                            <label for="add_name" class="form-label" style="font-weight: 600; font-size: 0.8125rem;">Item Name <span class="text-danger">*</span></label>
                            <input type="text" id="add_name" name="name" class="form-control" required placeholder="e.g. Electric Wire Rope Hoist">
                        </div>

                        <div class="form-group-premium">
                            <label class="form-label" style="font-weight: 600; font-size: 0.8125rem;">Item Code</label>
                            <input type="text" class="form-control font-mono" value="{{ $nextItemCode }}" readonly disabled style="background: var(--color-surface-alt); cursor: not-allowed; opacity: 0.85; font-weight: 600; color: var(--color-accent);">
                        </div>
                    </div>

                    {{-- Row 2: Model & Specifications --}}
                    <div class="form-group-premium">
                        <label for="add_model" class="form-label" style="font-weight: 600; font-size: 0.8125rem;">Model & Specifications (Dimensions, Configuration) <span class="text-danger">*</span></label>
                        <textarea id="add_model" name="model" class="form-control" rows="2" required placeholder="e.g. &quot;NH&quot; model NHHY 2T Cargo lift, complete w/ overwinding limit switch (made in Japan)"></textarea>
                    </div>

                    {{-- Row 3: Pricing & Quantity Metric Cards Grid --}}
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                        <div class="form-group-premium">
                            <label for="add_quantity" class="form-label" style="font-weight: 600; font-size: 0.8125rem;">Quantity in Stock <span class="text-danger">*</span></label>
                            <input type="number" id="add_quantity" name="quantity_available" class="form-control" min="0" value="1" required style="font-weight: 600;">
                        </div>

                        <div class="form-group-premium">
                            <label for="add_price" class="form-label" style="font-weight: 600; font-size: 0.8125rem;">Price (PHP) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" id="add_price" name="price" class="form-control font-mono" min="0" placeholder="0.00" required>
                        </div>

                        <div class="form-group-premium">
                            <label for="add_unit_cost" class="form-label" style="font-weight: 600; font-size: 0.8125rem;">Unit Cost (PHP) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" id="add_unit_cost" name="unit_cost" class="form-control font-mono" min="0" placeholder="0.00" required>
                        </div>
                    </div>

                    {{-- Row 4: Demand Status Selection --}}
                    <div class="form-group-premium">
                        <label for="add_demand_status" class="form-label" style="font-weight: 600; font-size: 0.8125rem;">Demand Status <span class="text-danger">*</span></label>
                        <select id="add_demand_status" name="demand_status" class="form-control" required>
                            <option value="fast-moving">Fast-moving (Regularly installed across elevator assemblies)</option>
                            <option value="slow-moving">Slow-moving (Custom, specialized, or infrequent order)</option>
                        </select>
                    </div>

                    {{-- Row 5: Description & Purpose --}}
                    <div class="form-group-premium">
                        <label for="add_description" class="form-label" style="font-weight: 600; font-size: 0.8125rem;">Description & Scope of Supply Purpose</label>
                        <textarea id="add_description" name="description" class="form-control" rows="2" placeholder="Explain what this material is used for (e.g. hoist lifting mechanism, elevator shaft rails, door interlock system)..."></textarea>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="card-footer" style="padding: 1rem 1.5rem; display: flex; justify-content: flex-end; align-items: center; gap: 0.75rem; border-top: 1px solid var(--color-border); background: var(--color-surface);">
                    <button type="button" class="btn btn-secondary btn-sm" @click="addModalOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                        <svg style="width: 14px; height: 14px;" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        Save Material
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Premium Configure / Edit Material Modal --}}
    <div
        class="reassign-modal-overlay"
        style="display: none;"
        x-show="editModalOpen"
        x-cloak
        role="dialog"
        aria-modal="true"
        aria-labelledby="configure-modal-title"
    >
        <div class="reassign-modal-backdrop" @click="editModalOpen = false"></div>
        <div class="reassign-modal-content card card-premium-modal" style="max-width: 680px; width: 100%; max-height: 90vh; display: flex; flex-direction: column; border-radius: var(--radius-lg); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid var(--color-border); overflow: hidden;">
            {{-- Modal Header --}}
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1.15rem 1.5rem; background: var(--color-surface); border-bottom: 1px solid var(--color-border);">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 36px; height: 36px; border-radius: var(--radius-md); background: rgba(56, 189, 248, 0.12); display: flex; align-items: center; justify-content: center; color: var(--color-accent);">
                        <svg style="width: 20px; height: 20px;" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                        </svg>
                    </div>
                    <div>
                        <h2 id="configure-modal-title" style="font-size: 1.05rem; font-weight: 600; margin: 0; color: var(--color-ink);">Configure Material</h2>
                        <span style="font-size: 0.775rem; color: var(--color-muted);">Update specifications, pricing, and stock details</span>
                    </div>
                </div>
                <button type="button" @click="editModalOpen = false" class="flash-close" style="background: transparent; border: none; font-size: 1.35rem; cursor: pointer; color: var(--color-muted); line-height: 1; padding: 0.25rem;">&times;</button>
            </div>

            <form :action="editForm.actionUrl" method="POST" style="display: flex; flex-direction: column; overflow-y: auto; flex: 1;">
                @csrf
                @method('PUT')
                <div class="card-body" style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem;">
                    {{-- Row 1: Item Name & Fixed Item Code Display --}}
                    <div style="display: grid; grid-template-columns: 1fr 180px; gap: 1rem; align-items: start;">
                        <div class="form-group-premium">
                            <label for="edit_name" class="form-label" style="font-weight: 600; font-size: 0.8125rem;">Item Name <span class="text-danger">*</span></label>
                            <input type="text" id="edit_name" name="name" class="form-control" x-model="editForm.name" required>
                        </div>

                        <div class="form-group-premium">
                            <label class="form-label" style="font-weight: 600; font-size: 0.8125rem;">Item Code</label>
                            <input type="text" class="form-control font-mono" :value="editForm.resource_code" readonly disabled style="background: var(--color-surface-alt); cursor: not-allowed; opacity: 0.85; font-weight: 600; color: var(--color-accent);">
                        </div>
                    </div>

                    {{-- Row 2: Model & Specifications --}}
                    <div class="form-group-premium">
                        <label for="edit_model" class="form-label" style="font-weight: 600; font-size: 0.8125rem;">Model & Specifications (Dimensions, Configuration) <span class="text-danger">*</span></label>
                        <textarea id="edit_model" name="model" class="form-control" rows="2" x-model="editForm.model" required></textarea>
                    </div>

                    {{-- Row 3: Metrics Grid --}}
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                        <div class="form-group-premium">
                            <label for="edit_quantity" class="form-label" style="font-weight: 600; font-size: 0.8125rem;">Quantity in Stock <span class="text-danger">*</span></label>
                            <input type="number" id="edit_quantity" name="quantity_available" class="form-control" min="0" x-model="editForm.quantity_available" required style="font-weight: 600;">
                        </div>

                        <div class="form-group-premium">
                            <label for="edit_price" class="form-label" style="font-weight: 600; font-size: 0.8125rem;">Price (PHP) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" id="edit_price" name="price" class="form-control font-mono" min="0" x-model="editForm.price" required>
                        </div>

                        <div class="form-group-premium">
                            <label for="edit_unit_cost" class="form-label" style="font-weight: 600; font-size: 0.8125rem;">Unit Cost (PHP) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" id="edit_unit_cost" name="unit_cost" class="form-control font-mono" min="0" x-model="editForm.unit_cost" required>
                        </div>
                    </div>

                    {{-- Row 4: Demand Status --}}
                    <div class="form-group-premium">
                        <label for="edit_demand_status" class="form-label" style="font-weight: 600; font-size: 0.8125rem;">Demand Status <span class="text-danger">*</span></label>
                        <select id="edit_demand_status" name="demand_status" class="form-control" x-model="editForm.demand_status" required>
                            <option value="fast-moving">Fast-moving (Regularly installed across elevator assemblies)</option>
                            <option value="slow-moving">Slow-moving (Custom, specialized, or infrequent order)</option>
                        </select>
                    </div>

                    {{-- Row 5: Description --}}
                    <div class="form-group-premium">
                        <label for="edit_description" class="form-label" style="font-weight: 600; font-size: 0.8125rem;">Description & Scope of Supply Purpose</label>
                        <textarea id="edit_description" name="description" class="form-control" rows="2" x-model="editForm.description"></textarea>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="card-footer" style="padding: 1rem 1.5rem; display: flex; justify-content: flex-end; align-items: center; gap: 0.75rem; border-top: 1px solid var(--color-border); background: var(--color-surface);">
                    <button type="button" class="btn btn-secondary btn-sm" @click="editModalOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                        <svg style="width: 14px; height: 14px;" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Bulk Delete Hidden Form --}}
    <form id="bulk-delete-form" action="{{ route('resources.bulk-destroy') }}" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
        <template x-for="id in selectedIds" :key="id">
            <input type="hidden" name="ids[]" :value="id">
        </template>
    </form>

    {{-- Single Delete Form --}}
    <form id="single-delete-form" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>
</div>
@endsection
