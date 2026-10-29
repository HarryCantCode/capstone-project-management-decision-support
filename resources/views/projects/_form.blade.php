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

    {{-- Card 2: Elevator Technical Specifications --}}
    <div class="card card-glass">
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

    @if ($isEditing)
        {{-- Card: Project Status Configuration --}}
        <div class="card card-glass">
            <div class="card-header" style="font-weight: 700; font-size: 0.9375rem; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <svg style="width: 18px; height: 18px; color: var(--color-primary);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                    <span class="text-muted">Elevator Specs</span>
                    <span class="font-mono font-bold" id="preview-specs">{{ (isset($project) && $project->no_of_stops) ? $project->no_of_stops . ' Stops · ' . ($project->rated_capacity ?? '') : 'Specs pending' }}</span>
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

        [addressInput, barangayInput, cityInput].forEach(function (input) {
            if (input) {
                input.addEventListener('input', updateLocation);
                input.addEventListener('change', updateLocation);
            }
        });

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
    });
</script>
