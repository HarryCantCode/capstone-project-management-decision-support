<?php
    $isEditing = isset($project);
    $todayStr = now()->format('Y-m-d');
    $minStartDate = ($isEditing && isset($project) && $project->start_date && $project->start_date->lt(now()->startOfDay()))
        ? $project->start_date->format('Y-m-d')
        : $todayStr;
    $currentStartDate = old('start_date', isset($project) && $project->start_date ? $project->start_date->format('Y-m-d') : $todayStr);
    $baseDate = \Carbon\Carbon::parse($currentStartDate)->gt(now()->startOfDay())
        ? \Carbon\Carbon::parse($currentStartDate)
        : now()->startOfDay();
    $minTargetCompletionDate = $baseDate->copy()->addMonth()->format('Y-m-d');
    $currentTargetDate = old('target_completion_date', isset($project) && $project->target_completion_date ? $project->target_completion_date->format('Y-m-d') : now()->addWeeks(8)->format('Y-m-d'));
    $calculatedWarrantyEnd = \Carbon\Carbon::parse($currentStartDate)->addYear()->format('Y-m-d');
?>

{{-- ─── LEFT COLUMN: Scope, Location & Schedule ──────────────────────────── --}}
<div style="display: flex; flex-direction: column; gap: var(--space-5); min-width: 0;">

    {{-- ─── Form Section Quick-Jump Anchor Bar (Zero Scroll Hassle) ─────────── --}}
    <nav class="pms-quick-nav" aria-label="Section Quick Jump">
        <span class="pms-quick-nav-label">Jump:</span>
        <div class="pms-quick-nav-track">
            <a href="#section-identity" class="pms-quick-nav-link" onclick="document.getElementById('section-identity')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); return false;">
                Identity
            </a>
            <a href="#section-specs" class="pms-quick-nav-link" onclick="document.getElementById('section-specs')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); return false;">
                Specs
            </a>
            <a href="#section-location" class="pms-quick-nav-link" onclick="document.getElementById('section-location')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); return false;">
                Location
            </a>
            <a href="#section-schedule" class="pms-quick-nav-link" onclick="document.getElementById('section-schedule')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); return false;">
                Schedule
            </a>
            @if (!$isEditing)
                <a href="#project-manpower-section" class="pms-quick-nav-link" onclick="document.getElementById('project-manpower-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); return false;">
                    Manpower
                    <span class="pms-nav-counter" id="nav-counter-manpower">0</span>
                </a>
                <a href="#project-expenses-section" class="pms-quick-nav-link" onclick="document.getElementById('project-expenses-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); return false;">
                    Materials
                    <span class="pms-nav-counter" id="nav-counter-expenses">0</span>
                </a>
                <a href="#project-attachments-section" class="pms-quick-nav-link" onclick="document.getElementById('project-attachments-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); return false;">
                    Files
                    <span class="pms-nav-counter" id="nav-counter-attachments">0</span>
                </a>
            @endif
            <a href="#section-financials" class="pms-quick-nav-link" onclick="document.getElementById('section-financials')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); return false;">
                Financials
            </a>
        </div>
    </nav>

    {{-- Card 1: Project Identity & Scope --}}
    <div class="card card-glass" id="section-identity">
        <div class="card-header" style="font-weight: 700; font-size: 0.9375rem; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: var(--space-2);">
                <svg style="width: 18px; height: 18px; color: var(--color-accent);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                    <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $project->name ?? '') }}" maxlength="255" required autofocus placeholder="e.g. Makati Tower Elevator Overhaul" autocomplete="off">
                    <div id="name-similarity-feedback" style="display: none; font-size: 0.75rem; margin-top: 4px; padding: 6px 10px; border-radius: var(--radius-sm); border: 1px solid var(--color-border);"></div>
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

    {{-- Card 2: Elevator Technical Specifications --}}
    <div class="card card-glass" id="section-specs">
        <div class="card-header" style="font-weight: 700; font-size: 0.9375rem; display: flex; align-items: center; justify-content: space-between;">
            <div>
                Elevator Technical Specifications
            </div>
            <span class="text-muted" style="font-size: 0.75rem; font-weight: normal;">Mechanical, Electrical &amp; Finishes</span>
        </div>
        <div class="card-body">
            <p class="form-hint" style="margin-bottom: var(--space-4); margin-top: 0;">Core engineering parameters, elevator cab interior finishes, and operating mechanisms.</p>

            {{-- Row 1: Rated Capacity, Lifting Speed, No. of Stops --}}
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-4);">
                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="rated_capacity" class="form-label">Rated Capacity <span class="text-danger">*</span></label>
                    <input
                        id="rated_capacity"
                        name="rated_capacity"
                        type="text"
                        list="rated_capacity_options"
                        class="form-control font-mono @error('rated_capacity') is-invalid @enderror"
                        value="{{ old('rated_capacity', $project->rated_capacity ?? '') }}"
                        maxlength="100"
                        required
                        placeholder="e.g. 500 kgs"
                    >
                    <datalist id="rated_capacity_options">
                        <option value="300 kgs">
                        <option value="500 kgs">
                        <option value="800 kgs">
                        <option value="1000 kgs">
                        <option value="1250 kgs">
                        <option value="1600 kgs">
                        <option value="2000 kgs">
                        <option value="2500 kgs">
                        <option value="3000 kgs">
                    </datalist>
                    <p class="form-hint">Payload capacity in kgs (e.g. 500 kgs).</p>
                    @error('rated_capacity')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="lifting_speed" class="form-label">Lifting Speed <span class="text-danger">*</span></label>
                    <input
                        id="lifting_speed"
                        name="lifting_speed"
                        type="text"
                        list="lifting_speed_options"
                        class="form-control font-mono @error('lifting_speed') is-invalid @enderror"
                        value="{{ old('lifting_speed', $project->lifting_speed ?? '') }}"
                        maxlength="100"
                        required
                        placeholder="e.g. 6.0 m/min."
                    >
                    <datalist id="lifting_speed_options">
                        <option value="6.0 m/min.">
                        <option value="9.0 m/min.">
                        <option value="12.0 m/min.">
                        <option value="15.0 m/min.">
                        <option value="20.0 m/min.">
                        <option value="30.0 m/min.">
                        <option value="45.0 m/min.">
                        <option value="60.0 m/min.">
                        <option value="90.0 m/min.">
                        <option value="105.0 m/min.">
                        <option value="120.0 m/min.">
                    </datalist>
                    <p class="form-hint">Travel speed in m/min (e.g. 6.0 m/min.).</p>
                    @error('lifting_speed')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="no_of_stops" class="form-label">No. of Stops <span class="text-danger">*</span></label>
                    <input
                        id="no_of_stops"
                        name="no_of_stops"
                        type="number"
                        min="1"
                        max="500"
                        step="1"
                        class="form-control font-mono @error('no_of_stops') is-invalid @enderror"
                        value="{{ old('no_of_stops', $project->no_of_stops ?? '') }}"
                        required
                        placeholder="e.g. 3"
                    >
                    <p class="form-hint">Total landings / stops served (e.g. 3 stops).</p>
                    @error('no_of_stops')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Row 2: Cage Dimension & Door Operation --}}
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-4);">
                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="cage_dimension" class="form-label">Cage Dimension <span class="text-danger">*</span></label>
                    <input
                        id="cage_dimension"
                        name="cage_dimension"
                        type="text"
                        list="cage_dimension_options"
                        class="form-control font-mono @error('cage_dimension') is-invalid @enderror"
                        value="{{ old('cage_dimension', $project->cage_dimension ?? '') }}"
                        maxlength="255"
                        required
                        placeholder="e.g. 1.3 mW x 1.5 mD x 1.8 mH"
                    >
                    <datalist id="cage_dimension_options">
                        <option value="1.1 mW x 1.3 mD x 1.8 mH">
                        <option value="1.2 mW x 1.4 mD x 1.8 mH">
                        <option value="1.3 mW x 1.5 mD x 1.8 mH">
                        <option value="1.4 mW x 1.6 mD x 2.0 mH">
                        <option value="1.5 mW x 1.7 mD x 2.2 mH">
                        <option value="1.6 mW x 1.8 mD x 2.3 mH">
                        <option value="1.8 mW x 2.0 mD x 2.4 mH">
                        <option value="2.0 mW x 2.2 mD x 2.5 mH">
                    </datalist>
                    <p class="form-hint">Car width, depth, and height (mW x mD x mH).</p>
                    @error('cage_dimension')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="door_operation" class="form-label">Door Operation <span class="text-danger">*</span></label>
                    @php
                        $selectedDoorOp = old('door_operation', $project->door_operation ?? '');
                        $doorOptions = [
                            'Up Sliding Manual Operation Panel Door',
                            'Up Sliding Vertical Bi-Parting Door',
                            'Automatic Center Opening Panel Door (2-Panel)',
                            'Automatic Center Opening Panel Door (4-Panel)',
                            'Automatic Side Opening Panel Door (2-Panel Telescopic)',
                            'Automatic Side Opening Panel Door (3-Panel Telescopic)',
                            'Manual Swing Panel Door',
                            'Semi-Automatic Swing / Slide Panel Door',
                            'Collapsible Accordion Gate Door',
                        ];
                    @endphp
                    <select id="door_operation" name="door_operation" class="form-control @error('door_operation') is-invalid @enderror" required>
                        <option value="" disabled {{ $selectedDoorOp === '' ? 'selected' : '' }}>Select Door Operation</option>
                        @foreach($doorOptions as $option)
                            <option value="{{ $option }}" {{ $selectedDoorOp === $option ? 'selected' : '' }}>{{ $option }}</option>
                        @endforeach
                    </select>
                    <p class="form-hint">Door opening mechanism and automation method.</p>
                    @error('door_operation')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Row 3: Power Source & Operation --}}
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-4);">
                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="power_source" class="form-label">Power Source <span class="text-danger">*</span></label>
                    <input
                        id="power_source"
                        name="power_source"
                        type="text"
                        list="power_source_options"
                        class="form-control font-mono @error('power_source') is-invalid @enderror"
                        value="{{ old('power_source', $project->power_source ?? '') }}"
                        maxlength="100"
                        required
                        placeholder="e.g. 220V, 3 phase, 60 hz"
                    >
                    <datalist id="power_source_options">
                        <option value="220V, 3 phase, 60 hz">
                        <option value="220V, single phase, 60 hz">
                        <option value="230V, single phase, 60 hz">
                        <option value="380V, 3 phase, 60 hz">
                        <option value="380V, 3 phase, 50 hz">
                        <option value="400V, 3 phase, 50 hz">
                        <option value="440V, 3 phase, 60 hz">
                        <option value="460V, 3 phase, 60 hz">
                        <option value="480V, 3 phase, 60 hz">
                    </datalist>
                    <p class="form-hint">Electrical power supply rating (volts, phase, hz).</p>
                    @error('power_source')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="operation" class="form-label">Operation <span class="text-danger">*</span></label>
                    <input
                        id="operation"
                        name="operation"
                        type="text"
                        list="operation_options"
                        class="form-control @error('operation') is-invalid @enderror"
                        value="{{ old('operation', $project->operation ?? '') }}"
                        maxlength="100"
                        required
                        placeholder="e.g. Push Button Control on each floor"
                    >
                    <datalist id="operation_options">
                        <option value="Push Button Control on each floor">
                        <option value="Simplex Full Collective Control">
                        <option value="Simplex Down Collective Control">
                        <option value="Duplex Collective Control (2-Car Group)">
                        <option value="Triplex / Group Supervisory Control">
                        <option value="Destination Dispatch Control">
                        <option value="Attendant Service / Independent Manual Operation">
                        <option value="Hospital / Emergency Bed Service Priority">
                        <option value="Firefighter Emergency Operation">
                        <option value="Inspection / Maintenance Manual Operation">
                    </datalist>
                    <p class="form-hint">Elevator control and dispatch supervisory system.</p>
                    @error('operation')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Row 4: Wall and Ceiling and Flooring --}}
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--space-4);">
                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="wall_and_ceiling" class="form-label">Wall and Ceiling <span class="text-danger">*</span></label>
                    <input
                        id="wall_and_ceiling"
                        name="wall_and_ceiling"
                        type="text"
                        list="wall_ceiling_options"
                        class="form-control @error('wall_and_ceiling') is-invalid @enderror"
                        value="{{ old('wall_and_ceiling', $project->wall_and_ceiling ?? '') }}"
                        maxlength="255"
                        required
                        placeholder="e.g. Painted Steel Panel"
                    >
                    <datalist id="wall_ceiling_options">
                        <option value="Painted Steel Panel">
                        <option value="Hairline Stainless Steel Panel">
                        <option value="Mirror Stainless Steel Panel">
                        <option value="Etched Stainless Steel Panel">
                        <option value="Powder Coated Steel Panel">
                        <option value="Stainless Steel &amp; Wood Laminate Panel">
                    </datalist>
                    <p class="form-hint">Car enclosure wall finishes &amp; ceiling architecture.</p>
                    @error('wall_and_ceiling')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="flooring" class="form-label">Flooring <span class="text-danger">*</span></label>
                    <input
                        id="flooring"
                        name="flooring"
                        type="text"
                        list="flooring_options"
                        class="form-control @error('flooring') is-invalid @enderror"
                        value="{{ old('flooring', $project->flooring ?? '') }}"
                        maxlength="255"
                        required
                        placeholder="e.g. Checkered Plate Flooring"
                    >
                    <datalist id="flooring_options">
                        <option value="Checkered Plate Flooring">
                        <option value="Granite Tile Flooring">
                        <option value="Heavy-duty Vinyl Tile Flooring">
                        <option value="Anti-Slip Rubber Flooring">
                        <option value="Marble Flooring">
                        <option value="Aluminum Checker Plate Flooring">
                    </datalist>
                    <p class="form-hint">Car platform floor surface material.</p>
                    @error('flooring')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Card 3: Site Address & Location --}}
    <div class="card card-glass" id="section-location">
        <div class="card-header" style="font-weight: 700; font-size: 0.9375rem; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: var(--space-2);">
                <svg style="width: 18px; height: 18px; color: var(--color-accent);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                        placeholder="ex. 123 Ayala Avenue, Tower 1"
                    >
                    @error('address')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="region" class="form-label">Region <span class="text-danger">*</span></label>
                    <select id="region" name="region" class="form-control @error('region') is-invalid @enderror" data-initial-value="{{ old('region', $project->region ?? '') }}" required>
                        <option value="" disabled {{ old('region', $project->region ?? '') ? '' : 'selected' }}>Select Region...</option>
                        @foreach(\App\Support\PhilippineRegions::all() as $regName)
                            <option value="{{ $regName }}" @selected(old('region', $project->region ?? '') === $regName)>
                                {{ $regName }}
                            </option>
                        @endforeach
                    </select>
                    @error('region')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="province" class="form-label">Province <span class="text-danger">*</span></label>
                    <select
                        id="province"
                        name="province"
                        class="form-control @error('province') is-invalid @enderror"
                        data-initial-value="{{ old('province', $project->province ?? '') }}"
                        required
                    >
                        <option value="" disabled {{ old('province', $project->province ?? '') ? '' : 'selected' }}>Select Province...</option>
                        @if(old('province', $project->province ?? ''))
                            <option value="{{ old('province', $project->province ?? '') }}" selected>{{ old('province', $project->province ?? '') }}</option>
                        @endif
                    </select>
                    @error('province')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                    <select
                        id="city"
                        name="city"
                        class="form-control @error('city') is-invalid @enderror"
                        data-initial-value="{{ old('city', $project->city ?? '') }}"
                        required
                    >
                        <option value="" disabled {{ old('city', $project->city ?? '') ? '' : 'selected' }}>Select City / Municipality...</option>
                        @if(old('city', $project->city ?? ''))
                            <option value="{{ old('city', $project->city ?? '') }}" selected>{{ old('city', $project->city ?? '') }}</option>
                        @endif
                    </select>
                    @error('city')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="barangay" class="form-label">Barangay</label>
                    <select
                        id="barangay"
                        name="barangay"
                        class="form-control @error('barangay') is-invalid @enderror"
                        data-initial-value="{{ old('barangay', $project->barangay ?? '') }}"
                    >
                        <option value="">Select Barangay...</option>
                        @if(old('barangay', $project->barangay ?? ''))
                            <option value="{{ old('barangay', $project->barangay ?? '') }}" selected>{{ old('barangay', $project->barangay ?? '') }}</option>
                        @endif
                    </select>
                    @error('barangay')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="postal_code" class="form-label">Postal Code</label>
                    <input
                        id="postal_code"
                        name="postal_code"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        class="form-control font-mono @error('postal_code') is-invalid @enderror"
                        value="{{ old('postal_code', $project->postal_code ?? '') }}"
                        maxlength="10"
                        placeholder="ex. 1226"
                    >
                    @error('postal_code')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Card 3: Schedule & Timeline --}}
    <div class="card card-glass" id="section-schedule">
        <div class="card-header" style="font-weight: 700; font-size: 0.9375rem; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: var(--space-2);">
                <svg style="width: 18px; height: 18px; color: var(--color-accent);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                        min="{{ $minTargetCompletionDate }}"
                        class="form-control @error('target_completion_date') is-invalid @enderror font-mono"
                        value="{{ $currentTargetDate }}"
                        required
                    >
                    <p class="form-hint">At least 1 month from now or start date.</p>
                    @error('target_completion_date')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group form-group-premium" style="margin-bottom: 0; min-width: 0;">
                    <label for="warranty_end_date" class="form-label" style="display: flex; justify-content: space-between; align-items: center; min-height: 20px;">
                        <span style="display: inline-flex; align-items: center; gap: 4px;">
                            Warranty Period
                            <button 
                                type="button" 
                                @click="warrantyInfoModalOpen = true" 
                                class="btn-help-inline" 
                                style="background: none; border: none; padding: 0; color: var(--color-primary); cursor: pointer; display: inline-flex; align-items: center;" 
                                title="Click to view warranty and maintenance inclusion terms"
                            >
                                <svg style="width: 14px; height: 14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                            </button>
                        </span>
                        <button
                            type="button"
                            @click="warrantyInfoModalOpen = true"
                            class="badge" 
                            style="background: var(--color-surface-alt); border: 1px solid var(--color-border); font-size: 0.6875rem; padding: 1px 6px; border-radius: var(--radius-pill); font-weight: 600; cursor: pointer; color: var(--color-ink);"
                            title="Click to view warranty inclusion details"
                        >
                            1 Year · Inclusion
                        </button>
                    </label>
                    <div style="position: relative;">
                        <input
                            id="warranty_end_date"
                            name="warranty_end_date"
                            type="date"
                            class="form-control font-mono @error('warranty_end_date') is-invalid @enderror"
                            value="{{ old('warranty_end_date', isset($project) && $project->warranty_end_date ? $project->warranty_end_date->format('Y-m-d') : $calculatedWarrantyEnd) }}"
                            readonly
                            @click="warrantyInfoModalOpen = true"
                            style="background: var(--color-surface-alt); cursor: pointer; width: 100%;"
                            title="Click to view warranty and maintenance inclusion details"
                        >
                    </div>
                    <input type="hidden" id="warranty_period" name="warranty_period" value="{{ old('warranty_period', $project->warranty_period ?? '1 Year') }}">
                    <p class="form-hint" id="warranty-hint" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 4px;">
                        <span>Coverage expires on <strong id="warranty-date-label" @click="warrantyInfoModalOpen = true" style="cursor: pointer; text-decoration: underline; text-decoration-style: dotted;" title="Click to view warranty inclusion details">{{ \Carbon\Carbon::parse($calculatedWarrantyEnd)->format('M j, Y') }}</strong>.</span>
                        <button 
                            type="button" 
                            @click="warrantyInfoModalOpen = true" 
                            class="table-link" 
                            style="background: none; border: none; padding: 0; font-size: 0.75rem; cursor: pointer; text-decoration: underline;"
                        >
                            View inclusion &rarr;
                        </button>
                    </p>
                    @error('warranty_period')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                    @error('warranty_end_date')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    @if ($isEditing)
        {{-- Card: Project Status Configuration --}}
        <div class="card card-glass">
            <div class="card-header" style="font-weight: 700; font-size: 0.9375rem; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <svg style="width: 18px; height: 18px; color: var(--color-accent);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    Project Status / Lifecycle
                </div>
                <x-status-pill :status="$project->status ?? 'pending'" />
            </div>
            <div class="card-body">
                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="status" class="form-label">Project Status <span class="text-danger">*</span></label>
                    <select id="status" name="status" class="form-control @error('status') is-invalid @enderror">
                        @php
                            $currStatus = old('status', $project->status ?? 'pending');
                        @endphp
                        @if ($project->status === 'completed')
                            <option value="completed" @selected($currStatus === 'completed')>Completed</option>
                            <option value="ongoing" @selected($currStatus === 'ongoing')>In Progress (Ongoing)</option>
                            <option value="delayed" @selected($currStatus === 'delayed')>Delayed</option>
                        @elseif ($project->status === 'delayed')
                            <option value="delayed" @selected($currStatus === 'delayed')>Delayed</option>
                            <option value="ongoing" @selected($currStatus === 'ongoing')>In Progress (Ongoing)</option>
                            <option value="completed" @selected($currStatus === 'completed')>Completed</option>
                            <option value="pending" @selected($currStatus === 'pending')>Pending</option>
                        @elseif ($project->status === 'ongoing')
                            <option value="ongoing" @selected($currStatus === 'ongoing')>In Progress (Ongoing)</option>
                            <option value="completed" @selected($currStatus === 'completed')>Completed</option>
                            <option value="delayed" @selected($currStatus === 'delayed')>Delayed</option>
                            <option value="pending" @selected($currStatus === 'pending')>Pending</option>
                        @else
                            <option value="pending" @selected($currStatus === 'pending')>Pending</option>
                            <option value="ongoing" @selected($currStatus === 'ongoing')>In Progress (Ongoing)</option>
                            <option value="delayed" @selected($currStatus === 'delayed')>Delayed</option>
                            <option value="completed" @selected($currStatus === 'completed')>Completed</option>
                        @endif
                    </select>
                    <p class="form-hint">You can reverse a completed project back to In Progress / Ongoing or adjust status as needed.</p>
                    @error('status')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Card 4: Reason for Update / Audit Notes --}}
        <div class="card card-glass">
            <div class="card-header" style="font-weight: 700; font-size: 0.9375rem; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <svg style="width: 18px; height: 18px; color: var(--color-accent);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Reason for Update &amp; Audit Notes
                </div>
                <span class="text-muted" style="font-size: 0.75rem;">Recorded in audit trail</span>
            </div>
            <div class="card-body">
                <div class="form-group form-group-premium" style="margin-bottom: 0;">
                    <label for="update_notes" class="form-label">
                        Reason / Notes for Updating Project Information <span class="text-danger">*</span>
                    </label>
                    <textarea
                        id="update_notes"
                        name="update_notes"
                        rows="3"
                        class="form-control @error('update_notes') is-invalid @enderror"
                        maxlength="1000"
                        required
                        placeholder="Please state the reason or notes for updating this project's information (e.g., Client requested change in target completion date, updated site address, revised contract price)..."
                    >{{ old('update_notes') }}</textarea>
                    <p class="form-hint">A reason or audit note is required and will be logged into the audit trail history for traceability.</p>
                    @error('update_notes')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    @endif

    @if (!$isEditing)
        {{-- ─── Card 5: Project Manpower & Staffing (Required) ──────────────── --}}
        @php
            $personnelData = ($availablePersonnel ?? collect())->map(function($p) {
                return [
                    'id' => $p->id,
                    'full_name' => $p->full_name,
                    'employee_id' => $p->employee_id,
                    'expertise' => $p->expertise,
                    'is_assigned' => !empty($p->project_id),
                    'project_code' => $p->project?->project_code ?? null,
                ];
            })->values();
            $oldPersonnelIds = array_map('intval', (array) old('personnel_ids', []));
        @endphp
        <div class="card card-glass" id="project-manpower-section"
            x-data="{
                personnelList: {{ Js::from($personnelData) }},
                selectedPersonnel: {{ Js::from($oldPersonnelIds) }},
                searchQuery: '',
                roleFilter: 'all',
                statusFilter: 'all',
                benchmarks: [
                    { role: 'Project Manager', short: 'PM', min: 1, max: 1, label: '1' },
                    { role: 'Site Engineer', short: 'Site Eng', min: 1, max: 1, label: '1' },
                    { role: 'Technician', short: 'Tech', min: 1, max: 2, label: '1-2' },
                    { role: 'QA Inspector', short: 'QA', min: 1, max: 1, label: '1' },
                    { role: 'Foreman', short: 'Foreman', min: 1, max: 1, label: '1' },
                    { role: 'Safety Officer', short: 'Safety', min: 1, max: 1, label: '1' },
                    { role: 'Elevator Control', short: 'Control', min: 1, max: 2, label: '1-2' },
                    { role: 'Worker / Installation Personnel', short: 'Workers', min: 4, max: 6, label: '4-6' }
                ],
                get roleCounts() {
                    const counts = {};
                    this.selectedPersonnel.forEach(id => {
                        const p = this.personnelList.find(item => item.id === id);
                        if (p && p.expertise) {
                            counts[p.expertise] = (counts[p.expertise] || 0) + 1;
                        }
                    });
                    return counts;
                },
                getRoleCount(role) {
                    return this.roleCounts[role] || 0;
                },
                getRoleStatus(bm) {
                    const count = this.getRoleCount(bm.role);
                    if (count === 0) return 'unmet';
                    if (count > bm.max) return 'exceeded';
                    if (count >= bm.min) return 'fulfilled';
                    return 'partial';
                },
                get fulfilledBenchmarkCount() {
                    return this.benchmarks.filter(bm => this.getRoleCount(bm.role) >= bm.min).length;
                },
                filterByRole(role) {
                    this.roleFilter = this.roleFilter === role ? 'all' : role;
                },
                get filteredPersonnel() {
                    const q = this.searchQuery.toLowerCase().trim();
                    return this.personnelList.filter(p => {
                        const matchesSearch = !q || 
                            p.full_name.toLowerCase().includes(q) || 
                            p.employee_id.toLowerCase().includes(q) ||
                            (p.expertise && p.expertise.toLowerCase().includes(q));
                        const matchesRole = this.roleFilter === 'all' || p.expertise === this.roleFilter;
                        const matchesStatus = this.statusFilter === 'all' || 
                            (this.statusFilter === 'available' && !p.is_assigned) ||
                            (this.statusFilter === 'assigned' && p.is_assigned);
                        return matchesSearch && matchesRole && matchesStatus;
                    });
                },
                get exceededRoles() {
                    const warnings = [];
                    for (const bm of this.benchmarks) {
                        const total = this.getRoleCount(bm.role);
                        if (total > bm.max) {
                            warnings.push({
                                role: bm.role,
                                total: total,
                                max: bm.max,
                                recommended: bm.label
                            });
                        }
                    }
                    return warnings;
                },
                toggleSelectFiltered() {
                    const filteredIds = this.filteredPersonnel.map(p => p.id);
                    if (filteredIds.length === 0) return;
                    const allSelected = filteredIds.every(id => this.selectedPersonnel.includes(id));
                    if (allSelected) {
                        this.selectedPersonnel = this.selectedPersonnel.filter(id => !filteredIds.includes(id));
                    } else {
                        this.selectedPersonnel = Array.from(new Set([...this.selectedPersonnel, ...filteredIds]));
                    }
                    this.updatePreview();
                },
                updatePreview() {
                    const count = this.selectedPersonnel.length;
                    const el = document.getElementById('preview-manpower');
                    if (el) {
                        el.textContent = count === 1 ? '1 Personnel' : `${count} Personnel`;
                        el.style.color = count > 0 ? 'var(--color-ink)' : 'var(--color-danger)';
                    }
                    const navEl = document.getElementById('nav-counter-manpower');
                    if (navEl) {
                        navEl.textContent = count;
                    }
                },
                init() {
                    this.$watch('selectedPersonnel', () => this.updatePreview());
                    this.updatePreview();
                }
            }"
        >
            <div class="card-header" style="font-weight: 700; font-size: 0.9375rem; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <svg style="width: 18px; height: 18px; color: var(--color-accent);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    Project Manpower &amp; Staffing <span class="text-danger">*</span>
                </div>
                <span class="text-muted" style="font-size: 0.75rem; font-weight: normal;">Required field workforce</span>
            </div>
            <div class="card-body">
                <p class="form-hint" style="margin-bottom: var(--space-3); margin-top: 0;">
                    Select the field personnel to assign to this project. At least one personnel is required.
                </p>

                {{-- Staffing Benchmark Checklist Panel --}}
                <div class="pms-benchmark-box">
                    <div class="pms-benchmark-top">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <svg style="width: 14px; height: 14px; color: var(--color-accent);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m9 11 3 3L22 4"/>
                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                            </svg>
                            <span style="font-weight: 600; font-size: 0.8125rem; color: var(--color-ink);">Staffing Benchmark Checklist</span>
                            <span class="pms-benchmark-badge" :class="{ 'is-complete': fulfilledBenchmarkCount === benchmarks.length }">
                                <span x-text="fulfilledBenchmarkCount"></span> / <span x-text="benchmarks.length"></span> fulfilled
                            </span>
                        </div>
                        <div style="font-size: 0.6875rem;">
                            <template x-if="roleFilter !== 'all'">
                                <span class="text-muted">
                                    Filtered by: <strong x-text="roleFilter" style="color: var(--color-accent);"></strong> 
                                    (<button type="button" @click="roleFilter = 'all'" style="background: none; border: none; padding: 0; color: var(--color-danger); cursor: pointer; text-decoration: underline; font-size: 0.6875rem;">Reset</button>)
                                </span>
                            </template>
                            <template x-if="roleFilter === 'all'">
                                <span class="text-muted">Click any role to filter roster below</span>
                            </template>
                        </div>
                    </div>

                    <div class="pms-benchmark-chips">
                        <template x-for="bm in benchmarks" :key="bm.role">
                            <button
                                type="button"
                                class="pms-benchmark-chip"
                                :class="{
                                    'status-fulfilled': getRoleStatus(bm) === 'fulfilled',
                                    'status-partial': getRoleStatus(bm) === 'partial',
                                    'status-exceeded': getRoleStatus(bm) === 'exceeded',
                                    'status-unmet': getRoleStatus(bm) === 'unmet',
                                    'is-filtered': roleFilter === bm.role
                                }"
                                @click="filterByRole(bm.role)"
                                :title="'Click to filter list by ' + bm.role"
                            >
                                <span class="pms-benchmark-indicator">
                                    <template x-if="getRoleStatus(bm) === 'fulfilled'">
                                        <svg viewBox="0 0 16 16" fill="currentColor" style="width: 12px; height: 12px;"><path d="M13.78 4.22a.75.75 0 010 1.06l-7.25 7.25a.75.75 0 01-1.06 0L2.22 9.28a.75.75 0 011.06-1.06L6 10.94l6.72-6.72a.75.75 0 011.06 0z"/></svg>
                                    </template>
                                    <template x-if="getRoleStatus(bm) === 'exceeded'">
                                        <span style="font-weight: 700; font-size: 0.6875rem; line-height: 1;">!</span>
                                    </template>
                                    <template x-if="getRoleStatus(bm) === 'partial'">
                                        <span style="font-size: 0.625rem; line-height: 1;">◐</span>
                                    </template>
                                    <template x-if="getRoleStatus(bm) === 'unmet'">
                                        <span style="font-size: 0.625rem; line-height: 1; opacity: 0.4;">○</span>
                                    </template>
                                </span>
                                <span class="pms-benchmark-label" x-text="bm.short"></span>
                                <span class="pms-benchmark-ratio font-mono" x-text="getRoleCount(bm.role) + '/' + bm.label"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Exceeded Staffing Notice --}}
                <template x-if="exceededRoles.length > 0">
                    <div class="alert alert-warning" style="margin-bottom: var(--space-3); padding: 8px 12px; font-size: 0.75rem;">
                        <strong>Staffing Notice:</strong>
                        <template x-for="item in exceededRoles" :key="item.role">
                            <span style="display: block; margin-top: 2px;">
                                • <span x-text="item.role"></span>: selected <strong x-text="item.total"></strong> exceeds recommended max of <strong x-text="item.recommended"></strong>.
                            </span>
                        </template>
                    </div>
                </template>

                {{-- Search & Filtering Toolbar (Uniform 34px controls) --}}
                <div class="pms-toolbar">
                    <div style="flex: 1 1 200px; min-width: 180px; position: relative;">
                        <input
                            type="text"
                            x-model="searchQuery"
                            class="form-control pms-toolbar-item"
                            placeholder="Search by name, ID, or role..."
                            style="padding-left: 30px !important;"
                        >
                        <svg style="position: absolute; left: 9px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; color: var(--color-muted); pointer-events: none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </div>

                    <select x-model="roleFilter" class="pms-toolbar-select">
                        <option value="all">All Roles</option>
                        @foreach(\App\Models\Personnel::EXPERTISE_OPTIONS as $exp)
                            <option value="{{ $exp }}">{{ $exp }}</option>
                        @endforeach
                    </select>

                    <select x-model="statusFilter" class="pms-toolbar-select">
                        <option value="all">All Status</option>
                        <option value="available">Available Only</option>
                        <option value="assigned">Assigned</option>
                    </select>

                    <button type="button" class="btn btn-secondary pms-toolbar-btn" @click="toggleSelectFiltered()">
                        <span x-text="filteredPersonnel.length > 0 && filteredPersonnel.every(p => selectedPersonnel.includes(p.id)) ? 'Deselect All' : 'Select Filtered'"></span>
                    </button>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; font-size: 0.75rem;">
                    <span style="font-weight: 600; color: var(--color-ink);" x-text="selectedPersonnel.length + ' personnel selected' + (selectedPersonnel.length === 0 ? ' (at least 1 required)' : '')"></span>
                    <span class="text-muted" x-text="'Showing ' + filteredPersonnel.length + ' of ' + personnelList.length"></span>
                </div>

                {{-- Scrollable List of Personnel --}}
                <div class="pms-roster-box">
                    <template x-for="p in filteredPersonnel" :key="p.id">
                        <label class="pms-personnel-row" :class="{ 'is-selected': selectedPersonnel.includes(p.id) }">
                            <div class="pms-personnel-check">
                                <input
                                    type="checkbox"
                                    name="personnel_ids[]"
                                    :value="p.id"
                                    x-model.number="selectedPersonnel"
                                >
                            </div>
                            <div class="pms-personnel-body">
                                <div class="pms-personnel-name-group">
                                    <span class="pms-personnel-name" x-text="p.full_name"></span>
                                    <span class="pms-personnel-id" x-text="p.employee_id"></span>
                                </div>
                                <div class="pms-personnel-meta">
                                    <span class="badge" style="background: var(--color-surface-alt); border: 1px solid var(--color-border); font-size: 0.6875rem; font-weight: 600;" x-text="p.expertise"></span>
                                    <span class="badge" style="background: rgba(46, 125, 83, 0.12); color: var(--color-success); font-size: 0.6875rem;" x-show="!p.is_assigned">Available</span>
                                    <span class="badge" style="background: rgba(201, 138, 44, 0.12); color: #C98A2C; font-size: 0.6875rem;" x-show="p.is_assigned" x-text="p.project_code ? 'Assigned: ' + p.project_code : 'Assigned'"></span>
                                </div>
                            </div>
                        </label>
                    </template>
                    <template x-if="filteredPersonnel.length === 0">
                        <div style="padding: var(--space-4); text-align: center; color: var(--color-muted); font-size: 0.8125rem;">
                            No personnel match your search or filter criteria.
                        </div>
                    </template>
                </div>

                @error('personnel_ids')
                    <div class="invalid-feedback" style="display: block; margin-top: 6px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- ─── Card 6: Initial Materials & Expenditures (Optional) ──────────── --}}
        @php
            $catalogData = ($catalogMaterials ?? collect())->map(function($m) {
                return [
                    'id' => $m->id,
                    'resource_code' => $m->resource_code,
                    'name' => $m->name,
                    'model' => $m->model ?? '',
                    'unit_price' => (float) ($m->price > 0 ? $m->price : $m->unit_cost),
                    'quantity_available' => (int) $m->quantity_available,
                ];
            })->values();
            $oldExpenses = (array) old('expenses', []);
        @endphp
        <div class="card card-glass" id="project-expenses-section"
            x-data="{
                catalogMaterials: {{ Js::from($catalogData) }},
                expenses: {{ Js::from($oldExpenses) }},
                addCatalogItem() {
                    this.expenses.push({
                        mode: 'catalog',
                        resource_id: '',
                        description: '',
                        cost_type: 'materials',
                        quantity: 1,
                        unit_price: 0,
                        amount: '0.00'
                    });
                    this.updateSpendPreview();
                },
                addManualItem() {
                    this.expenses.push({
                        mode: 'manual',
                        resource_id: '',
                        description: '',
                        cost_type: 'materials',
                        quantity: 1,
                        unit_price: 0,
                        amount: ''
                    });
                    this.updateSpendPreview();
                },
                removeExpense(index) {
                    this.expenses.splice(index, 1);
                    this.updateSpendPreview();
                },
                onSelectCatalogMaterial(index) {
                    const item = this.expenses[index];
                    const res = this.catalogMaterials.find(m => m.id == item.resource_id);
                    if (res) {
                        const modelInfo = res.model ? ' (' + res.model + ')' : '';
                        item.description = `${res.name}${modelInfo} [${res.resource_code}]`;
                        item.unit_price = res.unit_price;
                        item.quantity = Math.max(1, parseInt(item.quantity, 10) || 1);
                        item.cost_type = 'materials';
                        this.recalculateCatalogAmount(index);
                    }
                },
                recalculateCatalogAmount(index) {
                    const item = this.expenses[index];
                    const qty = Math.max(1, parseInt(item.quantity, 10) || 1);
                    item.quantity = qty;
                    const total = (qty * (item.unit_price || 0)).toFixed(2);
                    item.amount = total;
                    this.updateSpendPreview();
                },
                formatAmountInput(e, index) {
                    let val = e.target.value.replace(/[^0-9.]/g, '');
                    this.expenses[index].amount = val;
                    this.updateSpendPreview();
                },
                get totalExpenses() {
                    return this.expenses.reduce((sum, item) => {
                        const raw = String(item.amount || '0').replace(/,/g, '');
                        const parsed = parseFloat(raw);
                        return sum + (isNaN(parsed) ? 0 : parsed);
                    }, 0);
                },
                formatCurrency(val) {
                    return '₱' + (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
                updateSpendPreview() {
                    const el = document.getElementById('preview-expenses');
                    if (el) {
                        el.textContent = this.formatCurrency(this.totalExpenses);
                    }
                    const navEl = document.getElementById('nav-counter-expenses');
                    if (navEl) {
                        navEl.textContent = this.expenses.length;
                    }
                },
                init() {
                    this.$watch('expenses', () => this.updateSpendPreview());
                    this.updateSpendPreview();
                }
            }"
        >
            <div class="card-header" style="font-weight: 700; font-size: 0.9375rem; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <svg style="width: 18px; height: 18px; color: var(--color-accent);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="16" rx="2"/>
                        <line x1="2" y1="10" x2="22" y2="10"/>
                    </svg>
                    Initial Materials &amp; Expenditures
                </div>
                <span class="text-muted" style="font-size: 0.75rem; font-weight: normal;">Optional startup costs</span>
            </div>
            <div class="card-body">
                <p class="form-hint" style="margin-bottom: var(--space-3); margin-top: 0;">
                    Log initial materials pulled from inventory or upfront project expenses to be charged against the project budget.
                </p>

                {{-- Action Toolbar with uniform 34px buttons --}}
                <div class="pms-toolbar">
                    <button type="button" @click="addCatalogItem()" class="btn btn-secondary pms-toolbar-btn">
                        <svg style="width: 14px; height: 14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                        </svg>
                        Choose from Catalog
                    </button>
                    <button type="button" @click="addManualItem()" class="btn btn-secondary pms-toolbar-btn">
                        <svg style="width: 14px; height: 14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add Manual Expense
                    </button>
                </div>

                {{-- Empty State --}}
                <template x-if="expenses.length === 0">
                    <div style="border: 1px dashed var(--color-border); border-radius: var(--radius-md); padding: var(--space-4); text-align: center; color: var(--color-muted); font-size: 0.8125rem;">
                        No initial materials or expenditures added. You can leave this empty or add items above.
                    </div>
                </template>

                {{-- Repeater Items List (Bounded to 275px max height to eliminate scroll hassle) --}}
                <div class="pms-expenses-scroll">
                    <template x-for="(item, index) in expenses" :key="index">
                        <div class="pms-expense-card">
                            <div class="pms-expense-card-header">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-weight: 600; font-size: 0.8125rem; color: var(--color-ink);" x-text="'Item #' + (index + 1)"></span>
                                    <span class="badge" style="background: var(--color-surface-alt); border: 1px solid var(--color-border); font-size: 0.6875rem;" x-text="item.mode === 'catalog' ? 'Catalog Material' : 'Manual Entry'"></span>
                                </div>
                                <button type="button" @click="removeExpense(index)" class="pms-file-remove-btn" title="Remove this expense item">
                                    ✕ Remove
                                </button>
                            </div>

                            {{-- Catalog Mode Fields --}}
                            <template x-if="item.mode === 'catalog'">
                                <div>
                                    <div class="pms-expense-grid-catalog">
                                        <div class="form-group form-group-premium" style="margin-bottom: 0;">
                                            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 4px;">Material <span class="text-danger">*</span></label>
                                            <select
                                                x-model="item.resource_id"
                                                @change="onSelectCatalogMaterial(index)"
                                                class="form-control pms-toolbar-item"
                                                required
                                            >
                                                <option value="" disabled>Select Material from Catalog...</option>
                                                <template x-for="mat in catalogMaterials" :key="mat.id">
                                                    <option :value="mat.id" x-text="'[' + mat.resource_code + '] ' + mat.name + (mat.model ? ' (' + mat.model + ')' : '') + ' · Stock: ' + mat.quantity_available + ' · ₱' + Number(mat.unit_price).toLocaleString('en-US', { minimumFractionDigits: 2 })"></option>
                                                </template>
                                            </select>
                                        </div>

                                        <div class="form-group form-group-premium" style="margin-bottom: 0;">
                                            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 4px;">Qty <span class="text-danger">*</span></label>
                                            <input
                                                type="number"
                                                x-model.number="item.quantity"
                                                @input="recalculateCatalogAmount(index)"
                                                min="1"
                                                class="form-control pms-toolbar-item font-mono"
                                                placeholder="1"
                                                required
                                            >
                                        </div>

                                        <div class="form-group form-group-premium" style="margin-bottom: 0;">
                                            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 4px;">Item Total</label>
                                            <div style="height: 34px; display: flex; align-items: center; font-weight: 700; color: var(--color-ink); font-family: var(--font-mono); font-size: 0.875rem;" x-text="formatCurrency(item.amount)"></div>
                                        </div>
                                    </div>

                                    <template x-if="item.resource_id && catalogMaterials.find(m => m.id == item.resource_id) && item.quantity > catalogMaterials.find(m => m.id == item.resource_id).quantity_available">
                                        <p class="form-hint" style="color: var(--color-danger); margin-top: 4px; margin-bottom: 0; font-size: 0.75rem;">
                                            Warning: Requested quantity (<span x-text="item.quantity"></span>) exceeds stock (<span x-text="catalogMaterials.find(m => m.id == item.resource_id).quantity_available"></span>).
                                        </p>
                                    </template>

                                    <input type="hidden" :name="'expenses[' + index + '][resource_id]'" :value="item.resource_id">
                                    <input type="hidden" :name="'expenses[' + index + '][description]'" :value="item.description">
                                    <input type="hidden" :name="'expenses[' + index + '][cost_type]'" value="materials">
                                    <input type="hidden" :name="'expenses[' + index + '][quantity]'" :value="item.quantity">
                                    <input type="hidden" :name="'expenses[' + index + '][amount]'" :value="item.amount">
                                </div>
                            </template>

                            {{-- Manual Mode Fields --}}
                            <template x-if="item.mode !== 'catalog'">
                                <div>
                                    <div class="pms-expense-grid-manual">
                                        <div class="form-group form-group-premium" style="margin-bottom: 0;">
                                            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 4px;">Cost Type <span class="text-danger">*</span></label>
                                            <select :name="'expenses[' + index + '][cost_type]'" x-model="item.cost_type" class="form-control pms-toolbar-item" required>
                                                <option value="materials">Materials</option>
                                                <option value="equipment">Equipment</option>
                                                <option value="labor">Labor</option>
                                                <option value="subcontractor">Subcontractor</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>

                                        <div class="form-group form-group-premium" style="margin-bottom: 0;">
                                            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 4px;">Description <span class="text-danger">*</span></label>
                                            <input
                                                type="text"
                                                :name="'expenses[' + index + '][description]'"
                                                x-model="item.description"
                                                class="form-control pms-toolbar-item"
                                                placeholder="e.g. Mobilization logistics"
                                                required
                                            >
                                        </div>

                                        <div class="form-group form-group-premium" style="margin-bottom: 0;">
                                            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 4px;">Qty</label>
                                            <input
                                                type="number"
                                                :name="'expenses[' + index + '][quantity]'"
                                                x-model.number="item.quantity"
                                                min="1"
                                                class="form-control pms-toolbar-item font-mono"
                                                placeholder="1"
                                            >
                                        </div>

                                        <div class="form-group form-group-premium" style="margin-bottom: 0;">
                                            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 4px;">Amount (₱) <span class="text-danger">*</span></label>
                                            <input
                                                type="text"
                                                :name="'expenses[' + index + '][amount]'"
                                                :value="item.amount"
                                                @input="formatAmountInput($event, index)"
                                                class="form-control pms-toolbar-item font-mono"
                                                placeholder="0.00"
                                                required
                                            >
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Summary Footer --}}
                <template x-if="expenses.length > 0">
                    <div style="background: var(--color-surface-alt); border-radius: var(--radius-sm); padding: 8px 12px; margin-top: var(--space-3); display: flex; justify-content: space-between; align-items: center; border: 1px solid var(--color-border);">
                        <span class="text-muted" style="font-size: 0.8125rem;">Total Initial Expenditures (<span x-text="expenses.length"></span> items):</span>
                        <span class="font-mono font-bold" style="font-size: 0.9375rem; color: var(--color-ink);" x-text="formatCurrency(totalExpenses)"></span>
                    </div>
                </template>
            </div>
        </div>

        {{-- ─── Card 7: Project File Attachments (Optional, Multi-Upload & Categorized) --}}
        <div class="card card-glass" id="project-attachments-section"
            x-data="{
                defaultCategory: 'project_document',
                attachments: [],
                isDragging: false,
                categories: [
                    { key: 'project_document', label: 'Project document' },
                    { key: 'project_transaction', label: 'Project transaction' },
                    { key: 'invoice', label: 'Project invoice' },
                    { key: 'project_notes', label: 'Project notes' },
                    { key: 'project_drawing', label: 'Project drawing / blueprint' }
                ],
                formatSize(bytes) {
                    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
                    if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
                    return bytes + ' B';
                },
                handleFileSelect(e) {
                    const files = e.target.files;
                    if (!files || files.length === 0) return;
                    this.addFiles(files);
                    e.target.value = '';
                },
                handleDrop(e) {
                    this.isDragging = false;
                    const files = e.dataTransfer ? e.dataTransfer.files : null;
                    if (!files || files.length === 0) return;
                    this.addFiles(files);
                },
                addFiles(files) {
                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        this.attachments.push({
                            id: Date.now() + '_' + Math.random().toString(36).substr(2, 9),
                            file: file,
                            name: file.name,
                            size: file.size,
                            formattedSize: this.formatSize(file.size),
                            category: this.defaultCategory,
                            description: ''
                        });
                    }
                    this.syncInputs();
                    this.updateFilesPreview();
                },
                removeAttachment(index) {
                    this.attachments.splice(index, 1);
                    this.syncInputs();
                    this.updateFilesPreview();
                },
                clearAll() {
                    this.attachments = [];
                    this.syncInputs();
                    this.updateFilesPreview();
                },
                syncInputs() {
                    const container = document.getElementById('attachments_form_inputs');
                    if (!container) return;
                    container.innerHTML = '';
                    this.attachments.forEach((att, idx) => {
                        const fileInput = document.createElement('input');
                        fileInput.type = 'file';
                        fileInput.name = `attachments[${idx}][file]`;
                        fileInput.style.display = 'none';
                        try {
                            const dt = new DataTransfer();
                            dt.items.add(att.file);
                            fileInput.files = dt.files;
                        } catch (err) {
                            console.error('DataTransfer error', err);
                        }
                        container.appendChild(fileInput);

                        const catInput = document.createElement('input');
                        catInput.type = 'hidden';
                        catInput.name = `attachments[${idx}][category]`;
                        catInput.value = att.category;
                        container.appendChild(catInput);

                        const descInput = document.createElement('input');
                        descInput.type = 'hidden';
                        descInput.name = `attachments[${idx}][description]`;
                        descInput.value = att.description || '';
                        container.appendChild(descInput);
                    });
                },
                updateFilesPreview() {
                    const el = document.getElementById('preview-attachments');
                    const count = this.attachments.length;
                    if (el) {
                        el.textContent = count === 1 ? '1 File' : `${count} Files`;
                    }
                    const navEl = document.getElementById('nav-counter-attachments');
                    if (navEl) {
                        navEl.textContent = count;
                    }
                },
                init() {
                    this.$watch('attachments', () => {
                        this.syncInputs();
                        this.updateFilesPreview();
                    });
                    this.updateFilesPreview();
                }
            }"
        >
            <div class="card-header" style="font-weight: 700; font-size: 0.9375rem; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <svg style="width: 18px; height: 18px; color: var(--color-accent);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                    </svg>
                    Project File Attachments
                </div>
                <span class="text-muted" style="font-size: 0.75rem; font-weight: normal;">Optional multi-upload</span>
            </div>
            <div class="card-body">
                <p class="form-hint" style="margin-bottom: var(--space-3); margin-top: 0;">
                    Attach files categorized into Project document, Project transaction, Project invoice, Project notes, or Project drawing / blueprint. Multiple files can be selected at once.
                </p>

                {{-- Hidden input for file picker --}}
                <input
                    type="file"
                    x-ref="batchFileInput"
                    @change="handleFileSelect($event)"
                    multiple
                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
                    style="display: none;"
                >

                {{-- Hidden container for synthesized form inputs --}}
                <div id="attachments_form_inputs" style="display: none;"></div>

                {{-- Clean Technical Dropzone --}}
                <div 
                    class="pms-dropzone" 
                    :class="{ 'is-dragging': isDragging }"
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="handleDrop($event)"
                    @click="$refs.batchFileInput.click()"
                >
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px;">
                        <svg style="width: 24px; height: 24px; color: var(--color-accent);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                        <div style="font-weight: 600; font-size: 0.8125rem; color: var(--color-ink);">
                            Click to browse files or drag and drop here
                        </div>
                        <span class="text-muted" style="font-size: 0.75rem;">
                            PDF, DOCX, DOC, JPG, PNG, WEBP (Max 20MB per file) · Multiple files supported
                        </span>
                    </div>
                </div>

                {{-- Category Setting & Toolbar with uniform 34px controls --}}
                <div class="pms-toolbar">
                    <span style="font-size: 0.8125rem; color: var(--color-ink); font-weight: 500;">
                        Category for new files:
                    </span>
                    <select x-model="defaultCategory" class="pms-toolbar-select">
                        <template x-for="cat in categories" :key="cat.key">
                            <option :value="cat.key" x-text="cat.label"></option>
                        </template>
                    </select>

                    <button type="button" @click="$refs.batchFileInput.click()" class="btn btn-secondary pms-toolbar-btn">
                        <svg style="width: 14px; height: 14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                        Select Files
                    </button>

                    <template x-if="attachments.length > 0">
                        <button type="button" @click="clearAll()" class="btn btn-secondary pms-toolbar-btn" style="color: var(--color-danger); border-color: transparent;">
                            Clear All
                        </button>
                    </template>

                    <div style="margin-left: auto;" x-show="attachments.length > 0">
                        <span class="text-muted" style="font-size: 0.75rem;" x-text="attachments.length + ' file(s) attached'"></span>
                    </div>
                </div>

                {{-- Queued Files Cards List (Bounded to 240px max height to eliminate scroll hassle) --}}
                <template x-if="attachments.length > 0">
                    <div class="pms-files-scroll">
                        <template x-for="(att, idx) in attachments" :key="att.id">
                            <div class="pms-file-card">
                                <div class="pms-file-card-header">
                                    <div class="pms-file-info">
                                        <svg style="width: 16px; height: 16px; color: var(--color-accent); flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                            <polyline points="14 2 14 8 20 8"></polyline>
                                        </svg>
                                        <span class="pms-file-name" :title="att.name" x-text="att.name"></span>
                                        <span class="pms-file-size" x-text="att.formattedSize"></span>
                                    </div>
                                    <button type="button" @click="removeAttachment(idx)" class="pms-file-remove-btn" title="Remove attachment">
                                        ✕ Remove
                                    </button>
                                </div>
                                <div class="pms-file-card-fields">
                                    <select x-model="att.category" @change="syncInputs()" class="form-control pms-toolbar-item" style="margin-bottom: 0;">
                                        <template x-for="cat in categories" :key="cat.key">
                                            <option :value="cat.key" x-text="cat.label"></option>
                                        </template>
                                    </select>
                                    <input
                                        type="text"
                                        x-model="att.description"
                                        @input="syncInputs()"
                                        class="form-control pms-toolbar-item"
                                        placeholder="Optional description / note..."
                                        style="margin-bottom: 0;"
                                    >
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- ─── Dedicated Component Styles (Self-contained, bulletproof) ───── --}}
        <style>
            :root {
                --color-primary: #2C5F7C;
            }

            /* Quick-Jump Section Navigator (Sticky, Compact, No Scroll Hassle) */
            .pms-quick-nav {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                padding: 6px 10px !important;
                background: var(--color-surface) !important;
                border: 1px solid var(--color-border) !important;
                border-radius: var(--radius-md) !important;
                position: sticky !important;
                top: calc(var(--topbar-height, 64px) + 8px) !important;
                z-index: 15 !important;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04) !important;
                backdrop-filter: blur(8px) !important;
                margin-bottom: var(--space-1) !important;
                box-sizing: border-box !important;
            }
            .pms-quick-nav-label {
                font-size: 0.75rem !important;
                font-weight: 700 !important;
                color: var(--color-ink) !important;
                text-transform: uppercase !important;
                letter-spacing: 0.04em !important;
                flex-shrink: 0 !important;
            }
            .pms-quick-nav-track {
                display: flex !important;
                align-items: center !important;
                gap: 4px !important;
                overflow-x: auto !important;
                scrollbar-width: none !important;
                flex: 1 1 auto !important;
            }
            .pms-quick-nav-track::-webkit-scrollbar {
                display: none !important;
            }
            .pms-quick-nav-link {
                display: inline-flex !important;
                align-items: center !important;
                gap: 5px !important;
                padding: 4px 8px !important;
                font-size: 0.75rem !important;
                font-weight: 500 !important;
                color: var(--color-muted) !important;
                text-decoration: none !important;
                border-radius: var(--radius-sm) !important;
                white-space: nowrap !important;
                transition: all var(--transition-fast) !important;
                border: 1px solid transparent !important;
            }
            .pms-quick-nav-link:hover {
                color: var(--color-accent) !important;
                background: var(--color-surface-alt) !important;
                border-color: var(--color-border) !important;
            }
            .pms-nav-counter {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                background: var(--color-surface-alt) !important;
                color: var(--color-ink) !important;
                border: 1px solid var(--color-border) !important;
                font-size: 0.6875rem !important;
                font-family: var(--font-mono) !important;
                font-weight: 600 !important;
                padding: 0 5px !important;
                border-radius: var(--radius-pill) !important;
                min-width: 16px !important;
                height: 16px !important;
                line-height: 1 !important;
            }

            /* Preview Interactive Rows */
            .pms-preview-row {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                padding-bottom: var(--space-2) !important;
                border-bottom: 1px solid var(--color-border) !important;
                transition: opacity var(--transition-fast) !important;
                cursor: pointer !important;
            }
            .pms-preview-row:hover {
                opacity: 0.8 !important;
            }

            /* Staffing Benchmark Checklist */
            .pms-benchmark-box {
                background: var(--color-surface-alt) !important;
                border: 1px solid var(--color-border) !important;
                border-radius: var(--radius-md) !important;
                padding: 10px 12px !important;
                margin-bottom: var(--space-3) !important;
                box-sizing: border-box !important;
            }
            .pms-benchmark-top {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 8px !important;
                margin-bottom: 8px !important;
                flex-wrap: wrap !important;
            }
            .pms-benchmark-badge {
                display: inline-flex !important;
                align-items: center !important;
                gap: 4px !important;
                font-size: 0.6875rem !important;
                font-weight: 600 !important;
                padding: 2px 8px !important;
                border-radius: var(--radius-pill) !important;
                background: var(--color-surface) !important;
                border: 1px solid var(--color-border) !important;
                color: var(--color-muted) !important;
            }
            .pms-benchmark-badge.is-complete {
                background: rgba(46, 125, 83, 0.12) !important;
                color: var(--color-success) !important;
                border-color: rgba(46, 125, 83, 0.3) !important;
            }
            .pms-benchmark-chips {
                display: grid !important;
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 6px !important;
            }
            @media (max-width: 860px) {
                .pms-benchmark-chips {
                    grid-template-columns: repeat(2, 1fr) !important;
                }
            }
            @media (max-width: 480px) {
                .pms-benchmark-chips {
                    grid-template-columns: 1fr !important;
                }
            }
            .pms-benchmark-chip {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                padding: 6px 8px !important;
                border-radius: var(--radius-sm) !important;
                background: var(--color-surface) !important;
                border: 1px solid var(--color-border) !important;
                font-size: 0.75rem !important;
                cursor: pointer !important;
                transition: all var(--transition-fast) !important;
                gap: 6px !important;
                box-sizing: border-box !important;
                text-align: left !important;
            }
            .pms-benchmark-chip:hover {
                border-color: var(--color-accent) !important;
                transform: translateY(-1px) !important;
            }
            .pms-benchmark-chip.status-fulfilled {
                background: rgba(46, 125, 83, 0.08) !important;
                border-color: rgba(46, 125, 83, 0.3) !important;
                color: var(--color-success) !important;
            }
            .pms-benchmark-chip.status-fulfilled .pms-benchmark-indicator {
                color: var(--color-success) !important;
            }
            .pms-benchmark-chip.status-partial {
                background: rgba(44, 95, 124, 0.08) !important;
                border-color: rgba(44, 95, 124, 0.3) !important;
                color: var(--color-accent) !important;
            }
            .pms-benchmark-chip.status-partial .pms-benchmark-indicator {
                color: var(--color-accent) !important;
            }
            .pms-benchmark-chip.status-exceeded {
                background: rgba(201, 138, 44, 0.08) !important;
                border-color: rgba(201, 138, 44, 0.3) !important;
                color: #C98A2C !important;
            }
            .pms-benchmark-chip.status-exceeded .pms-benchmark-indicator {
                color: #C98A2C !important;
            }
            .pms-benchmark-chip.status-unmet {
                background: var(--color-surface) !important;
                border-color: var(--color-border) !important;
                color: var(--color-muted) !important;
            }
            .pms-benchmark-chip.is-filtered {
                outline: 2px solid var(--color-accent) !important;
                outline-offset: 1px !important;
            }
            .pms-benchmark-indicator {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 14px !important;
                height: 14px !important;
                flex-shrink: 0 !important;
            }
            .pms-benchmark-label {
                font-weight: 500 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                flex: 1 1 auto !important;
            }
            .pms-benchmark-ratio {
                font-size: 0.6875rem !important;
                font-weight: 600 !important;
                flex-shrink: 0 !important;
            }

            /* Scrollable Manpower Roster Box */
            .pms-roster-box {
                height: 260px !important;
                max-height: 260px !important;
                overflow-y: auto !important;
                overflow-x: hidden !important;
                border: 1px solid var(--color-border) !important;
                border-radius: var(--radius-md) !important;
                background: var(--color-surface) !important;
                scrollbar-width: thin !important;
                scrollbar-color: var(--color-border) transparent !important;
                box-sizing: border-box !important;
            }
            .pms-roster-box::-webkit-scrollbar {
                width: 6px !important;
            }
            .pms-roster-box::-webkit-scrollbar-thumb {
                background: var(--color-border) !important;
                border-radius: 4px !important;
            }

            /* Personnel Row & Checkbox Alignment */
            .pms-personnel-row {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                justify-content: flex-start !important;
                padding: 8px 12px !important;
                border-bottom: 1px solid var(--color-border) !important;
                cursor: pointer !important;
                user-select: none !important;
                gap: 12px !important;
                width: 100% !important;
                box-sizing: border-box !important;
                transition: background-color var(--transition-fast) !important;
            }
            .pms-personnel-row:last-child {
                border-bottom: none !important;
            }
            .pms-personnel-row:hover {
                background-color: var(--color-surface-alt) !important;
            }
            .pms-personnel-row.is-selected {
                background-color: rgba(44, 95, 124, 0.08) !important;
            }
            .pms-personnel-check {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                flex: 0 0 20px !important;
                width: 20px !important;
                height: 20px !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .pms-personnel-check input[type="checkbox"] {
                width: 16px !important;
                height: 16px !important;
                margin: 0 !important;
                padding: 0 !important;
                cursor: pointer !important;
                accent-color: var(--color-accent) !important;
                vertical-align: middle !important;
            }
            .pms-personnel-body {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                justify-content: space-between !important;
                flex: 1 1 auto !important;
                min-width: 0 !important;
                gap: 10px !important;
            }
            .pms-personnel-name-group {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                min-width: 0 !important;
                overflow: hidden !important;
            }
            .pms-personnel-name {
                font-size: 0.8125rem !important;
                font-weight: 600 !important;
                color: var(--color-ink) !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }
            .pms-personnel-id {
                font-size: 0.75rem !important;
                color: var(--color-muted) !important;
                font-family: var(--font-mono) !important;
                flex-shrink: 0 !important;
            }
            .pms-personnel-meta {
                display: flex !important;
                align-items: center !important;
                gap: 6px !important;
                flex-shrink: 0 !important;
            }

            /* Uniform 34px Toolbar Controls */
            .pms-toolbar {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                margin-bottom: var(--space-3) !important;
                flex-wrap: wrap !important;
            }
            .pms-toolbar-item,
            .form-group-premium input.pms-toolbar-item,
            .form-group-premium select.pms-toolbar-item {
                height: 34px !important;
                min-height: 34px !important;
                max-height: 34px !important;
                padding: 0 10px !important;
                font-size: 0.8125rem !important;
                line-height: 32px !important;
                box-sizing: border-box !important;
                border: 1px solid var(--color-border) !important;
                border-radius: var(--radius-md) !important;
                background: var(--color-surface) !important;
                color: var(--color-ink) !important;
            }
            .pms-toolbar-select {
                height: 34px !important;
                min-height: 34px !important;
                max-height: 34px !important;
                padding: 0 10px !important;
                font-size: 0.8125rem !important;
                line-height: 32px !important;
                border: 1px solid var(--color-border) !important;
                border-radius: var(--radius-md) !important;
                background: var(--color-surface) !important;
                color: var(--color-ink) !important;
                box-sizing: border-box !important;
            }
            .pms-toolbar-btn {
                height: 34px !important;
                min-height: 34px !important;
                max-height: 34px !important;
                padding: 0 12px !important;
                font-size: 0.8125rem !important;
                font-weight: 500 !important;
                border-radius: var(--radius-md) !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 6px !important;
                white-space: nowrap !important;
                box-sizing: border-box !important;
                cursor: pointer !important;
                border: 1px solid var(--color-border) !important;
                background: var(--color-surface) !important;
                color: var(--color-ink) !important;
                transition: background-color var(--transition-fast), border-color var(--transition-fast) !important;
            }
            .pms-toolbar-btn:hover {
                background: var(--color-surface-alt) !important;
                border-color: var(--color-muted) !important;
            }

            /* File Upload Component & Cards */
            .pms-dropzone {
                border: 2px dashed var(--color-border) !important;
                border-radius: var(--radius-md) !important;
                padding: 12px var(--space-4) !important;
                text-align: center !important;
                background: var(--color-surface) !important;
                transition: border-color var(--transition-fast), background-color var(--transition-fast) !important;
                cursor: pointer !important;
                margin-bottom: var(--space-3) !important;
            }
            .pms-dropzone:hover,
            .pms-dropzone.is-dragging {
                border-color: var(--color-accent) !important;
                background-color: var(--color-surface-alt) !important;
            }
            .pms-file-card {
                background: var(--color-surface) !important;
                border: 1px solid var(--color-border) !important;
                border-radius: var(--radius-md) !important;
                padding: 8px 12px !important;
                margin-bottom: 6px !important;
                box-sizing: border-box !important;
                width: 100% !important;
                min-width: 0 !important;
                overflow: hidden !important;
            }
            .pms-file-card-header {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 8px !important;
                margin-bottom: 6px !important;
                width: 100% !important;
                min-width: 0 !important;
            }
            .pms-file-info {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                min-width: 0 !important;
                flex: 1 1 auto !important;
                overflow: hidden !important;
            }
            .pms-file-name {
                font-size: 0.8125rem !important;
                font-weight: 600 !important;
                color: var(--color-ink) !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                flex: 1 1 auto !important;
                min-width: 0 !important;
            }
            .pms-file-size {
                font-size: 0.75rem !important;
                color: var(--color-muted) !important;
                font-family: var(--font-mono) !important;
                flex-shrink: 0 !important;
            }
            .pms-file-remove-btn {
                height: 26px !important;
                padding: 0 8px !important;
                font-size: 0.75rem !important;
                color: var(--color-danger) !important;
                border: 1px solid transparent !important;
                background: transparent !important;
                border-radius: var(--radius-sm) !important;
                cursor: pointer !important;
                flex-shrink: 0 !important;
                white-space: nowrap !important;
            }
            .pms-file-remove-btn:hover {
                background: rgba(192, 57, 43, 0.08) !important;
            }
            .pms-file-card-fields {
                display: grid !important;
                grid-template-columns: 210px 1fr !important;
                gap: 8px !important;
                align-items: center !important;
                width: 100% !important;
                min-width: 0 !important;
                box-sizing: border-box !important;
            }
            @media (max-width: 680px) {
                .pms-file-card-fields {
                    grid-template-columns: 1fr !important;
                }
            }

            /* Scrollable Containers (Eliminates Long Page Scroll) */
            .pms-expenses-scroll {
                max-height: 275px !important;
                overflow-y: auto !important;
                overflow-x: hidden !important;
                padding-right: 2px !important;
                scrollbar-width: thin !important;
                scrollbar-color: var(--color-border) transparent !important;
            }
            .pms-expenses-scroll::-webkit-scrollbar {
                width: 6px !important;
            }
            .pms-expenses-scroll::-webkit-scrollbar-thumb {
                background: var(--color-border) !important;
                border-radius: 4px !important;
            }

            .pms-files-scroll {
                max-height: 240px !important;
                overflow-y: auto !important;
                overflow-x: hidden !important;
                padding-right: 2px !important;
                scrollbar-width: thin !important;
                scrollbar-color: var(--color-border) transparent !important;
            }
            .pms-files-scroll::-webkit-scrollbar {
                width: 6px !important;
            }
            .pms-files-scroll::-webkit-scrollbar-thumb {
                background: var(--color-border) !important;
                border-radius: 4px !important;
            }

            /* Expenses Item Card */
            .pms-expense-card {
                background: var(--color-surface) !important;
                border: 1px solid var(--color-border) !important;
                border-radius: var(--radius-md) !important;
                padding: 12px !important;
                margin-bottom: 8px !important;
                box-sizing: border-box !important;
                width: 100% !important;
                min-width: 0 !important;
            }
            .pms-expense-card-header {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                margin-bottom: 10px !important;
                padding-bottom: 6px !important;
                border-bottom: 1px solid var(--color-surface-alt) !important;
                width: 100% !important;
            }
            .pms-expense-grid-catalog {
                display: grid !important;
                grid-template-columns: minmax(0, 2fr) 90px 120px !important;
                gap: 10px !important;
                align-items: end !important;
                width: 100% !important;
            }
            .pms-expense-grid-manual {
                display: grid !important;
                grid-template-columns: 130px minmax(0, 1.8fr) 80px 120px !important;
                gap: 10px !important;
                align-items: end !important;
                width: 100% !important;
            }
            @media (max-width: 768px) {
                .pms-expense-grid-catalog,
                .pms-expense-grid-manual {
                    grid-template-columns: 1fr !important;
                }
            }

            /* Dark Mode Theme Support */
            [data-theme="dark"] .pms-quick-nav,
            [data-theme="dark"] .pms-benchmark-box {
                background: #0E1522 !important;
                border-color: #243042 !important;
            }
            [data-theme="dark"] .pms-benchmark-chip {
                background: #111A28 !important;
                border-color: #243042 !important;
            }
            [data-theme="dark"] .pms-benchmark-chip.status-fulfilled {
                background: rgba(46, 125, 83, 0.2) !important;
                border-color: rgba(74, 222, 128, 0.4) !important;
                color: #4ADE80 !important;
            }
            [data-theme="dark"] .pms-benchmark-chip.status-partial {
                background: rgba(44, 95, 124, 0.25) !important;
                border-color: rgba(56, 189, 248, 0.4) !important;
                color: #38BDF8 !important;
            }
            [data-theme="dark"] .pms-benchmark-chip.status-exceeded {
                background: rgba(201, 138, 44, 0.2) !important;
                border-color: rgba(251, 191, 36, 0.4) !important;
                color: #FBBF24 !important;
            }
            [data-theme="dark"] .pms-benchmark-chip.status-unmet {
                background: #111A28 !important;
                border-color: #243042 !important;
                color: #94A3B8 !important;
            }
            [data-theme="dark"] .pms-quick-nav-link:hover {
                background: #1A263A !important;
            }
            [data-theme="dark"] .pms-nav-counter {
                background: #1A263A !important;
                border-color: #243042 !important;
                color: #F1F5F9 !important;
            }
            [data-theme="dark"] .pms-roster-box,
            [data-theme="dark"] .pms-file-card,
            [data-theme="dark"] .pms-expense-card {
                background: #111A28 !important;
                border-color: #243042 !important;
            }
            [data-theme="dark"] .pms-personnel-row {
                border-bottom-color: #243042 !important;
            }
            [data-theme="dark"] .pms-personnel-row:hover {
                background-color: #1A263A !important;
            }
            [data-theme="dark"] .pms-personnel-row.is-selected {
                background-color: rgba(44, 95, 124, 0.25) !important;
            }
            [data-theme="dark"] .pms-dropzone {
                background: #0E1522 !important;
                border-color: #243042 !important;
            }
            [data-theme="dark"] .pms-dropzone:hover,
            [data-theme="dark"] .pms-dropzone.is-dragging {
                background: #1A263A !important;
                border-color: var(--color-accent) !important;
            }
            [data-theme="dark"] .pms-toolbar-select {
                background: #0E1522 !important;
                border-color: #243042 !important;
                color: #F1F5F9 !important;
            }
        </style>
    @endif

</div>

{{-- ─── RIGHT COLUMN: Financials, Live Intelligence & Action Panel ──────── --}}
<div class="project-summary-sticky" style="min-width: 0;">

    {{-- Card 1: Financial & Billing Setup --}}
    <div class="card card-glass" id="section-financials">
        <div class="card-header" style="font-weight: 700; font-size: 0.9375rem; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: var(--space-2);">
                <svg style="width: 18px; height: 18px; color: var(--color-success);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/>
                    <path d="M12 18V6"/>
                </svg>
                Financials & Billing
            </div>
            @php
                $initialPaymentStatus = old('payment_status', $project->payment_status ?? 'Partial');
                $initialPaymentTerms = old('payment_terms', $project->payment_terms ?? '');
                $previewPaymentText = ($initialPaymentStatus === 'Partial' && $initialPaymentTerms)
                    ? "Partial ({$initialPaymentTerms})"
                    : $initialPaymentStatus;
            @endphp
            <span id="preview-payment-pill" class="status-pill status-pill--{{ strtolower($initialPaymentStatus) }}">
                {{ $previewPaymentText }}
            </span>
        </div>
        <div class="card-body">
            <div class="form-group form-group-premium" style="margin-bottom: var(--space-4);">
                <label for="contract_price" class="form-label">Contract Price (PHP ₱) <span class="text-danger">*</span></label>
                @php
                    $rawContractPrice = old('contract_price', isset($project) && $project->contract_price !== null ? $project->contract_price : '');
                    $formattedContractPrice = $rawContractPrice !== '' && is_numeric(str_replace(',', '', (string) $rawContractPrice))
                        ? number_format((float) str_replace(',', '', (string) $rawContractPrice), 2, '.', ',')
                        : $rawContractPrice;
                @endphp
                <div style="position: relative;">
                    <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 600; color: var(--color-muted); font-family: var(--font-mono); pointer-events: none; z-index: 2;">₱&nbsp;</span>
                    <input
                        id="contract_price"
                        name="contract_price"
                        type="text"
                        inputmode="decimal"
                        data-amount-input
                        class="form-control @error('contract_price') is-invalid @enderror font-mono"
                        style="padding-left: 38px !important;"
                        value="{{ $formattedContractPrice }}"
                        required
                        placeholder="100,000.00"
                    >
                </div>
                <p class="form-hint">Total agreed budget projected onto Project Costing (minimum ₱100,000.00).</p>
                @error('contract_price')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
            </div>

            <div class="form-group form-group-premium" style="margin-bottom: var(--space-4);">
                <label for="payment_status" class="form-label">Payment Status <span class="text-danger">*</span></label>
                <select id="payment_status" name="payment_status" class="form-control @error('payment_status') is-invalid @enderror" required>
                    <option value="" disabled {{ old('payment_status', $project->payment_status ?? '') === '' ? 'selected' : '' }}>Select Payment Status</option>
                    <option value="Paid" {{ old('payment_status', $project->payment_status ?? '') === 'Paid' ? 'selected' : '' }}>Paid</option>
                    <option value="Partial" {{ old('payment_status', $project->payment_status ?? 'Partial') === 'Partial' ? 'selected' : '' }}>Partial</option>
                </select>
                <p class="form-hint">Billing milestone collection tracking.</p>
                @error('payment_status')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
            </div>

            {{-- Payment Terms: Required when Payment Status is Partial --}}
            @php
                $isPartial = old('payment_status', $project->payment_status ?? 'Partial') === 'Partial';
                $selectedPaymentTerms = old('payment_terms', $project->payment_terms ?? '');
            @endphp
            <div class="form-group form-group-premium" id="payment-terms-group" style="margin-bottom: var(--space-4); {{ $isPartial ? '' : 'display: none;' }}">
                <label for="payment_terms" class="form-label">
                    Payment Terms <span class="text-danger">*</span>
                </label>
                <select
                    id="payment_terms"
                    name="payment_terms"
                    class="form-control @error('payment_terms') is-invalid @enderror"
                    {{ $isPartial ? 'required' : '' }}
                >
                    <option value="" disabled {{ empty($selectedPaymentTerms) ? 'selected' : '' }}>Select Payment Terms Percentage</option>
                    @foreach(['10%', '20%', '30%', '40%', '50%'] as $pct)
                        <option value="{{ $pct }}" {{ $selectedPaymentTerms === $pct ? 'selected' : '' }}>{{ $pct }}</option>
                    @endforeach
                </select>
                <p class="form-hint">Required partial payment percentage breakdown (10%, 20%, 30%, 40%, 50%).</p>
                @error('payment_terms')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
            </div>

            {{-- Invoice Attachment Upload (Multiple & Removable) --}}
            @php
                $hasExistingInvoice = isset($project) && $project->invoices()->exists();
                $existingInvoices = $hasExistingInvoice ? $project->invoices()->get() : collect();
            @endphp
            <div 
                class="form-group form-group-premium" 
                id="invoice-upload-group" 
                style="margin-bottom: 0;"
                x-data="{
                    invoiceFiles: [],
                    isDragging: false,
                    fileError: '',
                    formatSize(bytes) {
                        if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
                        if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
                        return bytes + ' B';
                    },
                    getFileIcon(name) {
                        if (!name) return '📄';
                        const ext = name.split('.').pop().toLowerCase();
                        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return '🖼️';
                        if (['pdf'].includes(ext)) return '📕';
                        if (['doc', 'docx'].includes(ext)) return '📘';
                        return '📄';
                    },
                    handleFileSelect(e) {
                        const files = e.target.files;
                        if (!files || files.length === 0) return;
                        this.addFiles(Array.from(files));
                        e.target.value = '';
                    },
                    handleDrop(e) {
                        this.isDragging = false;
                        const files = e.dataTransfer ? e.dataTransfer.files : null;
                        if (!files || files.length === 0) return;
                        this.addFiles(Array.from(files));
                    },
                    addFiles(files) {
                        this.fileError = '';
                        const maxSize = 20 * 1024 * 1024;
                        for (let i = 0; i < files.length; i++) {
                            const file = files[i];
                            if (file.size > maxSize) {
                                this.fileError = `File '${file.name}' exceeds the 20MB limit.`;
                                continue;
                            }
                            if (!this.invoiceFiles.some(f => f.name === file.name && f.size === file.size)) {
                                this.invoiceFiles.push({
                                    id: Date.now() + '_' + Math.random().toString(36).substr(2, 9),
                                    file: file,
                                    name: file.name,
                                    size: file.size,
                                    formattedSize: this.formatSize(file.size)
                                });
                            }
                        }
                        this.syncInputs();
                    },
                    removeFile(index) {
                        this.invoiceFiles.splice(index, 1);
                        this.syncInputs();
                    },
                    syncInputs() {
                        const container = document.getElementById('invoice_files_container');
                        if (!container) return;
                        container.innerHTML = '';
                        this.invoiceFiles.forEach((item, idx) => {
                            const input = document.createElement('input');
                            input.type = 'file';
                            input.name = 'invoice_files[]';
                            input.style.display = 'none';
                            try {
                                const dt = new DataTransfer();
                                dt.items.add(item.file);
                                input.files = dt.files;
                            } catch (err) {
                                console.error('DataTransfer error', err);
                            }
                            container.appendChild(input);
                        });

                        const primaryContainer = document.getElementById('primary_invoice_file_slot');
                        if (primaryContainer) {
                            primaryContainer.innerHTML = '';
                            if (this.invoiceFiles.length > 0) {
                                const pInput = document.createElement('input');
                                pInput.type = 'file';
                                pInput.name = 'invoice_file';
                                pInput.style.display = 'none';
                                try {
                                    const dt = new DataTransfer();
                                    dt.items.add(this.invoiceFiles[0].file);
                                    pInput.files = dt.files;
                                } catch (e) {}
                                primaryContainer.appendChild(pInput);
                            }
                        }
                    }
                }"
            >
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
                    <label class="form-label" style="margin-bottom: 0; display: flex; align-items: center; gap: 4px;">
                        <span>Invoice Attachment</span>
                        @if (!$hasExistingInvoice)
                            <span class="text-danger" title="Required">*</span>
                        @endif
                    </label>
                    <span class="badge" style="background: var(--color-surface-alt); font-size: 0.6875rem;">PDF, DOCX, Images</span>
                </div>

                {{-- Hidden dynamic inputs container --}}
                <div id="invoice_files_container" style="display: none;"></div>
                <div id="primary_invoice_file_slot" style="display: none;"></div>

                {{-- Hidden file input triggered by buttons/dropzone --}}
                <input
                    type="file"
                    x-ref="invoicePicker"
                    @change="handleFileSelect($event)"
                    multiple
                    accept=".pdf,.doc,.docx,image/*"
                    style="display: none;"
                >

                {{-- If existing invoices exist on edit --}}
                @if ($hasExistingInvoice)
                    <div style="margin-bottom: var(--space-2); padding: var(--space-2) var(--space-3); background: var(--color-surface-alt); border-radius: var(--radius-sm); border: 1px solid var(--color-border); font-size: 0.75rem;">
                        <span class="text-muted" style="display: block; margin-bottom: 4px; font-weight: 600;">Current Invoices on File:</span>
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                            @foreach ($existingInvoices as $exInv)
                                <a href="{{ route('projects.files.download', [$project, $exInv]) }}" target="_blank" class="table-link font-bold" style="display: inline-flex; align-items: center; gap: 4px;">
                                    📄 {{ $exInv->file_name }} <span class="text-muted font-normal">({{ $exInv->formatted_size }})</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Attached Files List --}}
                <template x-if="invoiceFiles.length > 0">
                    <div style="display: flex; flex-direction: column; gap: var(--space-2); margin-bottom: var(--space-2);">
                        <template x-for="(fileItem, index) in invoiceFiles" :key="fileItem.id">
                            <div 
                                style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); padding: var(--space-2) var(--space-3); background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.8125rem;"
                            >
                                <div style="display: flex; align-items: center; gap: var(--space-2); min-width: 0; flex: 1;">
                                    <span x-text="getFileIcon(fileItem.name)" style="font-size: 1.1rem; flex-shrink: 0;"></span>
                                    <div style="min-width: 0; flex: 1;">
                                        <div style="font-weight: 600; color: var(--color-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="fileItem.name"></div>
                                        <div class="text-muted" style="font-size: 0.6875rem;" x-text="fileItem.formattedSize"></div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    @click="removeFile(index)"
                                    style="padding: 2px 7px; color: #ef4444; border-color: rgba(239, 68, 68, 0.3); font-weight: 700; line-height: 1; border-radius: 4px;"
                                    title="Remove this attached file"
                                >
                                    ✕
                                </button>
                            </div>
                        </template>

                        <div style="display: flex; justify-content: flex-end; margin-top: 2px;">
                            <button
                                type="button"
                                class="btn btn-secondary btn-sm"
                                @click="$refs.invoicePicker.click()"
                                style="font-size: 0.75rem; padding: 4px 10px;"
                            >
                                + Attach Another Invoice
                            </button>
                        </div>
                    </div>
                </template>

                {{-- Empty dropzone / upload trigger --}}
                <template x-if="invoiceFiles.length === 0">
                    <div 
                        class="pms-dropzone"
                        :class="{ 'is-dragging': isDragging }"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleDrop($event)"
                        @click="$refs.invoicePicker.click()"
                        style="padding: var(--space-3); text-align: center; border: 2px dashed var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); cursor: pointer; transition: all 0.15s ease;"
                    >
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                            <svg style="width: 24px; height: 24px; color: var(--color-accent); opacity: 0.8;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                            <span style="font-size: 0.8125rem; font-weight: 600; color: var(--color-ink);">
                                Click to select or drop invoice file(s)
                            </span>
                            <span class="text-muted" style="font-size: 0.6875rem;">
                                PDF, DOCX, or Images up to 20MB. Multiple files supported.
                            </span>
                        </div>
                    </div>
                </template>

                <template x-if="fileError">
                    <div class="form-error" style="color: #ef4444; font-size: 0.75rem; margin-top: 4px;" x-text="fileError"></div>
                </template>

                <p class="form-hint" id="invoice-hint" style="margin-top: 6px;">
                    @if($hasExistingInvoice)
                        (Optional) Upload new files to add additional or updated invoices.
                    @else
                        Required official invoice, billing receipt, or payment breakdown (PDF, DOCX, Images up to 20MB).
                    @endif
                </p>
                @error('invoice_file')<div class="invalid-feedback" style="display: block;" role="alert">{{ $message }}</div>@enderror
                @error('invoice_files')<div class="invalid-feedback" style="display: block;" role="alert">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    {{-- Card 2: Live Overview Preview --}}
    <div class="card card-glass" style="border-left: 4px solid var(--color-accent);">
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
                <div class="pms-preview-row" onclick="document.getElementById('section-specs')?.scrollIntoView({ behavior: 'smooth', block: 'start' });" title="Jump to Elevator Specs">
                    <span class="text-muted">Elevator Specs</span>
                    <span class="font-mono font-bold" id="preview-specs">{{ (isset($project) && $project->no_of_stops) ? $project->no_of_stops . ' Stops · ' . ($project->rated_capacity ?? '') : 'Specs pending' }}</span>
                </div>
                <div class="pms-preview-row">
                    <span class="text-muted">Est. Duration</span>
                    <span class="font-mono font-bold" id="preview-duration" style="color: var(--color-accent);">8 Weeks</span>
                </div>
                <div class="pms-preview-row" @click="warrantyInfoModalOpen = true" style="cursor: pointer;" title="Click to view Warranty & Maintenance inclusion terms">
                    <span class="text-muted" style="display: flex; align-items: center; gap: 4px;">
                        Warranty Limit
                        <svg style="width: 12px; height: 12px; opacity: 0.6;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    </span>
                    <span class="font-mono font-bold text-success" id="preview-warranty-end">{{ \Carbon\Carbon::parse($calculatedWarrantyEnd)->format('M j, Y') }}</span>
                </div>
                <div class="pms-preview-row" onclick="document.getElementById('section-location')?.scrollIntoView({ behavior: 'smooth', block: 'start' });" title="Jump to Location">
                    <span class="text-muted">Location</span>
                    <span id="preview-location" style="text-align: right; max-width: 60%; font-weight: 500;">
                        {{ isset($project) && $project->full_address ? $project->full_address : 'Address pending' }}
                    </span>
                </div>
                @if (!$isEditing)
                    <div class="pms-preview-row" onclick="document.getElementById('project-manpower-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });" title="Jump to Manpower section">
                        <span class="text-muted" style="display: flex; align-items: center; gap: 4px;">
                            Manpower
                            <svg style="width: 11px; height: 11px; opacity: 0.6;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17l9.2-9.2M17 17V8H8"/></svg>
                        </span>
                        <span class="font-bold" id="preview-manpower" style="color: var(--color-danger);">0 Personnel</span>
                    </div>
                    <div class="pms-preview-row" onclick="document.getElementById('project-expenses-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });" title="Jump to Materials & Expenditures">
                        <span class="text-muted" style="display: flex; align-items: center; gap: 4px;">
                            Initial Spend
                            <svg style="width: 11px; height: 11px; opacity: 0.6;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17l9.2-9.2M17 17V8H8"/></svg>
                        </span>
                        <span class="font-mono font-bold text-success" id="preview-expenses">₱0.00</span>
                    </div>
                    <div class="pms-preview-row" style="border-bottom: none !important;" onclick="document.getElementById('project-attachments-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });" title="Jump to Attachments">
                        <span class="text-muted" style="display: flex; align-items: center; gap: 4px;">
                            Attachments
                            <svg style="width: 11px; height: 11px; opacity: 0.6;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17l9.2-9.2M17 17V8H8"/></svg>
                        </span>
                        <span class="font-bold" id="preview-attachments" style="color: var(--color-accent);">0 Files</span>
                    </div>
                @endif
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

{{-- ─── Warranty & Maintenance Information Modal ─────────────────────────────── --}}
@include('projects.partials.warranty-modal')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const startDateInput = document.getElementById('start_date');
        const targetCompletionInput = document.getElementById('target_completion_date');
        const warrantyEndDateInput = document.getElementById('warranty_end_date');
        const warrantyDateLabel = document.getElementById('warranty-date-label');
        const previewWarrantyEnd = document.getElementById('preview-warranty-end');
        const previewDuration = document.getElementById('preview-duration');
        const paymentSelect = document.getElementById('payment_status');
        const paymentTermsGroup = document.getElementById('payment-terms-group');
        const paymentTermsSelect = document.getElementById('payment_terms');
        const previewPaymentPill = document.getElementById('preview-payment-pill');
        const addressInput = document.getElementById('address');
        const regionInput = document.getElementById('region');
        const provinceInput = document.getElementById('province');
        const cityInput = document.getElementById('city');
        const barangayInput = document.getElementById('barangay');
        const postalCodeInput = document.getElementById('postal_code');
        const previewLocation = document.getElementById('preview-location');

        function getMinTargetCompletionDate(startDateStr) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            let baseDate = new Date(today);
            if (startDateStr) {
                const [sY, sM, sD] = startDateStr.split('-').map(Number);
                if (sY && sM && sD) {
                    const start = new Date(sY, sM - 1, sD);
                    start.setHours(0, 0, 0, 0);
                    if (start > baseDate) {
                        baseDate = start;
                    }
                }
            }

            const minDate = new Date(baseDate);
            minDate.setMonth(minDate.getMonth() + 1);

            const y = minDate.getFullYear();
            const m = String(minDate.getMonth() + 1).padStart(2, '0');
            const d = String(minDate.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        function updateDatesAndWarranty() {
            if (!startDateInput || !startDateInput.value) return;

            const selectedDateVal = startDateInput.value;
            
            // Sync target completion date min (at least 1 month from now or start date)
            if (targetCompletionInput) {
                const minTargetVal = getMinTargetCompletionDate(selectedDateVal);
                targetCompletionInput.min = minTargetVal;
                if (targetCompletionInput.value && targetCompletionInput.value < minTargetVal) {
                    targetCompletionInput.value = minTargetVal;
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
            const termsVal = paymentTermsSelect ? paymentTermsSelect.value : '';

            if (val === 'Partial') {
                if (paymentTermsGroup) paymentTermsGroup.style.display = '';
                if (paymentTermsSelect) paymentTermsSelect.required = true;
                previewPaymentPill.textContent = termsVal ? `Partial (${termsVal})` : 'Partial';
            } else {
                if (paymentTermsGroup) paymentTermsGroup.style.display = 'none';
                if (paymentTermsSelect) {
                    paymentTermsSelect.required = false;
                    paymentTermsSelect.value = '';
                }
                previewPaymentPill.textContent = val;
            }

            previewPaymentPill.className = `status-pill status-pill--${val.toLowerCase()}`;

            // Invoice attachment is required on project creation regardless of payment status
        }

        function updateLocation() {
            if (!previewLocation) return;
            const parts = [
                addressInput ? addressInput.value.trim() : '',
                barangayInput ? barangayInput.value.trim() : '',
                cityInput ? cityInput.value.trim() : '',
                provinceInput ? provinceInput.value.trim() : '',
                regionInput ? regionInput.value.trim() : ''
            ].filter(Boolean);
            const uniqueParts = [...new Set(parts)];
            let locText = uniqueParts.length > 0 ? uniqueParts.join(', ') : 'Address pending';
            if (postalCodeInput && postalCodeInput.value.trim() && locText !== 'Address pending') {
                locText += ` ${postalCodeInput.value.trim()}`;
            }
            previewLocation.textContent = locText;
        }

        const stopsInput = document.getElementById('no_of_stops');
        const capacityInput = document.getElementById('rated_capacity');
        const previewSpecs = document.getElementById('preview-specs');

        function updateElevatorSpecs() {
            if (!previewSpecs) return;
            const stops = stopsInput ? stopsInput.value.trim() : '';
            const cap = capacityInput ? capacityInput.value.trim() : '';
            if (stops && cap) {
                previewSpecs.textContent = `${stops} Stops · ${cap}`;
            } else if (stops) {
                previewSpecs.textContent = `${stops} Stops`;
            } else if (cap) {
                previewSpecs.textContent = cap;
            } else {
                previewSpecs.textContent = 'Specs pending';
            }
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

        if (paymentTermsSelect) {
            paymentTermsSelect.addEventListener('change', updatePaymentPill);
        }

        [addressInput, cityInput, provinceInput, regionInput, barangayInput, postalCodeInput].forEach(function (input) {
            if (input) {
                input.addEventListener('input', updateLocation);
                input.addEventListener('change', updateLocation);
            }
        });

        if (typeof initPhilippineAddress === 'function') {
            initPhilippineAddress({
                regionSelect: '#region',
                provinceSelect: '#province',
                citySelect: '#city',
                barangaySelect: '#barangay',
                postalCodeInput: '#postal_code',
                addressInput: '#address',
                initialRegion: @js(old('region', $project->region ?? '')),
                initialProvince: @js(old('province', $project->province ?? '')),
                initialCity: @js(old('city', $project->city ?? '')),
                initialBarangay: @js(old('barangay', $project->barangay ?? '')),
                onChange: updateLocation
            });
        }

        [stopsInput, capacityInput].forEach(function (input) {
            if (input) {
                input.addEventListener('input', updateElevatorSpecs);
                input.addEventListener('change', updateElevatorSpecs);
            }
        });

        // Initialize live values
        updateDatesAndWarranty();
        calculateDuration();
        updatePaymentPill();
        updateLocation();
        updateElevatorSpecs();

        // ─── Real-Time Similarity & Duplicate Checker ──────────────────────
        const nameInput = document.getElementById('name');
        const nameFeedback = document.getElementById('name-similarity-feedback');
        const excludeProjectId = @js($project->id ?? null);
        let similarityTimeout = null;
        let lastNotifiedKey = '';

        function checkProjectSimilarity() {
            if (!nameInput) return;
            const nameVal = (nameInput.value || '').trim();

            if (nameVal.length < 2) {
                if (nameFeedback) { nameFeedback.style.display = 'none'; nameFeedback.innerHTML = ''; }
                return;
            }

            const queryParams = new URLSearchParams({
                name: nameVal
            });
            if (excludeProjectId) {
                queryParams.append('exclude_id', excludeProjectId);
            }

            fetch(`{{ route('projects.check-similarity') }}?${queryParams.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (!data || !data.matches || data.matches.length === 0) {
                    if (nameFeedback) { nameFeedback.style.display = 'none'; nameFeedback.innerHTML = ''; }
                    return;
                }

                const matches = data.matches;
                const exactDuplicate = matches.find(m => m.exact_name);
                const similarMatches = matches.filter(m => !m.exact_name);

                if (exactDuplicate) {
                    const noticeText = `A project with this exact name already exists: <strong>${exactDuplicate.name}</strong> (${exactDuplicate.project_code}, Client: ${exactDuplicate.client_name})`;
                    if (nameFeedback) {
                        nameFeedback.style.display = 'block';
                        nameFeedback.style.background = '#fef2f2';
                        nameFeedback.style.borderColor = '#f87171';
                        nameFeedback.style.color = '#991b1b';
                        nameFeedback.innerHTML = `&#9888; ${noticeText}`;
                    }

                    const notifyKey = `exact_${exactDuplicate.id}_${nameVal}`;
                    if (lastNotifiedKey !== notifyKey) {
                        lastNotifiedKey = notifyKey;
                        window.dispatchEvent(new CustomEvent('show-toast', {
                            detail: {
                                type: 'error',
                                title: 'Duplicate Project Name',
                                message: `A project with the name "${exactDuplicate.name}" (${exactDuplicate.project_code}) already exists.`,
                                duration: 6000
                            }
                        }));
                    }
                } else if (similarMatches.length > 0) {
                    const first = similarMatches[0];
                    const count = similarMatches.length;
                    const summary = count === 1 
                        ? `Similar project name found: <strong>${first.name}</strong> (${first.project_code})`
                        : `Found ${count} projects with similar names (e.g. <strong>${first.name}</strong> - ${first.project_code})`;

                    if (nameFeedback) {
                        nameFeedback.style.display = 'block';
                        nameFeedback.style.background = '#fffbeb';
                        nameFeedback.style.borderColor = '#fcd34d';
                        nameFeedback.style.color = '#92400e';
                        nameFeedback.innerHTML = `&#8505; ${summary}`;
                    }

                    const notifyKey = `similar_${first.id}_${nameVal}`;
                    if (lastNotifiedKey !== notifyKey && nameVal.length >= 4) {
                        lastNotifiedKey = notifyKey;
                        window.dispatchEvent(new CustomEvent('show-toast', {
                            detail: {
                                type: 'warning',
                                title: 'Similar Project Name Found',
                                message: `Existing project "${first.name}" (${first.project_code}) has a very similar name.`,
                                duration: 5000
                            }
                        }));
                    }
                }
            })
            .catch(() => {
                // Silent catch on network / abort
            });
        }

        if (nameInput) {
            nameInput.addEventListener('input', function () {
                clearTimeout(similarityTimeout);
                similarityTimeout = setTimeout(checkProjectSimilarity, 400);
            });
            nameInput.addEventListener('blur', function () {
                clearTimeout(similarityTimeout);
                checkProjectSimilarity();
            });
        }

        const projectForm = document.querySelector('form.project-form-layout');
        if (projectForm) {
            projectForm.addEventListener('submit', function (e) {
                const isCreateMode = !@js($isEditing);
                if (isCreateMode) {
                    const checkedPersonnel = projectForm.querySelectorAll('input[name="personnel_ids[]"]:checked');
                    if (checkedPersonnel.length === 0) {
                        e.preventDefault();
                        window.dispatchEvent(new CustomEvent('show-toast', {
                            detail: {
                                type: 'error',
                                title: 'Manpower Required',
                                message: 'Please select at least one manpower personnel from the list to assign to this project.',
                                duration: 5000
                            }
                        }));
                        const manpowerCard = document.getElementById('project-manpower-section');
                        if (manpowerCard) {
                            manpowerCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        return false;
                    }
                }
            });
        }
    });
</script>
