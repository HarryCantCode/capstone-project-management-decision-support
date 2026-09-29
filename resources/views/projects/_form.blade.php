<?php
    $isEditing = isset($project);
    $todayStr = now()->format('Y-m-d');
    $minStartDate = ($isEditing && isset($project) && $project->start_date && $project->start_date->lt(now()->startOfDay()))
        ? $project->start_date->format('Y-m-d')
        : $todayStr;
    $currentStartDate = old('start_date', isset($project) && $project->start_date ? $project->start_date->format('Y-m-d') : $todayStr);
    $currentTargetDate = old('target_completion_date', isset($project) && $project->target_completion_date ? $project->target_completion_date->format('Y-m-d') : now()->addWeeks(8)->format('Y-m-d'));
    $calculatedWarrantyEnd = \Carbon\Carbon::parse($currentStartDate)->addYear()->format('Y-m-d');
?>

{{-- ─── LEFT COLUMN: Scope, Location & Schedule ──────────────────────────── --}}
<div style="display: flex; flex-direction: column; gap: var(--space-5); min-width: 0;">

    {{-- Card 1: Project Identity & Scope --}}
    <div class="card card-glass">
        <div class="card-header" style="font-weight: 700; font-size: 0.9375rem; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: var(--space-2);">
                <svg style="width: 18px; height: 18px; color: var(--color-primary);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="20" height="14" x="2" y="7" rx="2" ry="2"/>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                </svg>
                Project Identity & Scope
            </div>
            <span class="text-muted" style="font-size: 0.75rem; font-weight: normal;">Core specifications</span>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-4);">
                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="name" class="form-label">Project Name <span class="text-danger">*</span></label>
                    <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $project->name ?? '') }}" maxlength="255" required autofocus placeholder="e.g. Makati Tower Elevator Overhaul">
                    @error('name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="client_name" class="form-label">Client Name <span class="text-danger">*</span></label>
                    <input id="client_name" name="client_name" type="text" class="form-control @error('client_name') is-invalid @enderror" value="{{ old('client_name', $project->client_name ?? '') }}" maxlength="255" required placeholder="e.g. Northpoint Properties Inc.">
                    @error('client_name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-group form-group-premium" style="margin-bottom: var(--space-4);">
                <label for="project_type" class="form-label">Project Type <span class="text-danger">*</span></label>
                <select id="project_type" name="project_type" class="form-control @error('project_type') is-invalid @enderror" required>
                    <option value="" disabled {{ old('project_type', $project->project_type ?? '') === '' ? 'selected' : '' }}>Select Project Type</option>
                    @foreach(['Residential', 'Commercial', 'Industrial', 'Infrastructure'] as $type)
                        <option value="{{ $type }}" {{ old('project_type', $project->project_type ?? '') === $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
                @error('project_type')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
            </div>

            <div class="form-group form-group-premium" style="margin-bottom: 0;">
                <label for="description" class="form-label">Project Description <span class="text-muted">(optional)</span></label>
                <textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" rows="4" maxlength="5000" placeholder="Provide scope of work, technical specifications, and key deliverables...">{{ old('description', $project->description ?? '') }}</textarea>
                @error('description')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    {{-- Card 2: Site Address & Location --}}
    <div class="card card-glass">
        <div class="card-header" style="font-weight: 700; font-size: 0.9375rem; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: var(--space-2);">
                <svg style="width: 18px; height: 18px; color: var(--color-primary);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                Site Address / Project Location
            </div>
            <span class="text-muted" style="font-size: 0.75rem; font-weight: normal;">Field coordinates & logistics</span>
        </div>
        <div class="card-body">
            <p class="form-hint" style="margin-bottom: var(--space-4); margin-top: 0;">Specify the physical site coordinates for field logistics and resource deployment.</p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4);">
                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                    <input
                        id="address"
                        name="address"
                        type="text"
                        class="form-control @error('address') is-invalid @enderror"
                        value="{{ old('address', $project->address ?? '') }}"
                        maxlength="255"
                        required
                        placeholder="e.g. 123 Ayala Avenue, Tower 1"
                    >
                    @error('address')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="barangay" class="form-label">Barangay <span class="text-danger">*</span></label>
                    <input
                        id="barangay"
                        name="barangay"
                        type="text"
                        class="form-control @error('barangay') is-invalid @enderror"
                        value="{{ old('barangay', $project->barangay ?? '') }}"
                        maxlength="255"
                        required
                        placeholder="e.g. Bel-Air"
                    >
                    @error('barangay')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                    <input
                        id="city"
                        name="city"
                        type="text"
                        class="form-control @error('city') is-invalid @enderror"
                        value="{{ old('city', $project->city ?? '') }}"
                        maxlength="255"
                        required
                        placeholder="e.g. Makati City"
                    >
                    @error('city')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Card 3: Schedule & Timeline --}}
    <div class="card card-glass">
        <div class="card-header" style="font-weight: 700; font-size: 0.9375rem; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: var(--space-2);">
                <svg style="width: 18px; height: 18px; color: var(--color-primary);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/>
                    <line x1="16" x2="16" y1="2" y2="6"/>
                    <line x1="8" x2="8" y1="2" y2="6"/>
                    <line x1="3" x2="21" y1="10" y2="10"/>
                </svg>
                Execution Schedule & Timeline
            </div>
            <span class="text-muted" style="font-size: 0.75rem; font-weight: normal;">Milestone boundaries</span>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: var(--space-4);">
                <div class="form-group form-group-premium" style="margin-bottom: 0; min-width: 0;">
                    <label for="start_date" class="form-label" style="display: flex; align-items: center; min-height: 20px;">Start Date <span class="text-danger" style="margin-left: 2px;">*</span></label>
                    <input
                        id="start_date"
                        name="start_date"
                        type="date"
                        min="{{ $minStartDate }}"
                        class="form-control @error('start_date') is-invalid @enderror font-mono"
                        value="{{ $currentStartDate }}"
                        required
                    >
                    <p class="form-hint">Kickoff date.</p>
                    @error('start_date')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group form-group-premium" style="margin-bottom: 0; min-width: 0;">
                    <label for="target_completion_date" class="form-label" style="display: flex; align-items: center; min-height: 20px;">Target Completion Date <span class="text-danger" style="margin-left: 2px;">*</span></label>
                    <input
                        id="target_completion_date"
                        name="target_completion_date"
                        type="date"
                        min="{{ $currentStartDate }}"
                        class="form-control @error('target_completion_date') is-invalid @enderror font-mono"
                        value="{{ $currentTargetDate }}"
                        required
                    >
                    <p class="form-hint">Drives weekly progress & timeline milestones.</p>
                    @error('target_completion_date')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group form-group-premium" style="margin-bottom: 0; min-width: 0;">
                    <label for="warranty_end_date" class="form-label" style="display: flex; justify-content: space-between; align-items: center; min-height: 20px;">
                        <span>Warranty Period</span>
                        <span class="badge" style="background: var(--color-surface-alt); border: 1px solid var(--color-border); font-size: 0.6875rem; padding: 1px 6px; border-radius: var(--radius-pill); font-weight: 600;">1 Year</span>
                    </label>
                    <input
                        id="warranty_end_date"
                        name="warranty_end_date"
                        type="date"
                        class="form-control font-mono @error('warranty_end_date') is-invalid @enderror"
                        value="{{ old('warranty_end_date', isset($project) && $project->warranty_end_date ? $project->warranty_end_date->format('Y-m-d') : $calculatedWarrantyEnd) }}"
                        readonly
                        style="background: var(--color-surface-alt); cursor: default; width: 100%;"
                        title="Calculated 1-year warranty expiration date"
                    >
                    <input type="hidden" id="warranty_period" name="warranty_period" value="{{ old('warranty_period', $project->warranty_period ?? '1 Year') }}">
                    <p class="form-hint" id="warranty-hint">Coverage expires on <strong id="warranty-date-label">{{ \Carbon\Carbon::parse($calculatedWarrantyEnd)->format('M j, Y') }}</strong>.</p>
                    @error('warranty_period')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                    @error('warranty_end_date')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

</div>

{{-- ─── RIGHT COLUMN: Financials, Live Intelligence & Action Panel ──────── --}}
<div class="project-summary-sticky" style="min-width: 0;">

    {{-- Card 1: Financial & Billing Setup --}}
    <div class="card card-glass">
        <div class="card-header" style="font-weight: 700; font-size: 0.9375rem; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: var(--space-2);">
                <svg style="width: 18px; height: 18px; color: var(--color-success);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/>
                    <path d="M12 18V6"/>
                </svg>
                Financials & Billing
            </div>
            <span id="preview-payment-pill" class="status-pill status-pill--{{ strtolower(old('payment_status', $project->payment_status ?? 'Partial')) }}">
                {{ old('payment_status', $project->payment_status ?? 'Partial') }}
            </span>
        </div>
        <div class="card-body">
            <div class="form-group form-group-premium" style="margin-bottom: var(--space-4);">
                <label for="contract_price" class="form-label">Contract Price (PHP ₱) <span class="text-danger">*</span></label>
                <div style="position: relative;">
                    <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-weight: 600; color: var(--color-muted); font-family: var(--font-mono);">₱</span>
                    <input
                        id="contract_price"
                        name="contract_price"
                        type="number"
                        step="0.01"
                        min="0"
                        class="form-control @error('contract_price') is-invalid @enderror font-mono"
                        style="padding-left: 32px;"
                        value="{{ old('contract_price', isset($project) ? $project->contract_price : '') }}"
                        required
                        placeholder="0.00"
                    >
                </div>
                <p class="form-hint">Total agreed budget projected onto Project Costing.</p>
                @error('contract_price')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
            </div>

            <div class="form-group form-group-premium" style="margin-bottom: 0;">
                <label for="payment_status" class="form-label">Payment Status <span class="text-danger">*</span></label>
                <select id="payment_status" name="payment_status" class="form-control @error('payment_status') is-invalid @enderror" required>
                    <option value="" disabled {{ old('payment_status', $project->payment_status ?? '') === '' ? 'selected' : '' }}>Select Payment Status</option>
                    <option value="Paid" {{ old('payment_status', $project->payment_status ?? '') === 'Paid' ? 'selected' : '' }}>Paid</option>
                    <option value="Partial" {{ old('payment_status', $project->payment_status ?? 'Partial') === 'Partial' ? 'selected' : '' }}>Partial</option>
                </select>
                <p class="form-hint">Billing milestone collection tracking.</p>
                @error('payment_status')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    {{-- Card 2: Live Overview Preview --}}
    <div class="card card-glass" style="border-left: 4px solid var(--color-primary);">
        <div class="card-header" style="font-weight: 700; font-size: 0.9375rem; display: flex; align-items: center; justify-content: space-between;">
            <span>Project Preview</span>
            <x-status-pill :status="$project->status ?? 'pending'" />
        </div>
        <div class="card-body" style="padding: var(--space-4); font-size: 0.8125rem;">
            <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: var(--space-2); border-bottom: 1px solid var(--color-border);">
                    <span class="text-muted">Code</span>
                    <span class="font-mono font-bold" id="preview-code">{{ $project->project_code ?? 'DEX-' . now()->year . '-NEW' }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: var(--space-2); border-bottom: 1px solid var(--color-border);">
                    <span class="text-muted">Est. Duration</span>
                    <span class="font-mono font-bold" id="preview-duration" style="color: var(--color-accent);">8 Weeks</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: var(--space-2); border-bottom: 1px solid var(--color-border);">
                    <span class="text-muted">Warranty Limit</span>
                    <span class="font-mono font-bold text-success" id="preview-warranty-end">{{ \Carbon\Carbon::parse($calculatedWarrantyEnd)->format('M j, Y') }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <span class="text-muted">Location</span>
                    <span id="preview-location" style="text-align: right; max-width: 60%; font-weight: 500;">
                        {{ isset($project) && $project->full_address ? $project->full_address : 'Address pending' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Card 3: Action Submission Panel --}}
    <div class="card card-glass">
        <div class="card-body" style="padding: var(--space-4);">
            <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; justify-content: center; font-weight: 600;">
                    {{ $isEditing ? 'Save Changes' : 'Create Project' }}
                </button>
                <a href="{{ $isEditing ? route('projects.show', $project) : route('projects.index') }}" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                    Cancel
                </a>
            </div>
            <p class="text-muted" style="font-size: 0.6875rem; text-align: center; margin-top: var(--space-3); margin-bottom: 0;">
                All actions are authenticated and recorded in the audit trail.
            </p>
        </div>
    </div>

</div>

{{-- ─── Live Dynamic Preview & Synchronization Script ──────────────────── --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const startDateInput = document.getElementById('start_date');
        const targetCompletionInput = document.getElementById('target_completion_date');
        const warrantyEndDateInput = document.getElementById('warranty_end_date');
        const warrantyDateLabel = document.getElementById('warranty-date-label');
        const previewWarrantyEnd = document.getElementById('preview-warranty-end');
        const previewDuration = document.getElementById('preview-duration');
        const paymentSelect = document.getElementById('payment_status');
        const previewPaymentPill = document.getElementById('preview-payment-pill');
        const addressInput = document.getElementById('address');
        const barangayInput = document.getElementById('barangay');
        const cityInput = document.getElementById('city');
        const previewLocation = document.getElementById('preview-location');

        function updateDatesAndWarranty() {
            if (!startDateInput || !startDateInput.value) return;

            const selectedDateVal = startDateInput.value;
            
            // Sync target completion date min
            if (targetCompletionInput) {
                targetCompletionInput.min = selectedDateVal;
                if (targetCompletionInput.value && targetCompletionInput.value < selectedDateVal) {
                    targetCompletionInput.value = selectedDateVal;
                }
            }

            // Calculate 1 year warranty
            const parts = selectedDateVal.split('-');
            if (parts.length === 3) {
                const year = parseInt(parts[0], 10) + 1;
                const month = parts[1];
                const day = parts[2];
                const nextYearStr = `${year}-${month}-${day}`;

                if (warrantyEndDateInput) {
                    warrantyEndDateInput.value = nextYearStr;
                }

                const dateObj = new Date(year, parseInt(month, 10) - 1, parseInt(day, 10));
                const formatted = dateObj.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });

                if (warrantyDateLabel) {
                    warrantyDateLabel.textContent = formatted;
                }
                if (previewWarrantyEnd) {
                    previewWarrantyEnd.textContent = formatted;
                }
            }

            calculateDuration();
        }

        function calculateDuration() {
            if (!startDateInput || !targetCompletionInput || !previewDuration) return;
            const start = new Date(startDateInput.value);
            const end = new Date(targetCompletionInput.value);
            if (!isNaN(start.getTime()) && !isNaN(end.getTime()) && end >= start) {
                const diffDays = Math.round((end - start) / (1000 * 60 * 60 * 24));
                const weeks = Math.round(diffDays / 7);
                if (weeks > 0) {
                    previewDuration.textContent = `${weeks} ${weeks === 1 ? 'Week' : 'Weeks'} (${diffDays} days)`;
                } else {
                    previewDuration.textContent = `${diffDays} ${diffDays === 1 ? 'Day' : 'Days'}`;
                }
            } else {
                previewDuration.textContent = '—';
            }
        }

        function updatePaymentPill() {
            if (!paymentSelect || !previewPaymentPill) return;
            const val = paymentSelect.value || 'Partial';
            previewPaymentPill.textContent = val;
            previewPaymentPill.className = `status-pill status-pill--${val.toLowerCase()}`;
        }

        function updateLocation() {
            if (!previewLocation) return;
            const parts = [
                addressInput ? addressInput.value.trim() : '',
                barangayInput ? barangayInput.value.trim() : '',
                cityInput ? cityInput.value.trim() : ''
            ].filter(Boolean);
            previewLocation.textContent = parts.length > 0 ? parts.join(', ') : 'Address pending';
        }

        if (startDateInput) {
            startDateInput.addEventListener('change', updateDatesAndWarranty);
            startDateInput.addEventListener('input', updateDatesAndWarranty);
        }

        if (targetCompletionInput) {
            targetCompletionInput.addEventListener('change', calculateDuration);
            targetCompletionInput.addEventListener('input', calculateDuration);
        }

        if (paymentSelect) {
            paymentSelect.addEventListener('change', updatePaymentPill);
        }

        [addressInput, barangayInput, cityInput].forEach(function (input) {
            if (input) {
                input.addEventListener('input', updateLocation);
                input.addEventListener('change', updateLocation);
            }
        });

        // Initialize live values
        calculateDuration();
        updatePaymentPill();
        updateLocation();
    });
</script>
