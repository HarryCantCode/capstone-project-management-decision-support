{{-- Shared Configure Personnel Modal --}}
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

    <div
        class="reassign-modal-content card"
        style="max-width: 640px; width: 100%; max-height: 90vh; display: flex; flex-direction: column;"
        x-show="editModalOpen"
        x-transition:enter="modal-enter"
        x-transition:leave="modal-leave"
    >
        {{-- Modal Header --}}
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span id="configure-modal-title" style="font-weight: 600;">Configure Personnel</span>
                <span class="badge badge-accent font-mono" x-text="editForm.employee_id"></span>
            </div>
            <button
                type="button"
                class="flash-close"
                @click="editModalOpen = false"
                aria-label="Close configure modal"
                style="background: transparent; border: none; font-size: 1.25rem; cursor: pointer; color: var(--color-muted);"
            >
                &times;
            </button>
        </div>

        {{-- Modal Body / Form --}}
        <form :action="editForm.actionUrl" method="POST" style="display: flex; flex-direction: column; overflow-y: auto; flex: 1;">
            @csrf
            @method('PUT')

            <div class="card-body" style="padding: var(--space-5);">
                {{-- Name Fields Grid --}}
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-4);">
                    <div class="form-group-premium">
                        <label for="modal_first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            id="modal_first_name"
                            name="first_name"
                            class="form-control"
                            x-model="editForm.first_name"
                            required
                        >
                    </div>

                    <div class="form-group-premium">
                        <label for="modal_middle_name" class="form-label">Middle Name <span class="text-muted" style="font-weight: 400;">(optional)</span></label>
                        <input
                            type="text"
                            id="modal_middle_name"
                            name="middle_name"
                            class="form-control"
                            x-model="editForm.middle_name"
                        >
                    </div>

                    <div class="form-group-premium">
                        <label for="modal_last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            id="modal_last_name"
                            name="last_name"
                            class="form-control"
                            x-model="editForm.last_name"
                            required
                        >
                    </div>
                </div>

                {{-- DOB and Expertise Grid --}}
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-4);">
                    <div class="form-group-premium">
                        <label for="modal_date_of_birth" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                        <input
                            type="date"
                            id="modal_date_of_birth"
                            name="date_of_birth"
                            class="form-control"
                            x-model="editForm.date_of_birth"
                            max="{{ now()->subYears(16)->format('Y-m-d') }}"
                            required
                        >
                    </div>

                    <div class="form-group-premium">
                        <label for="modal_expertise" class="form-label">Expertise / Role <span class="text-danger">*</span></label>
                        <select
                            id="modal_expertise"
                            name="expertise"
                            class="form-control"
                            x-model="editForm.expertise"
                            required
                        >
                            @foreach ($expertiseOptions as $opt)
                                <option value="{{ $opt }}">{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Project Assignment --}}
                <div class="form-group-premium" style="margin-bottom: var(--space-4);">
                    <label for="modal_project_id" class="form-label">Assigned Project</label>
                    <select
                        id="modal_project_id"
                        name="project_id"
                        class="form-control"
                        x-model="editForm.project_id"
                    >
                        <option value="">No Assignment (Available)</option>
                        @foreach ($activeProjects as $project)
                            <option value="{{ $project->id }}">{{ $project->project_code }} — {{ $project->name }}</option>
                        @endforeach
                    </select>
                    <p class="form-hint" style="font-size: 0.75rem; color: var(--color-muted); margin-top: 4px;">
                        Changing the project updates assignment history automatically.
                    </p>
                </div>

                {{-- Address Field --}}
                <div class="form-group-premium" style="margin-bottom: var(--space-4);">
                    <label for="modal_address" class="form-label">Address <span class="text-danger">*</span></label>
                    <textarea
                        id="modal_address"
                        name="address"
                        rows="2"
                        maxlength="1000"
                        class="form-control"
                        x-model="editForm.address"
                        required
                    ></textarea>
                </div>

                {{-- Archive Profile Option Box --}}
                <div
                    class="archive-panel-card"
                    style="background: var(--color-surface-alt, rgba(239, 68, 68, 0.04)); border: 1px dashed var(--color-border); border-left: 4px solid var(--color-danger, #ef4444); border-radius: var(--radius-md); padding: var(--space-4);"
                >
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 200px;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                <svg style="width: 16px; height: 16px; color: var(--color-danger, #ef4444);" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z" />
                                    <path fill-rule="evenodd" d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" clip-rule="evenodd" />
                                </svg>
                                <strong style="color: var(--color-danger, #ef4444); font-size: 0.875rem;">Archive Personnel Profile</strong>
                            </div>
                            <p style="margin: 0; font-size: 0.8125rem; color: var(--color-muted);">
                                Move this profile to the inactive roster. Assigned projects will be released automatically.
                            </p>
                            <label class="form-check" style="cursor: pointer; margin-top: 0.5rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <input
                                    type="checkbox"
                                    name="archive_profile"
                                    value="1"
                                    class="form-check-input"
                                    x-model="editForm.archive_profile"
                                    id="modal_archive_profile"
                                >
                                <span class="form-check-label" style="font-size: 0.8125rem; font-weight: 500;">
                                    Place personnel profile to archive on save
                                </span>
                            </label>
                        </div>

                        <div>
                            <button
                                type="button"
                                class="btn btn-sm btn-danger"
                                style="background: var(--color-danger, #ef4444); border-color: var(--color-danger, #ef4444); color: #fff; font-size: 0.8125rem; padding: 0.35rem 0.75rem;"
                                @click="archiveDirectly(editForm.id, editForm.first_name + ' ' + editForm.last_name)"
                            >
                                Archive Profile Now
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="card-footer" style="padding: var(--space-4) var(--space-5); display: flex; justify-content: flex-end; gap: var(--space-3); border-top: 1px solid var(--color-border);">
                <button type="button" class="btn btn-secondary btn-sm" @click="editModalOpen = false">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary btn-sm" id="btn-modal-save-personnel">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Standalone Form for Direct Archive Action --}}
<form id="standalone-archive-form" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
