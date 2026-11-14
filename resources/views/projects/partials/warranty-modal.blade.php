{{-- ─── Modal: Warranty & Maintenance Period Inclusion ───────────────────────── --}}
<div 
    class="reassign-modal-overlay" 
    style="display: none;" 
    x-show="warrantyInfoModalOpen" 
    x-cloak
    @keydown.escape.window="warrantyInfoModalOpen = false"
    @open-warranty-modal.window="warrantyInfoModalOpen = true"
>
    <div class="reassign-modal-backdrop" @click="warrantyInfoModalOpen = false"></div>
    <div 
        class="reassign-modal-content card" 
        x-show="warrantyInfoModalOpen" 
        x-transition 
        style="max-width: 600px; width: 100%; border-top: 4px solid var(--color-primary); max-height: 90vh; display: flex; flex-direction: column;"
    >
        <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; padding: var(--space-4) var(--space-5);">
            <div style="display: flex; align-items: center; gap: var(--space-3);">
                <div style="width: 36px; height: 36px; border-radius: var(--radius-md); background: rgba(44, 95, 124, 0.12); color: var(--color-primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg style="width: 20px; height: 20px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                </div>
                <div>
                    <h2 style="font-weight: 700; font-size: 1.05rem; margin: 0; color: var(--color-ink);">WARRANTY & COVERAGE DETAILS</h2>
                    <span class="text-muted" style="font-size: 0.75rem;">Coverage Inclusions, Exclusions & Remaining Duration</span>
                </div>
            </div>
            <button type="button" class="flash-close" @click="warrantyInfoModalOpen = false" aria-label="Close modal" style="background: none; border: none; font-size: 1.5rem; line-height: 1; cursor: pointer; color: var(--color-muted); padding: 0 4px;">&times;</button>
        </div>

        <div class="card-body" style="padding: var(--space-5); overflow-y: auto; font-size: 0.875rem; line-height: 1.6; color: var(--color-ink); display: flex; flex-direction: column; gap: var(--space-4);">
            {{-- Dynamic Project Details & Remaining Days --}}
            <template x-if="typeof selectedWarrantyProject !== 'undefined' && selectedWarrantyProject">
                <div style="background: var(--color-surface-alt); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--color-border); border-left: 4px solid var(--color-success); display: flex; flex-direction: column; gap: var(--space-2);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                        <div>
                            <span class="font-mono" style="font-size: 0.75rem; color: var(--color-muted);" x-text="selectedWarrantyProject.code"></span>
                            <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 2px 0 0; color: var(--color-ink);" x-text="selectedWarrantyProject.name"></h3>
                        </div>
                        <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; font-size: 0.6875rem; font-weight: 700; border-radius: 9999px; background: rgba(46, 125, 83, 0.12); color: var(--color-success); border: 1px solid rgba(46, 125, 83, 0.25); flex-shrink: 0;">
                            <span style="width: 6px; height: 6px; border-radius: 50%; background: var(--color-success);"></span>
                            Active Warranty
                        </span>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-top: 4px; padding-top: 8px; border-top: 1px solid var(--color-border);">
                        <div>
                            <div style="font-size: 0.6875rem; text-transform: uppercase; color: var(--color-muted); font-weight: 600;">Warranty Remaining</div>
                            <div style="font-size: 0.9375rem; font-weight: 700; font-family: var(--font-mono); color: var(--color-success);" x-text="selectedWarrantyProject.days_remaining === 0 ? 'Last day today' : selectedWarrantyProject.days_remaining + ' day(s) remaining'"></div>
                        </div>
                        <div>
                            <div style="font-size: 0.6875rem; text-transform: uppercase; color: var(--color-muted); font-weight: 600;">Valid Until</div>
                            <div style="font-size: 0.875rem; font-weight: 600; font-family: var(--font-mono); color: var(--color-ink);" x-text="selectedWarrantyProject.end_date"></div>
                        </div>
                        <div>
                            <div style="font-size: 0.6875rem; text-transform: uppercase; color: var(--color-muted); font-weight: 600;">Coverage Period</div>
                            <div style="font-size: 0.875rem; font-weight: 600; color: var(--color-ink);" x-text="selectedWarrantyProject.period"></div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Equipment Warranty --}}
            <div>
                <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0 0 8px 0; color: var(--color-ink);">
                    Equipment Warranty
                </h3>
                <p style="margin: 0 0 10px 0; color: var(--color-ink);">
                    All elevator equipment, materials, and components supplied under this proposal are warranted against defects in materials, manufacturing, and workmanship under normal operating conditions for a period of <strong>twelve (12) months</strong> from the date of Project Acceptance and Turnover by the Client.
                </p>
                <p style="margin: 0; color: var(--color-ink);">
                    During the warranty period, the Contractor shall repair or replace, at no additional cost to the Client, any defective parts or components determined to be caused by manufacturing defects or faulty workmanship.
                </p>
            </div>

            {{-- Exclusions --}}
            <div style="background: var(--color-surface-alt); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                <h4 style="font-size: 0.8125rem; font-weight: 700; margin-top: 0; margin-bottom: 8px; color: var(--color-ink); display: flex; align-items: center; gap: 6px;">
                    <svg style="width: 15px; height: 15px; color: #f59e0b; flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    This warranty shall not cover defects, damages, or failures resulting from:
                </h4>
                <ul style="margin: 0; padding-left: 20px; color: var(--color-muted); font-size: 0.8125rem; display: flex; flex-direction: column; gap: 5px;">
                    <li>Improper operation, misuse, negligence, or vandalism;</li>
                    <li>Unauthorized repairs, alterations, or modifications performed by parties other than the Contractor;</li>
                    <li>Power fluctuations, electrical faults, flooding, fire, natural disasters, or other force majeure events;</li>
                    <li>Normal wear and tear of consumable components.</li>
                </ul>
            </div>

            {{-- Preventive Maintenance Services --}}
            <div style="border-top: 1px solid var(--color-border); padding-top: var(--space-3);">
                <h4 style="font-size: 0.8125rem; font-weight: 700; margin-top: 0; margin-bottom: 6px; color: var(--color-ink); display: flex; align-items: center; gap: 6px;">
                    <svg style="width: 15px; height: 15px; color: var(--color-primary); flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                    </svg>
                    Preventive Maintenance Services
                </h4>
                <p style="margin: 0; font-size: 0.8125rem; color: var(--color-muted);">
                    Preventive maintenance services during the warranty period shall be provided in accordance with the maintenance schedule specified in this proposal.
                </p>
            </div>
        </div>

        <div class="card-footer" style="display: flex; justify-content: flex-end; align-items: center; gap: var(--space-2); padding: var(--space-3) var(--space-5);">
            <button type="button" class="btn btn-secondary" @click="warrantyInfoModalOpen = false">
                Close
            </button>
        </div>
    </div>
</div>

