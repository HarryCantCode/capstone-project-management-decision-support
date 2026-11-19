{{--
    Onboarding Tour Partial — Interactive Step-by-Step System Walkthrough
    Supports automatic triggering for first-time users per module,
    manual triggering via the Info button ("Show tutorial"),
    smooth auto-scroll, spotlight cutout mask, responsive popover,
    role-filtered module descriptions, and keyboard navigation.
--}}

@php
    $currentModule = 'dashboard';
    if (request()->routeIs('dashboard')) {
        $currentModule = 'dashboard';
    } elseif (request()->routeIs('projects.create')) {
        $currentModule = 'projects.create';
    } elseif (request()->routeIs('projects.show')) {
        $currentModule = 'projects.show';
    } elseif (request()->routeIs('projects.*')) {
        $currentModule = 'projects';
    } elseif (request()->routeIs('costing.show')) {
        $currentModule = 'costing.show';
    } elseif (request()->routeIs('costing.*')) {
        $currentModule = 'costing';
    } elseif (request()->routeIs('pending-actions.*') || request()->is('pending-actions*')) {
        $currentModule = 'pending-actions';
    } elseif (request()->routeIs('approval-list.*') || request()->is('approval-list*')) {
        $currentModule = 'approval-list';
    } elseif (request()->routeIs('resources.*')) {
        $currentModule = 'resources';
    } elseif (request()->routeIs('scheduling.*')) {
        $currentModule = 'scheduling';
    } elseif (request()->routeIs('reports.*')) {
        $currentModule = 'reports';
    } elseif (request()->routeIs('decision-support.*')) {
        $currentModule = 'decision-support';
    } elseif (request()->routeIs('account.*')) {
        $currentModule = 'account';
    }

    $currentUser = Auth::user();
    $isAdmin = $currentUser ? $currentUser->hasRole('Admin') : false;
    $isManager = $currentUser ? $currentUser->hasRole('Manager') : false;
    $isStaff = $currentUser ? $currentUser->hasRole('Staff') : false;
    $userId = $currentUser ? $currentUser->id : 0;
@endphp

{{-- Hidden HTML template for the Sidebar step module summary (Role-filtered) --}}
<template id="dex-tour-sidebar-summary-template">
    <div class="tour-intro-wrap">
        <p class="tour-text-lead">Welcome to <strong>Dex PMS</strong>. The sidebar provides access to all modules available to your role. Here is a summary of the core modules:</p>
        <div class="tour-modules-list">
            {{-- Project module — All roles --}}
            <div class="tour-module-row">
                <div class="tour-module-name">
                    <svg class="tour-module-icon" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M6 2a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7l-5-5H6zm5 1v4h4l-4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>Project module</span>
                </div>
                <div class="tour-module-desc">Where you can view your projects and manage them.</div>
            </div>

            {{-- Costing module — All roles --}}
            <div class="tour-module-row">
                <div class="tour-module-name">
                    <svg class="tour-module-icon" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 0 1-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 0 1-.567.267z"/>
                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm1-13a1 1 0 1 0-2 0v.092a4.535 4.535 0 0 0-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 1 0-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 1 0 2 0v-.092a4.535 4.535 0 0 0 1.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0 0 11 9.092V7.151c.391.127.68.317.843.504a1 1 0 1 0 1.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                    </svg>
                    <span>Costing module</span>
                </div>
                <div class="tour-module-desc">Where you can track your expenses and budget for a project.</div>
            </div>

            {{-- Staff request module — Admin and Manager only --}}
            @if($isAdmin || $isManager)
            <div class="tour-module-row">
                <div class="tour-module-name">
                    <svg class="tour-module-icon" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M9 2a1 1 0 0 0 0 2h2a1 1 0 1 0 0-2H9z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 0 1 2-2 3 3 0 0 0 3 3h2a3 3 0 0 0 3-3 2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5zm9.707 5.707a1 1 0 0 0-1.414-1.414L9 12.586l-1.293-1.293a1 1 0 0 0-1.414 1.414l2 2a1 1 0 0 0 1.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>Staff request module (for admin/manager)</span>
                </div>
                <div class="tour-module-desc">You can see the request that was made by your staff related to progression of a project.</div>
            </div>
            @endif

            {{-- Approval list module — Staff only --}}
            @if($isStaff)
            <div class="tour-module-row">
                <div class="tour-module-name">
                    <svg class="tour-module-icon" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 2a1 1 0 0 1 1 1v1.07A7.002 7.002 0 0 1 17 11v3.586l1.707 1.707A1 1 0 0 1 18 18H2a1 1 0 0 1-.707-1.707L3 14.586V11a7.002 7.002 0 0 1 6-6.93V3a1 1 0 0 1 1-1zm0 18a3 3 0 0 1-3-3h6a3 3 0 0 1-3 3z" clip-rule="evenodd"/>
                    </svg>
                    <span>Approval List module</span>
                </div>
                <div class="tour-module-desc">Track and review requests submitted for management review.</div>
            </div>
            @endif

            {{-- Materials catalog module — All roles --}}
            <div class="tour-module-row">
                <div class="tour-module-name">
                    <svg class="tour-module-icon" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M4 3a2 2 0 1 0 0 4h12a2 2 0 1 0 0-4H4zm0 6a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2H4zm2 3h8v3H6v-3z"/>
                    </svg>
                    <span>Materials catalog module</span>
                </div>
                <div class="tour-module-desc">Monitor your the changes from your material list.</div>
            </div>

            {{-- Manpower module — Admin and Manager only --}}
            @if($isAdmin || $isManager)
            <div class="tour-module-row">
                <div class="tour-module-name">
                    <svg class="tour-module-icon" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M9 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM17 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 0 0-1.5-4.33A5 5 0 0 1 19 16v1h-6.07zM6 11a5 5 0 0 1 5 5v1H1v-1a5 5 0 0 1 5-5z"/>
                    </svg>
                    <span>Manpower module</span>
                </div>
                <div class="tour-module-desc">List of all your active available people that you may assign from each project.</div>
            </div>
            @endif

            {{-- Reports module — All roles --}}
            <div class="tour-module-row">
                <div class="tour-module-name">
                    <svg class="tour-module-icon" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M6 2a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7.414A2 2 0 0 0 15.414 7L11 2.586A2 2 0 0 0 9.586 2H6zm2 10a1 1 0 1 0 0 2h4a1 1 0 1 0 0-2H8zm0-4a1 1 0 1 0 0 2h4a1 1 0 1 0 0-2H8z" clip-rule="evenodd"/>
                    </svg>
                    <span>Reports module</span>
                </div>
                <div class="tour-module-desc">Generate an exportable information about your project, materials, revenue, and manpower.</div>
            </div>

            {{-- Decision Support module — All roles --}}
            <div class="tour-module-row">
                <div class="tour-module-name">
                    <svg class="tour-module-icon" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M11 3a1 1 0 1 0-2 0v1a1 1 0 1 0 2 0V3zM15.657 5.757a1 1 0 0 0-1.414-1.414l-.707.707a1 1 0 0 0 1.414 1.414l.707-.707zM18 10a1 1 0 0 1-1 1h-1a1 1 0 1 1 0-2h1a1 1 0 0 1 1 1zM5.05 6.464A1 1 0 1 0 6.464 5.05l-.707-.707a1 1 0 0 0-1.414 1.414l.707.707zM5 10a1 1 0 0 1-1 1H3a1 1 0 1 1 0-2h1a1 1 0 0 1 1 1zM8 16v-1h4v1a2 2 0 1 1-4 0zM12 14c.015-.34.208-.646.477-.859a4 4 0 1 0-4.954 0c.27.213.462.519.476.859h4.001z"/>
                    </svg>
                    <span>Decision Support module</span>
                </div>
                <div class="tour-module-desc">Helps you strategize and provides informed choices guiding you toward the best possible action for your business.</div>
            </div>

            {{-- User management module — Admin only --}}
            @if($isAdmin)
            <div class="tour-module-row">
                <div class="tour-module-name">
                    <svg class="tour-module-icon" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-7 9a7 7 0 1 1 14 0H3z" clip-rule="evenodd"/>
                    </svg>
                    <span>User management module</span>
                </div>
                <div class="tour-module-desc">Manage who access your system.</div>
            </div>
            @endif
        </div>
    </div>
</template>

{{-- Onboarding Tour Container & Overlay DOM --}}
<div id="dex-tour-container" class="dex-tour-container" style="display: none;" aria-live="polite">
    {{-- SVG Cutout Backdrop Mask --}}
    <svg class="dex-tour-mask-svg" width="100%" height="100%">
        <defs>
            <mask id="dex-tour-mask">
                <rect x="0" y="0" width="100%" height="100%" fill="white" />
                <rect id="dex-tour-cutout" x="0" y="0" width="0" height="0" rx="8" ry="8" fill="black" />
            </mask>
        </defs>
        <rect class="dex-tour-backdrop-rect" x="0" y="0" width="100%" height="100%" mask="url(#dex-tour-mask)" />
    </svg>

    {{-- Glowing Spotlight Highlight Box --}}
    <div id="dex-tour-highlight-box" class="dex-tour-highlight-box" aria-hidden="true"></div>

    {{-- Interactive Tooltip Popover --}}
    <div id="dex-tour-popover" class="dex-tour-popover" role="dialog" aria-modal="true" aria-labelledby="dex-tour-title">
        <div id="dex-tour-arrow" class="dex-tour-arrow"></div>

        {{-- Popover Header --}}
        <div class="dex-tour-header">
            <div class="dex-tour-badges">
                <span id="dex-tour-step-badge" class="dex-tour-badge dex-tour-badge--step font-mono">Step 1 of 6</span>
                <span id="dex-tour-module-badge" class="dex-tour-badge dex-tour-badge--module">DASHBOARD</span>
            </div>
            <button type="button" id="dex-tour-close-btn" class="dex-tour-close-btn" aria-label="Close tutorial" title="Close tutorial (Esc)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        {{-- Popover Body --}}
        <div class="dex-tour-body">
            <h3 id="dex-tour-title" class="dex-tour-title"></h3>
            <div id="dex-tour-description" class="dex-tour-description"></div>
        </div>

        {{-- Popover Footer --}}
        <div class="dex-tour-footer">
            <div id="dex-tour-dots" class="dex-tour-dots" aria-hidden="true"></div>
            <div class="dex-tour-actions">
                <button type="button" id="dex-tour-skip-btn" class="dex-tour-btn dex-tour-btn--skip">Skip tour</button>
                <div class="dex-tour-nav-btns">
                    <button type="button" id="dex-tour-prev-btn" class="dex-tour-btn dex-tour-btn--secondary">Back</button>
                    <button type="button" id="dex-tour-next-btn" class="dex-tour-btn dex-tour-btn--primary">
                        <span id="dex-tour-next-text">Next</span>
                        <svg id="dex-tour-next-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ─── Onboarding Tour Overlay & Highlight Styles ───────────────────────────── */
.dex-tour-container {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    z-index: 99990;
    pointer-events: auto;
}

.dex-tour-mask-svg {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    pointer-events: auto;
    z-index: 99991;
}

.dex-tour-backdrop-rect {
    fill: rgba(15, 23, 42, 0.68);
    transition: fill var(--transition-base);
}

[data-theme="dark"] .dex-tour-backdrop-rect {
    fill: rgba(5, 8, 15, 0.82);
}

/* ─── Spotlight Glowing Frame ────────────────────────────────────────── */
.dex-tour-highlight-box {
    position: fixed;
    pointer-events: none;
    border-radius: var(--radius-md, 8px);
    border: 2px solid var(--color-accent);
    box-shadow: 0 0 0 4px rgba(44, 95, 124, 0.28), 0 0 28px rgba(44, 95, 124, 0.25);
    z-index: 99995;
    opacity: 0;
    transition: top 0.28s cubic-bezier(0.16, 1, 0.3, 1),
                left 0.28s cubic-bezier(0.16, 1, 0.3, 1),
                width 0.28s cubic-bezier(0.16, 1, 0.3, 1),
                height 0.28s cubic-bezier(0.16, 1, 0.3, 1),
                opacity 0.2s ease;
}

[data-theme="dark"] .dex-tour-highlight-box {
    border-color: #38BDF8;
    box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.32), 0 0 30px rgba(56, 189, 248, 0.35);
}

/* ─── Tour Tooltip Popover ──────────────────────────────────────────── */
.dex-tour-popover {
    position: fixed;
    z-index: 99999;
    width: 440px;
    max-width: calc(100vw - 32px);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl, 12px);
    box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.28), 0 4px 16px -2px rgba(0, 0, 0, 0.08);
    padding: 1.25rem 1.35rem;
    box-sizing: border-box;
    color: var(--color-ink);
    opacity: 0;
    transform: scale(0.96);
    transition: top 0.28s cubic-bezier(0.16, 1, 0.3, 1),
                left 0.28s cubic-bezier(0.16, 1, 0.3, 1),
                opacity 0.2s ease,
                transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.dex-tour-popover.is-visible {
    opacity: 1;
    transform: scale(1);
}

[data-theme="dark"] .dex-tour-popover {
    background: #111A28;
    border-color: #243042;
    box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.75), 0 4px 20px -2px rgba(0, 0, 0, 0.45);
    color: #F1F5F9;
}

/* ─── Popover Arrow ─────────────────────────────────────────────────── */
.dex-tour-arrow {
    position: absolute;
    width: 14px;
    height: 14px;
    background: var(--color-surface);
    transform: rotate(45deg);
    z-index: -1;
    transition: top 0.25s ease, left 0.25s ease;
}

[data-theme="dark"] .dex-tour-arrow {
    background: #111A28;
}

.dex-tour-popover[data-placement="right"] .dex-tour-arrow {
    left: -7px;
    border-left: 1px solid var(--color-border);
    border-bottom: 1px solid var(--color-border);
}

.dex-tour-popover[data-placement="left"] .dex-tour-arrow {
    right: -7px;
    border-right: 1px solid var(--color-border);
    border-top: 1px solid var(--color-border);
}

.dex-tour-popover[data-placement="bottom"] .dex-tour-arrow {
    top: -7px;
    border-left: 1px solid var(--color-border);
    border-top: 1px solid var(--color-border);
}

.dex-tour-popover[data-placement="top"] .dex-tour-arrow {
    bottom: -7px;
    border-right: 1px solid var(--color-border);
    border-bottom: 1px solid var(--color-border);
}

[data-theme="dark"] .dex-tour-popover .dex-tour-arrow {
    border-color: #243042 !important;
}

/* ─── Header & Badges ────────────────────────────────────────────────── */
.dex-tour-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-2);
    margin-bottom: var(--space-2);
}

.dex-tour-badges {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.dex-tour-badge {
    font-size: 0.65625rem;
    font-weight: 600;
    line-height: 1;
    padding: 2.5px 7px;
    border-radius: var(--radius-pill);
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.dex-tour-badge--step {
    background: var(--color-accent-light);
    color: var(--color-accent);
}

[data-theme="dark"] .dex-tour-badge--step {
    background: rgba(56, 189, 248, 0.16);
    color: #38BDF8;
}

.dex-tour-badge--module {
    background: var(--color-surface-alt);
    color: var(--color-muted);
}

[data-theme="dark"] .dex-tour-badge--module {
    background: #1A263A;
    color: #94A3B8;
}

.dex-tour-close-btn {
    background: transparent;
    border: none;
    color: var(--color-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: var(--radius-sm);
    transition: background-color var(--transition-fast), color var(--transition-fast), transform var(--transition-fast);
}

.dex-tour-close-btn:hover {
    background-color: var(--color-surface-alt);
    color: var(--color-danger);
    transform: scale(1.08);
}

/* ─── Body Content ──────────────────────────────────────────────────── */
.dex-tour-title {
    font-size: 1.0625rem;
    font-weight: 700;
    color: var(--color-ink);
    margin: 0 0 var(--space-2) 0;
    line-height: 1.35;
}

[data-theme="dark"] .dex-tour-title {
    color: #F1F5F9;
}

.dex-tour-description {
    font-size: 0.84375rem;
    line-height: 1.55;
    color: var(--color-muted);
}

[data-theme="dark"] .dex-tour-description {
    color: #94A3B8;
}

.dex-tour-description p {
    margin: 0 0 0.5rem 0;
}

.dex-tour-description p:last-child {
    margin-bottom: 0;
}

.dex-tour-description strong {
    color: var(--color-ink);
    font-weight: 600;
}

[data-theme="dark"] .dex-tour-description strong {
    color: #F1F5F9;
}

/* ─── Sidebar Modules List Showcase ─────────────────────────────────── */
.tour-text-lead {
    font-size: 0.8125rem;
    line-height: 1.45;
    margin-bottom: 0.625rem;
    color: var(--color-ink);
}

[data-theme="dark"] .tour-text-lead {
    color: #E2E8F0;
}

.tour-modules-list {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    max-height: 270px;
    overflow-y: auto;
    padding-right: 4px;
    margin-top: 0.25rem;
}

.tour-modules-list::-webkit-scrollbar {
    width: 4px;
}

.tour-modules-list::-webkit-scrollbar-thumb {
    background: var(--color-border);
    border-radius: 4px;
}

[data-theme="dark"] .tour-modules-list::-webkit-scrollbar-thumb {
    background: #2E3C52;
}

.tour-module-row {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 6px 10px;
    border-radius: var(--radius-sm);
    background: var(--color-surface-alt);
    border-left: 3px solid var(--color-accent);
}

[data-theme="dark"] .tour-module-row {
    background: #152033;
    border-left-color: #38BDF8;
}

.tour-module-name {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--color-ink);
}

[data-theme="dark"] .tour-module-name {
    color: #F1F5F9;
}

.tour-module-icon {
    width: 14px;
    height: 14px;
    flex-shrink: 0;
    color: var(--color-accent);
}

[data-theme="dark"] .tour-module-icon {
    color: #38BDF8;
}

.tour-module-desc {
    font-size: 0.75rem;
    line-height: 1.4;
    color: var(--color-muted);
}

[data-theme="dark"] .tour-module-desc {
    color: #94A3B8;
}

/* ─── Footer & Navigation Controls ──────────────────────────────────── */
.dex-tour-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3, 0.75rem);
    margin-top: 1rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--color-border);
    box-sizing: border-box;
    width: 100%;
}

[data-theme="dark"] .dex-tour-footer {
    border-top-color: #243042;
}

.dex-tour-dots {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.dex-tour-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--color-border);
    border: none;
    padding: 0;
    cursor: pointer;
    flex-shrink: 0;
    transition: background-color var(--transition-fast), transform var(--transition-fast), width var(--transition-fast), opacity var(--transition-fast);
}

.dex-tour-dot:hover {
    background: var(--color-accent);
}

.dex-tour-dot.is-active {
    width: 12px;
    border-radius: var(--radius-pill);
    background: var(--color-accent);
}

.dex-tour-dot.is-edge {
    width: 3px;
    height: 3px;
    opacity: 0.5;
}

[data-theme="dark"] .dex-tour-dot {
    background: #2E3C52;
}

[data-theme="dark"] .dex-tour-dot:hover {
    background: #38BDF8;
}

[data-theme="dark"] .dex-tour-dot.is-active {
    background: #38BDF8;
}

.dex-tour-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    margin-left: auto;
}

.dex-tour-nav-btns {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

.dex-tour-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8125rem;
    font-weight: 600;
    border-radius: var(--radius-md);
    padding: 0.35rem 0.75rem;
    cursor: pointer;
    line-height: 1.25;
    white-space: nowrap;
    flex-shrink: 0;
    box-sizing: border-box;
    transition: background var(--transition-fast), border-color var(--transition-fast), color var(--transition-fast), transform var(--transition-fast);
}

.dex-tour-btn--skip {
    background: transparent;
    border: none;
    color: var(--color-muted);
    font-weight: 500;
    padding: 0.35rem 0.5rem;
    font-size: 0.8125rem;
}

.dex-tour-btn--skip:hover {
    color: var(--color-ink);
    text-decoration: underline;
}

[data-theme="dark"] .dex-tour-btn--skip:hover {
    color: #F1F5F9;
}

.dex-tour-btn--secondary {
    background: transparent;
    border: 1px solid var(--color-border);
    color: var(--color-ink);
}

.dex-tour-btn--secondary:hover:not(:disabled) {
    background: var(--color-surface-alt);
    border-color: var(--color-accent);
}

[data-theme="dark"] .dex-tour-btn--secondary {
    border-color: #2E3C52;
    color: #CBD5E1;
}

[data-theme="dark"] .dex-tour-btn--secondary:hover:not(:disabled) {
    background: #1A263A;
    border-color: #38BDF8;
    color: #FFFFFF;
}

.dex-tour-btn--secondary:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.dex-tour-btn--primary {
    background: var(--color-accent);
    border: 1px solid var(--color-accent);
    color: #FFFFFF;
    gap: 5px;
}

.dex-tour-btn--primary:hover {
    background: var(--color-accent-hover, #245270);
    border-color: var(--color-accent-hover, #245270);
    transform: translateY(-1px);
}

[data-theme="dark"] .dex-tour-btn--primary {
    background: #2C5F7C;
    border-color: #38BDF8;
    color: #FFFFFF;
}

[data-theme="dark"] .dex-tour-btn--primary:hover {
    background: #38BDF8;
    color: #0B0F19;
}

/* ─── Header Info Button & Hover Tooltip ─────────────────────────────── */
.tour-trigger-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.tour-trigger-btn {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: var(--radius-md);
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    color: var(--color-ink);
    cursor: pointer;
    transition: border-color var(--transition-fast), background-color var(--transition-fast), color var(--transition-fast), transform var(--transition-fast);
}

.tour-trigger-btn:hover {
    background-color: var(--color-surface-alt);
    border-color: var(--color-accent);
    color: var(--color-accent);
    transform: translateY(-1px);
}

.tour-trigger-btn:focus-visible {
    outline: 2px solid var(--color-accent);
    outline-offset: -2px;
}

.tour-trigger-icon {
    width: 18px;
    height: 18px;
}

[data-theme="dark"] .tour-trigger-btn {
    background: #131B28;
    border-color: var(--color-border);
    color: var(--color-ink);
}

[data-theme="dark"] .tour-trigger-btn:hover {
    background: var(--color-surface-alt);
    border-color: var(--color-accent);
    color: var(--color-accent);
}

/* Custom instant hover tooltip for "Show tutorial" */
.tour-trigger-wrap::after {
    content: "Show tutorial";
    position: absolute;
    top: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    background: #1B1E23;
    color: #FFFFFF;
    font-size: 0.75rem;
    font-weight: 500;
    padding: 4px 8px;
    border-radius: var(--radius-sm);
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity var(--transition-fast), transform var(--transition-fast);
    z-index: 1000;
    box-shadow: var(--shadow-md);
}

[data-theme="dark"] .tour-trigger-wrap::after {
    background: #243042;
    color: #F1F5F9;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.tour-trigger-wrap:hover::after {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
}
</style>

<script>
/**
 * Dex PMS Interactive Onboarding Tour Engine
 */
(function() {
    const currentModule = '{{ $currentModule }}';
    const userId = '{{ $userId }}';
    const isAdmin = {{ $isAdmin ? 'true' : 'false' }};
    const isManager = {{ $isManager ? 'true' : 'false' }};
    const isStaff = {{ $isStaff ? 'true' : 'false' }};

    let active = false;
    let currentStepIdx = 0;
    let activeSteps = [];
    let currentModuleKey = currentModule;
    let scrollRafId = null;
    let isTransitioning = false;
    let minAllowedScrollY = 0;
    let maxAllowedScrollY = 0;
    let touchStartY = 0;
    let previousProjectShowState = null;

    // Multi-module step definitions
    const modulesConfig = {
        'dashboard': [
            {
                target: '#sidebar',
                title: 'System Navigation & Modules',
                moduleName: 'Overview',
                placement: 'right',
                padding: 4,
                getContent: function() {
                    const tpl = document.getElementById('dex-tour-sidebar-summary-template');
                    return tpl ? tpl.innerHTML : '';
                }
            },
            {
                target: '[data-tour="dashboard-kpis"]',
                title: 'Project Status KPI Overview',
                moduleName: 'Dashboard',
                placement: 'bottom',
                padding: 8,
                content: '<p>Track live project volumes across the entire organization: <strong>Total Projects</strong>, <strong>Ongoing Projects</strong>, <strong>Pending Projects</strong>, and <strong>Completed Projects</strong>. Click any card to filter projects instantly.</p>'
            },
            {
                target: '[data-tour="task-overview"]',
                title: 'Task Overview & Pipeline Organizer',
                moduleName: 'Dashboard',
                placement: 'bottom',
                padding: 8,
                content: '<p>Monitor task execution metrics, total mother tasks, in-progress tasks, and completed items. The <strong>Task Stage Distribution</strong> pipeline and <strong>Checklist Execution Rate</strong> keep your project milestones on schedule.</p>'
            },
            {
                target: '[data-tour="manpower-stats"]',
                title: 'Manpower Statistics & Allocation',
                moduleName: 'Dashboard',
                placement: 'bottom',
                padding: 8,
                skipIfMissing: true,
                content: '<p>Analyze workforce deployment in real time. The circular utilization gauge, deployment counts, available personnel, and trade specialization distribution provide visibility into your active workforce.</p>'
            },
            {
                target: '[data-tour="recent-projects"]',
                title: 'Recent Projects & Progression',
                moduleName: 'Dashboard',
                placement: 'top',
                padding: 8,
                content: '<p>Review the latest active projects and their real-time progression percentages. Each card details client information, target completion dates, and checklist completion. Click on any project to open its details.</p>'
            },
            {
                target: '[data-tour="page-actions"]',
                title: 'Quick Action: New Project',
                moduleName: 'Dashboard',
                placement: 'bottom',
                padding: 6,
                content: '<p>Create a new project initiative anytime by clicking <strong>+ New Project</strong>. Define client contracts, timeline dates, and initial budget figures.</p>'
            },
            {
                target: '#dex-tour-trigger-btn',
                title: 'Show Tutorial & System Alerts',
                moduleName: 'Top Header',
                placement: 'bottom',
                padding: 6,
                content: '<p>Whenever you need guidance on any page, click this <strong>Info icon</strong> ("Show tutorial") to view the tutorial for that module. Receive real-time system alerts via the Notification Bell beside it.</p>'
            }
        ],
        'projects.create': [
            {
                target: '#section-identity',
                title: 'Project Identity & Scope',
                moduleName: 'Create Project',
                placement: 'bottom',
                padding: 8,
                content: '<p>Input your project information and it will be save to your project directory/list.</p>'
            },
            {
                target: '#section-specs',
                title: 'Elevator Technical Specifications',
                moduleName: 'Create Project',
                placement: 'bottom',
                padding: 8,
                content: '<p>Technical specification required for building or constructing an elevator.</p>'
            },
            {
                target: '#section-location',
                title: 'Site Address / Project Location',
                moduleName: 'Create Project',
                placement: 'bottom',
                padding: 8,
                content: '<p>Location where the project will take place.</p>'
            },
            {
                target: '#section-schedule',
                title: 'Execution Schedule & Timeline',
                moduleName: 'Create Project',
                placement: 'bottom',
                padding: 8,
                content: '<p>Duration or timeline of the project.</p>'
            },
            {
                target: '#project-manpower-section',
                title: 'Project Manpower & Staffing',
                moduleName: 'Create Project',
                placement: 'bottom',
                padding: 8,
                skipIfMissing: true,
                content: '<p>Assign available manpower based on your recommeneded project specification.</p>'
            },
            {
                target: '#project-expenses-section',
                title: 'Initial Materials & Expenditures',
                moduleName: 'Create Project',
                placement: 'bottom',
                padding: 8,
                skipIfMissing: true,
                content: '<p>Log and track your expenses from this project.</p>'
            },
            {
                target: '#project-attachments-section',
                title: 'Project File Attachments',
                moduleName: 'Create Project',
                placement: 'bottom',
                padding: 8,
                skipIfMissing: true,
                content: '<p>Attach required files and documents uplon project initialization.</p>'
            },
            {
                target: '#section-financials',
                title: 'Financials & Billing',
                moduleName: 'Create Project',
                placement: 'bottom',
                padding: 8,
                content: '<p>Enter the contract amount and monitor your expenditures, see chose payment status and attach an invoice upon project creation.</p>'
            },
            {
                target: '#section-preview',
                title: 'Project Preview',
                moduleName: 'Create Project',
                placement: 'left',
                padding: 8,
                content: '<p>Check the information that you log from the project creation fields.</p>'
            }
        ],
        'projects.show': [
            {
                target: '[data-tour="project-overview-card"]',
                title: 'Overview',
                moduleName: 'Project View',
                placement: 'bottom',
                padding: 8,
                content: '<p>Information about your project which includes client, project code, warranty period, and core details.</p>'
            },
            {
                target: '[data-tour="overview-client"]',
                title: 'Client',
                moduleName: 'Project View',
                placement: 'bottom',
                padding: 6,
                content: '<p>The client or organization for whom this project is being undertaken.</p>'
            },
            {
                target: '[data-tour="overview-project-code"]',
                title: 'Project Code',
                moduleName: 'Project View',
                placement: 'bottom',
                padding: 6,
                content: '<p>Unique code for your project to find your project much faster.</p>'
            },
            {
                target: '[data-tour="overview-warranty"]',
                title: 'Warranty Period',
                moduleName: 'Project View',
                placement: 'bottom',
                padding: 6,
                content: '<p>Indicates warranty inclusion and remaining days.</p>'
            },
            {
                target: '[data-tour="project-financials-card"]',
                title: 'Financials & Budget',
                moduleName: 'Project View',
                placement: 'bottom',
                padding: 8,
                content: '<p>Track your budget for the project.</p>'
            },
            {
                target: '[data-tour="financial-allocated-budget"]',
                title: 'Allocated Budget',
                moduleName: 'Project View',
                placement: 'bottom',
                padding: 6,
                content: '<p>Your project budget.</p>'
            },
            {
                target: '[data-tour="financial-actual-spend"]',
                title: 'Actual Spend',
                moduleName: 'Project View',
                placement: 'bottom',
                padding: 6,
                content: '<p>Total expense of the project.</p>'
            },
            {
                target: '[data-tour="financial-remaining-budget"]',
                title: 'Remaining',
                moduleName: 'Project View',
                placement: 'bottom',
                padding: 6,
                content: '<p>Your remaining budget or possible profit without tax.</p>'
            },
            {
                target: '[data-tour="project-files-card"]',
                title: 'Project Files & Documents',
                moduleName: 'Project View',
                placement: 'bottom',
                padding: 8,
                content: '<p>Attach related documents for your project.</p>'
            },
            {
                target: '[data-tour="project-files-upload"]',
                title: 'Upload File',
                moduleName: 'Project View',
                placement: 'bottom',
                padding: 6,
                content: '<p>Attach a file and organize them base on their category.</p>'
            },
            {
                target: '[data-tour="project-files-list"]',
                title: 'File List',
                moduleName: 'Project View',
                placement: 'top',
                padding: 8,
                content: '<p>Your attached files will go here.</p>'
            },
            {
                target: '[data-tour="elevator-specs-card"]',
                title: 'Elevator Technical Specifications',
                moduleName: 'Project View',
                placement: 'bottom',
                padding: 8,
                content: '<p>The specification of the elevator that we are going to build.</p>'
            },
            {
                target: '[data-tour="project-timeline-card"]',
                title: 'Project Progress Timeline',
                moduleName: 'Project View',
                placement: 'bottom',
                padding: 8,
                content: '<p>Track your project progress here.</p>'
            },
            {
                target: '[data-tour="timeline-major-task-first"]',
                title: 'Major Task / Week 1',
                moduleName: 'Project View',
                placement: 'top',
                padding: 8,
                skipIfMissing: true,
                content: '<p>Your major task for the project which has a start and end date typically by weekly, inside this are your specific tasks for the following week.</p>'
            },
            {
                target: '[data-tour="timeline-add-major-task"]',
                title: 'Add Major Task',
                moduleName: 'Project View',
                placement: 'bottom',
                padding: 6,
                content: '<p>Add major task or weekly task to track and organize your project progress.</p>'
            },
            {
                target: '[data-tour="timeline-specific-tasks"]',
                title: 'Specific Tasks',
                moduleName: 'Project View',
                placement: 'top',
                padding: 8,
                skipIfMissing: true,
                content: '<p>Specific tasks are tasks that is inside your major task and these tasks has target date to be accomiplish.</p>'
            },
            {
                target: '[data-tour="project-expenditures-card"]',
                title: 'Expenditures',
                moduleName: 'Project View',
                placement: 'bottom',
                padding: 8,
                content: '<p>Log your expenses for the current project that you are viewing.</p>'
            },
            {
                target: '[data-tour="assigned-manpower-card"]',
                title: 'Assigned Manpower',
                moduleName: 'Project View',
                placement: 'bottom',
                padding: 8,
                content: '<p>Track the personnel that are curently assigned to your project, you can modify the list by assigning or unassigning.</p>'
            },
            {
                target: '[data-tour="audit-trail-card"]',
                title: 'Audit Trail & Status History',
                moduleName: 'Project View',
                placement: 'top',
                padding: 8,
                content: '<p>Track the changes of your project for auditing and accountability.</p>'
            }
        ],
        'projects': [
            {
                target: '[data-tour-nav="projects"]',
                title: 'Project Module',
                moduleName: 'Projects',
                placement: 'right',
                padding: 4,
                content: '<p><strong>Project module</strong> — Where you can view your projects and manage them. Track statuses, timeline schedules, and project deliverables.</p>'
            },
            {
                target: '.page-header, .page-header-container, main',
                title: 'Project Actions & Filters',
                moduleName: 'Projects',
                placement: 'bottom',
                padding: 8,
                content: '<p>Filter projects by status (Ongoing, Pending, Completed), search by project title or client code, and create new projects.</p>'
            },
            {
                target: '.projects-table, .table-responsive, .projects-list, main',
                title: 'Project Portfolio List',
                moduleName: 'Projects',
                placement: 'top',
                padding: 8,
                content: '<p>View comprehensive project details, completion percentages, manager assignments, and click into any project for complete task and milestone tracking.</p>'
            }
        ],
        'costing': [
            {
                target: '[data-tour="costing-kpi-allocated"]',
                title: 'Total Allocated Contract Value',
                moduleName: 'Costing',
                placement: 'bottom',
                padding: 8,
                content: '<p>Represents the combined contract baseline value across all active and registered projects in your portfolio, along with the total project count.</p>'
            },
            {
                target: '[data-tour="costing-kpi-spend"]',
                title: 'Total Actual Spend',
                moduleName: 'Costing',
                placement: 'bottom',
                padding: 8,
                content: '<p>Aggregates all cumulative expenses recorded for materials, labor, and operations across every project, displaying the overall portfolio spend percentage.</p>'
            },
            {
                target: '[data-tour="costing-kpi-remaining"]',
                title: 'Total Remaining Budget',
                moduleName: 'Costing',
                placement: 'bottom',
                padding: 8,
                content: '<p>Shows the net available capital remaining across your portfolio before reaching contract limits, along with the percentage of total budget still unspent.</p>'
            },
            {
                target: '[data-tour="costing-ledger-card"]',
                title: 'Project Budget & Costing Ledger',
                moduleName: 'Costing',
                placement: 'top',
                padding: 8,
                content: '<p>Centralized financial directory of all registered projects. Use the search bar and status filter to quickly locate specific projects and inspect their itemized expense breakdowns.</p>'
            },
            {
                target: '[data-tour="costing-col-allocated"]',
                title: 'Allocated Budget',
                moduleName: 'Costing',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Displays the total contracted budget baseline assigned to each individual project upon initialization.</p>'
            },
            {
                target: '[data-tour="costing-col-spend"]',
                title: 'Actual Spend',
                moduleName: 'Costing',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Shows the cumulative approved expenditures logged for materials, labor, equipment, and operations on that specific project.</p>'
            },
            {
                target: '[data-tour="costing-col-remaining"]',
                title: 'Remaining Budget',
                moduleName: 'Costing',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Indicates the net available balance (Allocated Budget minus Actual Spend), helping you track remaining funds or projected gross margin before reaching contract limits.</p>'
            },
            {
                target: '[data-tour="costing-col-utilization"]',
                title: 'Budget Utilization Percentage',
                moduleName: 'Costing',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Visualizes the proportion of the allocated budget consumed so far with color-coded health indicators (Healthy, High Spend at 85%+, or Over Budget above 100%).</p>'
            }
        ],
        'costing.show': [
            {
                target: '[data-tour="costing-show-financial-overview"]',
                title: 'Financial Overview',
                moduleName: 'Costing Breakdown',
                placement: 'bottom',
                padding: 8,
                content: '<p>Summarizes the overall financial health and budget utilization rate for this specific project.</p>'
            },
            {
                target: '[data-tour="costing-show-allocated-budget"]',
                title: 'Allocated Budget',
                moduleName: 'Costing Breakdown',
                placement: 'bottom',
                padding: 6,
                content: '<p>Displays the total contract budget allocated to this project.</p>'
            },
            {
                target: '[data-tour="costing-show-actual-spend"]',
                title: 'Total Actual Spend',
                moduleName: 'Costing Breakdown',
                placement: 'bottom',
                padding: 6,
                content: '<p>Shows the cumulative approved expenditures logged across all cost categories for this project.</p>'
            },
            {
                target: '[data-tour="costing-show-remaining-budget"]',
                title: 'Remaining Budget',
                moduleName: 'Costing Breakdown',
                placement: 'bottom',
                padding: 6,
                content: '<p>Indicates the remaining unspent budget balance (or deficit if expenditures exceed the allocated contract price).</p>'
            },
            {
                target: '[data-tour="costing-show-category-spending"]',
                title: 'Spending by Category',
                moduleName: 'Costing Breakdown',
                placement: 'bottom',
                padding: 8,
                content: '<p>Breaks down total project expenditures across key cost categories — <strong>Materials</strong>, <strong>Equipment Rental</strong>, <strong>Labor / Overtime</strong>, <strong>Subcontractor</strong>, and <strong>Other / Misc</strong> — showing item counts and percentage share.</p>'
            },
            {
                target: '[data-tour="costing-show-ledger-card"]',
                title: 'Itemized Expense Ledger',
                moduleName: 'Costing Breakdown',
                placement: 'top',
                padding: 8,
                content: '<p>Complete chronological register of all approved project expenditures and any pending staff expense submissions awaiting review.</p>'
            },
            {
                target: '[data-tour="costing-show-col-description"]',
                title: 'Description (What this is for)',
                moduleName: 'Costing Breakdown',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Details the specific purpose, material item, or service covered by the expense entry.</p>'
            },
            {
                target: '[data-tour="costing-show-col-category"]',
                title: 'Category',
                moduleName: 'Costing Breakdown',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Identifies the cost classification assigned to the expense (Materials, Equipment, Labor, Subcontractor, or Other).</p>'
            },
            {
                target: '[data-tour="costing-show-col-qty"]',
                title: 'Qty',
                moduleName: 'Costing Breakdown',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Shows the quantity of units purchased or utilized for the logged expenditure.</p>'
            },
            {
                target: '[data-tour="costing-show-col-amount"]',
                title: 'Amount (PHP)',
                moduleName: 'Costing Breakdown',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Displays the total monetary cost of the line item in Philippine Pesos (₱).</p>'
            },
            {
                target: '[data-tour="costing-show-col-date"]',
                title: 'Incurred Date',
                moduleName: 'Costing Breakdown',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Records the date when the expense was incurred or logged into the project ledger.</p>'
            },
            {
                target: '[data-tour="costing-show-col-logged-by"]',
                title: 'Logged By',
                moduleName: 'Costing Breakdown',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Identifies the user (Admin, Manager, or Staff member) who recorded the expenditure.</p>'
            },
            {
                target: '[data-tour="costing-show-col-actions"]',
                title: 'Actions',
                moduleName: 'Costing Breakdown',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Allows authorized users to review pending staff expense submissions or delete erroneous cost entries.</p>'
            },
            {
                target: '[data-tour="costing-show-log-expense-btn"]',
                title: 'Log New Expense / Cost',
                moduleName: 'Costing Breakdown',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Click this button to record a new project expenditure — either by selecting an item directly from the Materials Catalog or by entering a custom expense.</p>'
            }
        ],
        'pending-actions': [
            {
                target: '[data-tour="staff-req-kpi-total"]',
                title: 'Total Requests',
                moduleName: 'Staff Requests',
                placement: 'bottom',
                padding: 8,
                beforeEnter: function() { switchStaffRequestsTab('list'); },
                content: '<p>Displays the cumulative number of staff submissions across all active projects and the total number of contributing staff members.</p>'
            },
            {
                target: '[data-tour="staff-req-kpi-unexamined"]',
                title: 'Unexamined Requests',
                moduleName: 'Staff Requests',
                placement: 'bottom',
                padding: 8,
                beforeEnter: function() { switchStaffRequestsTab('list'); },
                content: '<p>Shows the queue of pending requests awaiting Admin or Manager verification. Click this card anytime to filter the list to unexamined items.</p>'
            },
            {
                target: '[data-tour="staff-req-kpi-approved"]',
                title: 'Approved Requests',
                moduleName: 'Staff Requests',
                placement: 'bottom',
                padding: 8,
                beforeEnter: function() { switchStaffRequestsTab('list'); },
                content: '<p>Tracks all verified staff submissions that have been approved and applied to project timelines, budgets, and document ledgers.</p>'
            },
            {
                target: '[data-tour="staff-req-kpi-rejected"]',
                title: 'Rejected Requests',
                moduleName: 'Staff Requests',
                placement: 'bottom',
                padding: 8,
                beforeEnter: function() { switchStaffRequestsTab('list'); },
                content: '<p>Counts declined submissions that were returned to staff members with reviewer remarks and feedback.</p>'
            },
            {
                target: '[data-tour="staff-req-tab-approvals"]',
                title: 'Request Approvals Tab',
                moduleName: 'Staff Requests',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchStaffRequestsTab('list'); },
                content: '<p>Where you can see all of the requests that were submitted by staff and need to be reviewed by you (Admin/Manager).</p>'
            },
            {
                target: '[data-tour="staff-req-tab-staff"]',
                title: 'View from Staff List Tab',
                moduleName: 'Staff Requests',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchStaffRequestsTab('list'); },
                content: '<p>Switch to a list of requests organized based off your staff directory to inspect submission activity per staff member.</p>'
            },
            {
                target: '[data-tour="staff-req-col-details"]',
                title: 'Request Details',
                moduleName: 'Staff Requests',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchStaffRequestsTab('list'); },
                content: '<p>See what type of request was submitted by staff — such as a <strong>Timeline Task Completion</strong>, <strong>Expense Entry</strong>, or <strong>File Upload</strong> — along with a summary of the submission.</p>'
            },
            {
                target: '[data-tour="staff-req-col-project"]',
                title: 'Project',
                moduleName: 'Staff Requests',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchStaffRequestsTab('list'); },
                content: '<p>Shows the associated project code and project name where the staff request will be applied once approved.</p>'
            },
            {
                target: '[data-tour="staff-req-col-status"]',
                title: 'Status',
                moduleName: 'Staff Requests',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchStaffRequestsTab('list'); },
                content: '<p>Indicates the current review status of the request (<strong>Unexamined</strong>, <strong>Approved</strong>, or <strong>Rejected</strong>) and who reviewed it.</p>'
            },
            {
                target: '[data-tour="staff-req-col-actions"]',
                title: 'Actions (Request Approvals)',
                moduleName: 'Staff Requests',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchStaffRequestsTab('list'); },
                content: '<p>Click <strong>Verdict</strong> on pending requests to inspect proof attachments and approve or reject with mandatory notes, or click <strong>Review</strong> on processed items.</p>'
            },
            {
                target: '[data-tour="staff-dir-col-member"]',
                title: 'Staff Member (Staff Directory)',
                moduleName: 'Staff Requests',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchStaffRequestsTab('staff'); },
                content: '<p>In the <strong>Staff Directory &amp; Request Summary</strong> tab, this column lists each staff member along with their avatar initials and email address.</p>'
            },
            {
                target: '[data-tour="staff-dir-col-breakdown"]',
                title: 'Request Status Breakdown',
                moduleName: 'Staff Requests',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchStaffRequestsTab('staff'); },
                content: '<p>Provides an instant breakdown of each staff member\'s <strong>Unexamined</strong>, <strong>Approved</strong>, and <strong>Rejected</strong> request counts.</p>'
            },
            {
                target: '[data-tour="staff-dir-col-actions"]',
                title: 'Actions (Staff Directory)',
                moduleName: 'Staff Requests',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchStaffRequestsTab('staff'); },
                content: '<p>Click <strong>View Requests</strong> to open a dedicated modal listing all requests submitted by that specific staff member for focused review.</p>'
            }
        ],
        'approval-list': [
            {
                target: '[data-tour-nav="approval-list"]',
                title: 'Approval List Module',
                moduleName: 'Approval List',
                placement: 'right',
                padding: 4,
                content: '<p><strong>Approval List module</strong> — Track the review status of your submitted project requests and milestone progression updates.</p>'
            }
        ],
        'resources': [
            {
                target: '[data-tour="materials-searchbar"]',
                title: 'Materials Catalog Searchbar',
                moduleName: 'Materials Catalog',
                placement: 'bottom',
                padding: 8,
                content: '<p>Quickly search and filter your catalog inventory by item code, material name, model, or technical specifications.</p>'
            },
            {
                target: '[data-tour="materials-col-code"]',
                title: 'Item Code',
                moduleName: 'Materials Catalog',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Unique reference code assigned to each elevator part or material for fast identification and inventory tracking.</p>'
            },
            {
                target: '[data-tour="materials-col-name"]',
                title: 'Item Name & Description',
                moduleName: 'Materials Catalog',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Displays the primary name of the component along with a brief description of its function or category.</p>'
            },
            {
                target: '[data-tour="materials-col-specs"]',
                title: 'Model / Specs',
                moduleName: 'Materials Catalog',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Specifies the technical model, dimensions, rating, or engineering specifications required for compatibility.</p>'
            },
            {
                target: '[data-tour="materials-col-qty"]',
                title: 'QTY (Available Stock)',
                moduleName: 'Materials Catalog',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Tracks current quantity on hand in central stock, with automatic warning indicators when stock drops to low-stock or out-of-stock levels.</p>'
            },
            {
                target: '[data-tour="materials-col-price"]',
                title: 'Price (DEX Selling Price)',
                moduleName: 'Materials Catalog',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Represents the DEX selling price per unit used when quoting or billing materials to client projects.</p>'
            },
            {
                target: '[data-tour="materials-col-unit-cost"]',
                title: 'Unit Cost (Procurement / Supplier Price)',
                moduleName: 'Materials Catalog',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Records the procurement or supplier acquisition cost per unit to help monitor margins and actual supply expenses.</p>'
            },
            {
                target: '[data-tour="materials-col-demand"]',
                title: 'Demand Status',
                moduleName: 'Materials Catalog',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Classifies items as <strong>Fast-moving</strong> or <strong>Slow-moving</strong> based on project usage frequency over the last 3 months.</p>'
            },
            {
                target: '[data-tour="materials-col-configure"]',
                title: 'Configure & Actions',
                moduleName: 'Materials Catalog',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Click <strong>Configure</strong> on any row to update stock quantities, adjust selling or supplier pricing, edit specifications, or remove an entry.</p>'
            },
            {
                target: '[data-tour="materials-add-btn"]',
                title: 'Add Material',
                moduleName: 'Materials Catalog',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Click this button to register a new elevator part or material into the central catalog with its initial stock, specifications, and pricing.</p>'
            }
        ],
        'scheduling': [
            {
                target: '[data-tour="manpower-col-emp-id"]',
                title: 'Employee ID',
                moduleName: 'Manpower Management',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Unique employee identification number assigned to each registered field worker or technical staff member.</p>'
            },
            {
                target: '[data-tour="manpower-col-name"]',
                title: 'Full Name',
                moduleName: 'Manpower Management',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Displays the full registered name of the personnel in your active manpower roster.</p>'
            },
            {
                target: '[data-tour="manpower-col-role"]',
                title: 'Role',
                moduleName: 'Manpower Management',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Indicates the personnel\'s trade specialization or technical expertise (such as Project Engineer, Safety Officer, or Installation Personnel).</p>'
            },
            {
                target: '[data-tour="manpower-col-project"]',
                title: 'Assigned Project',
                moduleName: 'Manpower Management',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Shows the current project code and project name where the personnel is deployed, or a dash if they are currently unassigned.</p>'
            },
            {
                target: '[data-tour="manpower-col-status"]',
                title: 'Status',
                moduleName: 'Manpower Management',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Reflects real-time deployment availability — whether the personnel is currently <strong>Assigned</strong> to an active project or <strong>Not Assigned</strong> and available for deployment.</p>'
            },
            {
                target: '[data-tour="manpower-col-date"]',
                title: 'Date Assigned',
                moduleName: 'Manpower Management',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Records the exact date when the personnel was deployed to their current project assignment.</p>'
            },
            {
                target: '[data-tour="manpower-col-actions"]',
                title: 'Actions',
                moduleName: 'Manpower Management',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Use <strong>Reassign</strong> to deploy or unassign a worker to a different project, or click <strong>Configure</strong> to edit their personal details, role, and profile status.</p>'
            },
            {
                target: '[data-tour="manpower-add-btn"]',
                title: 'Add New Personnel',
                moduleName: 'Manpower Management',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                content: '<p>Click this button to register new field personnel into your workforce roster and optionally assign them directly to an active project.</p>'
            }
        ],
        'reports': [
            {
                target: '[data-tour="reports-kpi-projects"]',
                title: 'Projects Register KPI',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 8,
                beforeEnter: function() { switchReportsTab('projects'); },
                content: '<p>Summarizes total registered projects across ongoing, completed, and pending statuses. Click this card anytime to jump directly to Project Data Export.</p>'
            },
            {
                target: '[data-tour="reports-kpi-contract"]',
                title: 'Contract Portfolio KPI (Without Taxes)',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 8,
                beforeEnter: function() { switchReportsTab('projects'); },
                content: '<p>Displays total portfolio contract value, cumulative actual spend, remaining budget (without taxes), and overall budget utilization percentage.</p>'
            },
            {
                target: '[data-tour="reports-kpi-materials"]',
                title: 'Materials Catalog KPI',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 8,
                beforeEnter: function() { switchReportsTab('projects'); },
                content: '<p>Shows total catalog materials, cumulative units in stock, and the breakdown between fast-moving and slow-moving inventory items.</p>'
            },
            {
                target: '[data-tour="reports-kpi-manpower"]',
                title: 'Field Manpower KPI',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 8,
                beforeEnter: function() { switchReportsTab('projects'); },
                content: '<p>Tracks active field personnel, currently deployed workers on site, available pool count, and overall workforce deployment percentage.</p>'
            },
            {
                target: '[data-tour="reports-tab-projects"]',
                title: 'Project Data Export Tab',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchReportsTab('projects'); },
                content: '<p>Compile and export comprehensive A4 PDF project dossiers for any active or archived project in your portfolio.</p>'
            },
            {
                target: '[data-tour="reports-customize-template-card"]',
                title: 'Customize Project Data Template',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 8,
                beforeEnter: function() { switchReportsTab('projects'); },
                content: '<p>Configure which project sections are included in your exported PDF report and quickly generate a dossier for a specific project.</p>'
            },
            {
                target: '[data-tour="reports-template-checkboxes"]',
                title: 'Customize Exported Project Data Sections',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchReportsTab('projects'); },
                content: '<p>Toggle these checkboxes to customize which sections — <strong>Project Overview</strong>, <strong>Elevator Technical Specs</strong>, <strong>Financials &amp; Budget</strong>, <strong>Expenditures</strong>, and <strong>Assigned Manpower</strong> — are included in your exported project PDF.</p>'
            },
            {
                target: '[data-tour="reports-generate-template-dropdown"]',
                title: 'Generate Customized Project Data Template',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchReportsTab('projects'); },
                content: '<p>Select a project directly from this dropdown to immediately <strong>Download PDF</strong> or open a live <strong>Preview</strong> using your customized template settings.</p>'
            },
            {
                target: '[data-tour="reports-projects-list-card"]',
                title: 'Select Project to Export as PDF',
                moduleName: 'Reports',
                placement: 'top',
                padding: 8,
                beforeEnter: function() { switchReportsTab('projects'); },
                content: '<p>Browse, search, and filter your full project directory by scope or status, and click <strong>Preview</strong> or <strong>Export PDF</strong> on any individual project row.</p>'
            },
            {
                target: '[data-tour="reports-tab-revenue"]',
                title: 'Revenue Data Export Tab',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchReportsTab('revenue'); },
                content: '<p>Switch to the Revenue Data Export tab to analyze and export portfolio revenue performance, contract values, and remaining budgets (without taxes).</p>'
            },
            {
                target: '[data-tour="reports-revenue-section"]',
                title: 'Revenue Status & Financial Performance (Without Taxes)',
                moduleName: 'Reports',
                placement: 'top',
                padding: 8,
                beforeEnter: function() { switchReportsTab('revenue'); },
                content: '<p>Compare project contract prices (without taxes), actual spends, and total remaining budgets across months or years with interactive visual analytics.</p>'
            },
            {
                target: '[data-tour="reports-revenue-btn-monthly"]',
                title: 'Monthly Comparison Button',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchReportsTab('revenue'); },
                content: '<p>Analyze financial performance month-by-month within a selected year or custom month/date range.</p>'
            },
            {
                target: '[data-tour="reports-revenue-btn-yearly"]',
                title: 'Yearly Comparison Button',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchReportsTab('revenue'); },
                content: '<p>Switch to a multi-year comparison view to evaluate annual contract values, expenditures, and net revenue trends across years.</p>'
            },
            {
                target: '[data-tour="reports-rev-card-contract"]',
                title: 'Total Contract Value',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchReportsTab('revenue'); },
                content: '<p>Displays the combined contract value across all projects matching your active period and status filters.</p>'
            },
            {
                target: '[data-tour="reports-rev-card-spends"]',
                title: 'Total Actual Spends',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchReportsTab('revenue'); },
                content: '<p>Shows the cumulative actual expenditures recorded across the filtered projects along with the percentage of contract budget spent.</p>'
            },
            {
                target: '[data-tour="reports-rev-card-remaining"]',
                title: 'Total Revenue / Remaining Budget (Without Tax)',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchReportsTab('revenue'); },
                content: '<p>Calculates the net revenue balance / remaining unspent budget (without taxes) for the selected period.</p>'
            },
            {
                target: '[data-tour="reports-rev-card-comparison"]',
                title: 'Period Comparison',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchReportsTab('revenue'); },
                content: '<p>Compares current period revenue performance against the previous baseline period (e.g., vs. previous year) with variance amount and percentage change.</p>'
            },
            {
                target: '[data-tour="reports-revenue-chart"]',
                title: 'Financial Performance Graphical Chart',
                moduleName: 'Reports',
                placement: 'top',
                padding: 8,
                beforeEnter: function() { switchReportsTab('revenue'); },
                content: '<p>Visualizes <strong>Total Contract Value</strong>, <strong>Actual Spends</strong>, and <strong>Total Revenue / Remaining Budget</strong> side-by-side per month or year. Click any period bar to inspect its summary.</p>'
            },
            {
                target: '[data-tour="reports-revenue-pdf-btn"]',
                title: 'Generate Revenue PDF Button',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchReportsTab('revenue'); },
                content: '<p>Click this button to generate and download an official PDF report of the revenue status and project-by-project financial breakdown (without taxes).</p>'
            },
            {
                target: '[data-tour="reports-tab-materials"]',
                title: 'Materials List Export Tab',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchReportsTab('materials'); },
                content: '<p>Switch to the Materials List Export tab to filter and export catalog inventory and demand movement reports.</p>'
            },
            {
                target: '[data-tour="reports-materials-list-card"]',
                title: 'Materials Catalog Selection & Movement List',
                moduleName: 'Reports',
                placement: 'top',
                padding: 8,
                beforeEnter: function() { switchReportsTab('materials'); },
                content: '<p>Review catalog items, filter by Fast-Moving or Slow-Moving demand status or stock level, and tick checkboxes to select specific materials for export.</p>'
            },
            {
                target: '[data-tour="reports-materials-pdf-btn"]',
                title: 'Export Selected to PDF (Materials)',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchReportsTab('materials'); },
                content: '<p>Click this button to download a formatted PDF inventory report containing your selected or filtered catalog materials.</p>'
            },
            {
                target: '[data-tour="reports-tab-manpower"]',
                title: 'Manpower List Export Tab',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchReportsTab('manpower'); },
                content: '<p>Switch to the Manpower List Export tab to generate workforce deployment and expertise roster reports.</p>'
            },
            {
                target: '[data-tour="reports-manpower-list-card"]',
                title: 'Manpower Roster Selection & Expertise List',
                moduleName: 'Reports',
                placement: 'top',
                padding: 8,
                beforeEnter: function() { switchReportsTab('manpower'); },
                content: '<p>Filter personnel by expertise role or deployment status, optionally include recent project history, and select specific workers to include in your report.</p>'
            },
            {
                target: '[data-tour="reports-manpower-pdf-btn"]',
                title: 'Export Selected to PDF (Manpower)',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchReportsTab('manpower'); },
                content: '<p>Click this button to export your selected or filtered manpower roster and deployment history as an official PDF document.</p>'
            }
        ],
        'decision-support': [
            {
                target: '[data-tour="dss-tab-simulator"]',
                title: 'What-If Simulator Tab',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchDssTourView('simulator', 'feasibility'); },
                content: '<p>Run in-memory what-if simulations to evaluate project feasibility, schedule delay impacts, and supplier procurement trade-offs without modifying live records.</p>'
            },
            {
                target: '[data-tour="dss-sim-tab-feasibility"]',
                title: 'Project Feasibility Tab',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchDssTourView('simulator', 'feasibility'); },
                content: '<p>Assess whether your current workforce and catalog inventory can support taking on a proposed new project.</p>'
            },
            {
                target: '[data-tour="dss-feasibility-section"]',
                title: 'Project Feasibility Simulator',
                moduleName: 'Decision Support',
                placement: 'top',
                padding: 8,
                beforeEnter: function() { switchDssTourView('simulator', 'feasibility'); },
                content: '<p>Enter a proposed project budget, configure required manpower roles and catalog materials, and immediately view a color-coded feasibility verdict.</p>'
            },
            {
                target: '[data-tour="dss-feas-roles"]',
                title: 'Required Manpower Roles',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchDssTourView('simulator', 'feasibility'); },
                content: '<p>Specify the trade roles and headcount needed for the proposed project to test against your available and releasable personnel pool.</p>'
            },
            {
                target: '[data-tour="dss-feas-materials"]',
                title: 'Required Catalog Materials',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchDssTourView('simulator', 'feasibility'); },
                content: '<p>Add the catalog materials and quantities required for the project to verify stock sufficiency and estimated material cost.</p>'
            },
            {
                target: '[data-tour="dss-feas-manpower-chart"]',
                title: 'Manpower Role Readiness Chart',
                moduleName: 'Decision Support',
                placement: 'top',
                padding: 8,
                beforeEnter: function() { switchDssTourView('simulator', 'feasibility'); },
                content: '<p>Visualizes available and releasable headcount versus required headcount for each role, highlighting any staffing deficits.</p>'
            },
            {
                target: '[data-tour="dss-feas-materials-chart"]',
                title: 'Materials Stock & Budget Allocation Chart',
                moduleName: 'Decision Support',
                placement: 'top',
                padding: 8,
                beforeEnter: function() { switchDssTourView('simulator', 'feasibility'); },
                content: '<p>Compares required material quantities against current catalog stock, recommends reorder quantities for shortages, and calculates your remaining budget margin.</p>'
            },
            {
                target: '[data-tour="dss-sim-tab-delay"]',
                title: 'Project Delay Simulation Tab',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchDssTourView('simulator', 'delay'); },
                content: '<p>Switch to the Project Delay Simulation view to model timeline slips and detect schedule collisions across your portfolio.</p>'
            },
            {
                target: '[data-tour="dss-delay-control-card"]',
                title: 'Project Delay Simulation Section',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 8,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('simulator', 'delay'); },
                content: '<p>Configure which active project is experiencing a schedule delay and simulate how the extension shifts completion dates and locks assigned crew.</p>'
            },
            {
                target: '[data-tour="dss-delay-project-select"]',
                title: 'Target Project to Delay Dropdown',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('simulator', 'delay'); },
                content: '<p>Choose the target project whose timeline you want to test for schedule slippage.</p>'
            },
            {
                target: '[data-tour="dss-delay-slider"]',
                title: 'Simulated Delay Duration Control Slider',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('simulator', 'delay'); },
                content: '<p>Drag the slider or enter the exact number of slip days (or use the quick preset buttons) to simulate the delay duration in real time.</p>'
            },
            {
                target: '[data-tour="dss-delay-calendar-card"]',
                title: 'Portfolio Schedule Calendar',
                moduleName: 'Decision Support',
                placement: 'top',
                padding: 8,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('simulator', 'delay'); },
                content: '<p>Interactive Gantt-style portfolio timeline displaying scheduled project durations, simulated delay extensions, and downstream overlap conflicts.</p>'
            },
            {
                target: '[data-tour="dss-delay-col-project"]',
                title: 'Project Column',
                moduleName: 'Decision Support',
                placement: 'right',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('simulator', 'delay'); },
                content: '<p>Lists every project in the timeline along with its color indicator, status badge, and scheduled date window. Click any row to simulate a delay on that project.</p>'
            },
            {
                target: '[data-tour="dss-delay-legend"]',
                title: 'Project Color Legend & Calendar Key',
                moduleName: 'Decision Support',
                placement: 'top',
                padding: 8,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('simulator', 'delay'); },
                content: '<p>Explains the visual timeline bars — <strong>Scheduled Duration</strong>, <strong>Simulated Delay (+X days)</strong>, <strong>Run-Over Overlap Zone</strong>, and <strong>Today</strong> marker — and lets you quickly select projects by color chip.</p>'
            },
            {
                target: '[data-tour="dss-delay-runover-card"]',
                title: 'Projects Run Over by Schedule Slip',
                moduleName: 'Decision Support',
                placement: 'top',
                padding: 8,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('simulator', 'delay'); },
                content: '<p>Identifies downstream projects whose schedules overlap with the simulated delay window and shows the exact number of conflicting overlap days.</p>'
            },
            {
                target: '[data-tour="dss-delay-held-crew-card"]',
                title: 'Held Field Personnel',
                moduleName: 'Decision Support',
                placement: 'top',
                padding: 8,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('simulator', 'delay'); },
                content: '<p>Lists all field personnel currently assigned to the delayed project who will remain locked until the new extended target completion date.</p>'
            },
            {
                target: '[data-tour="dss-sim-tab-supplier"]',
                title: 'Supplier Procurement Trade-Off Tab',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchDssTourView('simulator', 'supplier'); },
                content: '<p>Switch to the Supplier Procurement Trade-Off simulator to compare supplier unit costs, shipping lead times, and payment terms side-by-side.</p>'
            },
            {
                target: '[data-tour="dss-supplier-canvas"]',
                title: 'Drag and Drop Playground for Profiles',
                moduleName: 'Decision Support',
                placement: 'top',
                padding: 8,
                beforeEnter: function() { switchDssTourView('simulator', 'supplier'); },
                content: '<p>Interactive node canvas where you can drag supplier profile cards and connect profiles that supply the same material to compare price and delivery lead time.</p>'
            },
            {
                target: '[data-tour="dss-supplier-btn-add"]',
                title: 'Add Profile Button',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchDssTourView('simulator', 'supplier'); },
                content: '<p>Places a new blank supplier profile card directly onto the canvas for quick ad-hoc comparison.</p>'
            },
            {
                target: '[data-tour="dss-supplier-btn-choose"]',
                title: 'Choose From Existing Profiles Button',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchDssTourView('simulator', 'supplier'); },
                content: '<p>Opens your saved supplier library and catalog material baselines so you can add existing supplier profiles onto the comparison canvas.</p>'
            },
            {
                target: '[data-tour="dss-supplier-btn-create"]',
                title: 'Create Profile Button',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchDssTourView('simulator', 'supplier'); },
                content: '<p>Create and save a new supplier profile with its material item, unit cost, shipping lead days, and payment terms, and place it onto the canvas.</p>'
            },
            {
                target: '[data-tour="dss-supplier-result-card"]',
                title: 'Graphical View / Result Section',
                moduleName: 'Decision Support',
                placement: 'top',
                padding: 8,
                beforeEnter: function() { switchDssTourView('simulator', 'supplier'); },
                content: '<p>Displays the side-by-side comparison results for connected supplier profiles, highlighting the recommended supplier, cost savings, shipment lead-time difference, and latest order cutoff dates.</p>'
            },
            {
                target: '[data-tour="dss-tab-allocation"]',
                title: 'Generate Resource Allocation Tab',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('allocation'); },
                content: '<p>Switch to Generate Resource Allocation to build specification-driven manpower and material packages and allocate them directly to a target project.</p>'
            },
            {
                target: '[data-tour="dss-alloc-selector-card"]',
                title: 'Project Specification & Scenario Template Selector',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 8,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('allocation'); },
                content: '<p>Inspect the target project\'s elevator technical specifications, select or customize a scenario template, and validate pre-flight readiness.</p>'
            },
            {
                target: '[data-tour="dss-alloc-target-project"]',
                title: 'Target Project Dropdown',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('allocation'); },
                content: '<p>Select the active project that will receive the manpower and material resource allocation.</p>'
            },
            {
                target: '[data-tour="dss-alloc-template-select"]',
                title: 'Material & Manpower Scenario Template Dropdown',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('allocation'); },
                content: '<p>Choose an auto-derived specification template, a standard rise preset (Low-Rise, Mid-Rise, High-Rise), or one of your saved custom scenario templates.</p>'
            },
            {
                target: '[data-tour="dss-alloc-btn-match"]',
                title: 'Match Project Specs Button',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('allocation'); },
                content: '<p>Automatically recalculates recommended manpower roles and catalog material quantities based on the selected project\'s elevator specifications and missing core roles.</p>'
            },
            {
                target: '[data-tour="dss-alloc-btn-save-tpl"]',
                title: 'Save as Custom Template Button',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('allocation'); },
                content: '<p>Save your current combination of manpower roles and catalog materials as a reusable custom scenario template for future projects.</p>'
            },
            {
                target: '[data-tour="dss-alloc-manpower-card"]',
                title: 'Manpower Allocation Builder',
                moduleName: 'Decision Support',
                placement: 'top',
                padding: 8,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('allocation'); },
                content: '<p>Configure the manpower roles and headcount to deploy to the project, automatically ranking unassigned personnel by lowest historical workload.</p>'
            },
            {
                target: '[data-tour="dss-alloc-live-pool"]',
                title: 'Live Pool Availability & Auto-Matched Crew',
                moduleName: 'Decision Support',
                placement: 'top',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('allocation'); },
                content: '<p>Displays real-time unassigned pool availability per role and previews the exact personnel who will be auto-assigned to the project.</p>'
            },
            {
                target: '[data-tour="dss-alloc-material-card"]',
                title: 'Material Assignment Template Builder',
                moduleName: 'Decision Support',
                placement: 'top',
                padding: 8,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('allocation'); },
                content: '<p>Select the catalog materials and quantities to allocate, which automatically deducts central stock and logs itemized project expenditures upon submission.</p>'
            },
            {
                target: '[data-tour="dss-alloc-stock-sufficiency"]',
                title: 'Catalog Stock Sufficiency',
                moduleName: 'Decision Support',
                placement: 'top',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('allocation'); },
                content: '<p>Verifies that requested material quantities are available in central catalog stock and calculates the total template material cost.</p>'
            },
            {
                target: '[data-tour="dss-alloc-post-impact-card"]',
                title: 'Post-Allocation Project Budget Impact',
                moduleName: 'Decision Support',
                placement: 'top',
                padding: 8,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('allocation'); },
                content: '<p>Reviews pre-flight budget guardrails, showing how the allocation affects total project spend, utilization percentage, and remaining contract budget.</p>'
            },
            {
                target: '[data-tour="dss-alloc-submit-btn"]',
                title: 'Allocate Resource to Project Button',
                moduleName: 'Decision Support',
                placement: 'top',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('allocation'); },
                content: '<p>Commits the validated manpower assignments and material expenditures directly to the target project in a single atomic action.</p>'
            },
            {
                target: '[data-tour="dss-tab-kuya-dex"]',
                title: 'Kuya DEX Personal Assistant Tab',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchDssTourView('kuya_dex'); },
                content: '<p>Switch to <strong>Kuya DEX</strong>, your personal AI assistant for project inquiries, materials, costing, manpower, and decision support.</p>'
            },
            {
                target: '[data-tour="dss-kuya-dex-window"]',
                title: 'Kuya DEX Chat Window',
                moduleName: 'Decision Support',
                placement: 'top',
                padding: 8,
                beforeEnter: function() { switchDssTourView('kuya_dex'); },
                content: '<p>Interactive chat workspace where Kuya DEX answers questions about your projects, available workforce, expenditures, and strategic forecasts.</p>'
            },
            {
                target: '[data-tour="dss-kuya-dex-context"]',
                title: 'Project Context Dropdown',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                skipIfMissing: true,
                beforeEnter: function() { switchDssTourView('kuya_dex'); },
                content: '<p>Select which active project Kuya DEX should use as reference context when answering project-specific questions.</p>'
            },
            {
                target: '[data-tour="dss-kuya-dex-input"]',
                title: 'Message Text Box',
                moduleName: 'Decision Support',
                placement: 'top',
                padding: 6,
                beforeEnter: function() { switchDssTourView('kuya_dex'); },
                content: '<p>Type your question here — or click any of the suggested inquiry chips inside the chat stream — to ask Kuya DEX for insights.</p>'
            },
            {
                target: '[data-tour="dss-kuya-dex-send"]',
                title: 'Send Button',
                moduleName: 'Decision Support',
                placement: 'top',
                padding: 6,
                beforeEnter: function() { switchDssTourView('kuya_dex'); },
                content: '<p>Click <strong>Send</strong> (or press Enter) to submit your inquiry to Kuya DEX.</p>'
            },
            {
                target: '[data-tour="dss-kuya-dex-reset"]',
                title: 'Reset Chat Button',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 6,
                beforeEnter: function() { switchDssTourView('kuya_dex'); },
                content: '<p>Clears the current conversation history and resets the Kuya DEX chat session.</p>'
            }
        ],
        'account': [
            {
                target: '[data-tour-nav="account"]',
                title: 'User Management Module',
                moduleName: 'User Management',
                placement: 'right',
                padding: 4,
                content: '<p><strong>User management module</strong> — Manage who access your system.</p>'
            },
            {
                target: '.account-settings-container, .card, main',
                title: 'Security & Access Governance',
                moduleName: 'User Management',
                placement: 'bottom',
                padding: 8,
                content: '<p>Create and edit accounts, grant role permissions, unlock accounts, and manage two-factor authentication security.</p>'
            }
        ]
    };

    /**
     * Maximize specific tasks, expenditures, assigned manpower, and audit trail in project view
     */
    function maximizeProjectShowSections() {
        if (window.__projectDetailComponent) {
            previousProjectShowState = {
                checklistMinimized: window.__projectDetailComponent.checklistMinimized,
                expendituresMinimized: window.__projectDetailComponent.expendituresMinimized,
                manpowerMinimized: window.__projectDetailComponent.manpowerMinimized,
                auditTrailMinimized: window.__projectDetailComponent.auditTrailMinimized,
                activeTaskId: window.__projectDetailComponent.activeTaskId
            };

            // Maximize specific tasks, expenditures, assigned manpower, audit trail
            window.__projectDetailComponent.checklistMinimized = false;
            window.__projectDetailComponent.expendituresMinimized = false;
            window.__projectDetailComponent.manpowerMinimized = false;
            window.__projectDetailComponent.auditTrailMinimized = false;

            // Ensure first task is active if none selected
            if (!window.__projectDetailComponent.activeTaskId) {
                const firstCard = document.querySelector('[data-tour="timeline-major-task-first"]');
                if (firstCard && firstCard.id) {
                    const idMatch = firstCard.id.match(/\d+/);
                    if (idMatch) {
                        window.__projectDetailComponent.activeTaskId = parseInt(idMatch[0], 10);
                    }
                }
            }
        } else {
            // DOM button fallback
            previousProjectShowState = {
                expendituresWasMinimized: false,
                manpowerWasMinimized: false,
                auditTrailWasMinimized: false
            };

            const expBtn = document.getElementById('btn-toggle-expenditures');
            if (expBtn && expBtn.getAttribute('aria-expanded') === 'false') {
                previousProjectShowState.expendituresWasMinimized = true;
                expBtn.click();
            }

            const manBtn = document.getElementById('btn-toggle-manpower');
            if (manBtn && manBtn.getAttribute('aria-expanded') === 'false') {
                previousProjectShowState.manpowerWasMinimized = true;
                manBtn.click();
            }

            const auditBtn = document.getElementById('btn-toggle-audit-trail');
            if (auditBtn && auditBtn.getAttribute('aria-expanded') === 'false') {
                previousProjectShowState.auditTrailWasMinimized = true;
                auditBtn.click();
            }

            const firstCard = document.querySelector('[data-tour="timeline-major-task-first"]');
            if (firstCard) firstCard.click();
        }
    }

    /**
     * Restore previous collapsible states when tour closes or is skipped
     */
    function restoreProjectShowSections() {
        if (!previousProjectShowState) return;

        if (window.__projectDetailComponent) {
            window.__projectDetailComponent.checklistMinimized = previousProjectShowState.checklistMinimized;
            window.__projectDetailComponent.expendituresMinimized = previousProjectShowState.expendituresMinimized;
            window.__projectDetailComponent.manpowerMinimized = previousProjectShowState.manpowerMinimized;
            window.__projectDetailComponent.auditTrailMinimized = previousProjectShowState.auditTrailMinimized;
            if (previousProjectShowState.activeTaskId !== undefined) {
                window.__projectDetailComponent.activeTaskId = previousProjectShowState.activeTaskId;
            }
        } else {
            // DOM fallback
            if (previousProjectShowState.expendituresWasMinimized) {
                const expBtn = document.getElementById('btn-toggle-expenditures');
                if (expBtn && expBtn.getAttribute('aria-expanded') === 'true') expBtn.click();
            }
            if (previousProjectShowState.manpowerWasMinimized) {
                const manBtn = document.getElementById('btn-toggle-manpower');
                if (manBtn && manBtn.getAttribute('aria-expanded') === 'true') manBtn.click();
            }
            if (previousProjectShowState.auditTrailWasMinimized) {
                const auditBtn = document.getElementById('btn-toggle-audit-trail');
                if (auditBtn && auditBtn.getAttribute('aria-expanded') === 'true') auditBtn.click();
            }
        }

        previousProjectShowState = null;
    }

    /**
     * Switch tab in Staff Requests module ('list' = Request Approvals, 'staff' = View from Staff List)
     */
    function switchStaffRequestsTab(mode) {
        if (window.__staffRequestsComponent && typeof window.__staffRequestsComponent.switchView === 'function') {
            if (window.__staffRequestsComponent.viewMode !== mode) {
                window.__staffRequestsComponent.switchView(mode);
            }
        } else {
            const selector = mode === 'staff'
                ? '[data-tour="staff-req-tab-staff"]'
                : '[data-tour="staff-req-tab-approvals"]';
            const tabBtn = document.querySelector(selector);
            if (tabBtn) tabBtn.click();
        }
    }

    /**
     * Switch tab in Reports module ('projects', 'revenue', 'materials', 'manpower')
     */
    function switchReportsTab(tab) {
        if (window.__reportsModuleComponent) {
            if (window.__reportsModuleComponent.activeTab !== tab) {
                window.__reportsModuleComponent.activeTab = tab;
            }
        } else {
            const tabBtn = document.getElementById(`tab-btn-${tab}`);
            if (tabBtn) tabBtn.click();
        }
    }

    /**
     * Switch tab and optional simulator sub-mode in Decision Support module
     */
    function switchDssTourView(tab, simMode = null) {
        if (window.__dssModuleComponent) {
            if (window.__dssModuleComponent.activeTab !== tab) {
                if (typeof window.__dssModuleComponent.switchDssTab === 'function') {
                    window.__dssModuleComponent.switchDssTab(tab);
                } else {
                    window.__dssModuleComponent.activeTab = tab;
                }
            }
            if (simMode && window.__dssModuleComponent.simMode !== simMode) {
                window.__dssModuleComponent.simMode = simMode;
            }
        } else {
            const tabBtnMap = {
                'simulator': 'tab-btn-dss-simulator',
                'allocation': 'tab-btn-dss-allocation',
                'kuya_dex': 'tab-btn-dss-kuya-dex'
            };
            const tabBtn = document.getElementById(tabBtnMap[tab]);
            if (tabBtn) tabBtn.click();
            if (simMode) {
                const simBtn = document.getElementById(`btn-sim-mode-${simMode}`);
                if (simBtn) simBtn.click();
            }
        }
    }

    /**
     * Start the tour for a specific module
     */
    function startTour(moduleKey, isManual = false) {
        const key = moduleKey || currentModule;
        currentModuleKey = key;

        // In project view, expand collapsible sections (specific tasks, expenditures, manpower, audit logs)
        if (key === 'projects.show') {
            maximizeProjectShowSections();
        } else if (key === 'pending-actions') {
            switchStaffRequestsTab('list');
        } else if (key === 'reports') {
            switchReportsTab('projects');
        } else if (key === 'decision-support') {
            switchDssTourView('simulator', 'feasibility');
        }

        const candidateSteps = modulesConfig[key] || modulesConfig['dashboard'];

        // Filter valid steps that exist in current DOM
        activeSteps = candidateSteps.filter(step => {
            const el = document.querySelector(step.target);
            if (!el && step.skipIfMissing) return false;
            return true;
        });

        if (activeSteps.length === 0) return;

        active = true;
        currentStepIdx = 0;

        const container = document.getElementById('dex-tour-container');
        if (container) {
            container.style.display = 'block';
        }

        // Attach listeners
        window.addEventListener('wheel', onWheel, { passive: false });
        window.addEventListener('touchstart', onTouchStart, { passive: true });
        window.addEventListener('touchmove', onTouchMove, { passive: false });
        window.addEventListener('scroll', onScrollOrResize, { passive: true });
        window.addEventListener('resize', onScrollOrResize, { passive: true });
        window.addEventListener('keydown', onKeyDown);

        goToStep(0);
    }

    /**
     * Compute vertical scrolling bounds to restrict user from scrolling away from the highlighted section
     */
    function computeScrollBounds(el) {
        if (!el) {
            minAllowedScrollY = 0;
            maxAllowedScrollY = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
            return;
        }

        const rect = el.getBoundingClientRect();
        const docTop = rect.top + window.scrollY;
        const docBottom = docTop + rect.height;
        const viewH = window.innerHeight;
        const topBarOffset = 70; // 56px topbar + padding

        // Also check if popover is visible to include it in the focus region
        const popover = document.getElementById('dex-tour-popover');
        let focusTop = docTop;
        let focusBottom = docBottom;

        if (popover && popover.classList.contains('is-visible')) {
            const pRect = popover.getBoundingClientRect();
            const pTop = pRect.top + window.scrollY;
            const pBottom = pTop + pRect.height;
            focusTop = Math.min(docTop, pTop);
            focusBottom = Math.max(docBottom, pBottom);
        }

        const focusHeight = focusBottom - focusTop;
        const availableH = viewH - topBarOffset;

        if (focusHeight <= availableH) {
            // Section fits inside viewport: lock/restrict around centered position
            const idealY = Math.max(0, focusTop - topBarOffset - (availableH - focusHeight) / 2);
            minAllowedScrollY = Math.max(0, idealY - 25);
            maxAllowedScrollY = idealY + 25;
        } else {
            // Section is taller than viewport (e.g. large timeline or specs table):
            // Allow user to scroll down through the section, but restrict from scrolling past its top/bottom
            minAllowedScrollY = Math.max(0, focusTop - topBarOffset - 20);
            maxAllowedScrollY = Math.max(minAllowedScrollY, focusBottom - viewH + 40);
        }
    }

    /**
     * Navigate to a step by index
     */
    function goToStep(index) {
        if (!active || index < 0 || index >= activeSteps.length) return;

        currentStepIdx = index;
        const step = activeSteps[index];

        // Execute pre-step hook (e.g. switching tabs before spotlighting element)
        if (typeof step.beforeEnter === 'function') {
            step.beforeEnter();
        }

        let el = document.querySelector(step.target);

        // If target is missing, skip or fallback
        if (!el) {
            if (index < activeSteps.length - 1) {
                goToStep(index + 1);
            } else {
                closeTour();
            }
            return;
        }

        // Handle mobile sidebar visibility if targeting sidebar
        if (step.target === '#sidebar' && window.innerWidth <= 768) {
            const sidebarEl = document.getElementById('sidebar');
            if (sidebarEl) sidebarEl.classList.add('sidebar-open');
        }

        // Smooth scroll to element while unlocking transition
        isTransitioning = true;
        renderStepContent(step, index);

        // Synchronize spotlight position after Alpine DOM updates and scroll settles
        requestAnimationFrame(() => {
            el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
            updatePositions(el, step);
        });
        setTimeout(() => updatePositions(el, step), 150);
        setTimeout(() => {
            updatePositions(el, step);
            computeScrollBounds(el);
            isTransitioning = false;
        }, 360);
    }

    /**
     * Render popover text, badges, dots, and button states
     */
    function renderStepContent(step, index) {
        const total = activeSteps.length;
        const stepBadge = document.getElementById('dex-tour-step-badge');
        const moduleBadge = document.getElementById('dex-tour-module-badge');
        const titleEl = document.getElementById('dex-tour-title');
        const descEl = document.getElementById('dex-tour-description');
        const prevBtn = document.getElementById('dex-tour-prev-btn');
        const nextText = document.getElementById('dex-tour-next-text');
        const nextIcon = document.getElementById('dex-tour-next-icon');
        const dotsContainer = document.getElementById('dex-tour-dots');

        if (stepBadge) stepBadge.textContent = `Step ${index + 1} of ${total}`;
        if (moduleBadge) moduleBadge.textContent = (step.moduleName || currentModuleKey).toUpperCase();
        if (titleEl) titleEl.textContent = step.title;

        if (descEl) {
            if (typeof step.getContent === 'function') {
                descEl.innerHTML = step.getContent();
            } else {
                descEl.innerHTML = step.content || '';
            }
        }

        // Back button visibility
        if (prevBtn) {
            prevBtn.style.display = index === 0 ? 'none' : 'inline-flex';
        }

        // Next button state
        const isLast = index === total - 1;
        if (nextText) {
            nextText.textContent = isLast ? 'Finish' : 'Next';
        }
        if (nextIcon) {
            if (isLast) {
                nextIcon.innerHTML = '<polyline points="20 6 9 17 4 12"></polyline>';
            } else {
                nextIcon.innerHTML = '<line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline>';
            }
        }

        // Render minimized progress dots
        if (dotsContainer) {
            dotsContainer.innerHTML = '';

            if (total <= 7) {
                // When 7 or fewer steps, render all mini dots
                for (let i = 0; i < total; i++) {
                    const dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = `dex-tour-dot ${i === index ? 'is-active' : ''}`;
                    dot.setAttribute('aria-label', `Go to step ${i + 1}`);
                    dot.onclick = () => goToStep(i);
                    dotsContainer.appendChild(dot);
                }
            } else {
                // When more than 7 steps (e.g. 19 steps in Project View),
                // render a compact sliding window of 5 dots (Instagram/Carousel style)
                const maxVisible = 5;
                let start = Math.max(0, index - Math.floor(maxVisible / 2));
                let end = start + maxVisible;
                if (end > total) {
                    end = total;
                    start = Math.max(0, end - maxVisible);
                }

                for (let i = start; i < end; i++) {
                    const dot = document.createElement('button');
                    dot.type = 'button';
                    const isActive = i === index;
                    const isEdge = (!isActive && ((i === start && start > 0) || (i === end - 1 && end < total)));
                    dot.className = `dex-tour-dot ${isActive ? 'is-active' : ''} ${isEdge ? 'is-edge' : ''}`;
                    dot.setAttribute('aria-label', `Go to step ${i + 1}`);
                    dot.onclick = () => goToStep(i);
                    dotsContainer.appendChild(dot);
                }
            }
        }
    }

    /**
     * Compute bounding rectangles and align cutout + popover
     */
    function updatePositions(el, step) {
        if (!active || !el) return;

        const rect = el.getBoundingClientRect();
        const pad = step.padding || 6;

        // Cutout geometry
        const x = Math.max(0, rect.left - pad);
        const y = Math.max(0, rect.top - pad);
        const w = Math.min(window.innerWidth - x, rect.width + pad * 2);
        const h = Math.min(window.innerHeight - y, rect.height + pad * 2);

        // Update SVG cutout mask
        const cutout = document.getElementById('dex-tour-cutout');
        if (cutout) {
            cutout.setAttribute('x', x);
            cutout.setAttribute('y', y);
            cutout.setAttribute('width', Math.max(0, w));
            cutout.setAttribute('height', Math.max(0, h));
        }

        // Update glowing highlight frame
        const highlightBox = document.getElementById('dex-tour-highlight-box');
        if (highlightBox) {
            highlightBox.style.top = `${y}px`;
            highlightBox.style.left = `${x}px`;
            highlightBox.style.width = `${Math.max(0, w)}px`;
            highlightBox.style.height = `${Math.max(0, h)}px`;
            highlightBox.style.opacity = '1';
        }

        // Update Tooltip Popover position
        const popover = document.getElementById('dex-tour-popover');
        if (!popover) return;

        const popoverW = Math.min(440, window.innerWidth - 32);
        popover.style.width = `${popoverW}px`;
        const popoverH = popover.offsetHeight || 300;

        let finalPlacement = step.placement || 'bottom';
        let top = 0;
        let left = 0;

        if (finalPlacement === 'right') {
            if (x + w + 16 + popoverW > window.innerWidth - 16) {
                finalPlacement = 'bottom';
            }
        }

        if (finalPlacement === 'left') {
            if (x - popoverW - 16 < 16) {
                finalPlacement = 'bottom';
            }
        }

        if (finalPlacement === 'bottom') {
            if (y + h + 16 + popoverH > window.innerHeight - 16 && y - popoverH - 16 > 16) {
                finalPlacement = 'top';
            }
        }

        if (finalPlacement === 'right') {
            left = x + w + 16;
            top = Math.max(16, Math.min(window.innerHeight - popoverH - 16, y + 10));
        } else if (finalPlacement === 'left') {
            left = Math.max(16, x - popoverW - 16);
            top = Math.max(16, Math.min(window.innerHeight - popoverH - 16, y + 10));
        } else if (finalPlacement === 'top') {
            top = Math.max(16, y - popoverH - 16);
            left = Math.max(16, Math.min(window.innerWidth - popoverW - 16, x + (w - popoverW) / 2));
        } else { // bottom
            top = Math.min(window.innerHeight - popoverH - 16, y + h + 16);
            left = Math.max(16, Math.min(window.innerWidth - popoverW - 16, x + (w - popoverW) / 2));
        }

        popover.style.top = `${top}px`;
        popover.style.left = `${left}px`;
        popover.setAttribute('data-placement', finalPlacement);
        popover.classList.add('is-visible');

        // Align arrow
        const arrow = document.getElementById('dex-tour-arrow');
        if (arrow) {
            arrow.style.left = '';
            arrow.style.right = '';
            arrow.style.top = '';
            arrow.style.bottom = '';

            if (finalPlacement === 'bottom' || finalPlacement === 'top') {
                const targetCenter = x + w / 2;
                const arrowLeft = Math.max(24, Math.min(popoverW - 24, targetCenter - left));
                arrow.style.left = `${arrowLeft}px`;
            } else if (finalPlacement === 'right') {
                const targetCenter = y + Math.min(h / 2, 60);
                const arrowTop = Math.max(20, Math.min(popoverH - 20, targetCenter - top));
                arrow.style.top = `${arrowTop}px`;
            } else if (finalPlacement === 'left') {
                const targetCenter = y + Math.min(h / 2, 60);
                const arrowTop = Math.max(20, Math.min(popoverH - 20, targetCenter - top));
                arrow.style.top = `${arrowTop}px`;
                arrow.style.right = '-7px';
            }
        }
    }

    /**
     * Mouse wheel listener to restrict scrolling outside focused section
     */
    function onWheel(e) {
        if (!active) return;

        if (isTransitioning) {
            e.preventDefault();
            return;
        }

        // Allow scroll inside scrollable popover containers (e.g. sidebar module list)
        const popover = document.getElementById('dex-tour-popover');
        if (popover && popover.contains(e.target)) {
            const scrollableParent = e.target.closest('.tour-modules-list');
            if (scrollableParent && scrollableParent.scrollHeight > scrollableParent.clientHeight) {
                return;
            }
        }

        const currentY = window.scrollY;
        const nextY = currentY + e.deltaY;

        if (nextY < minAllowedScrollY) {
            if (currentY <= minAllowedScrollY) {
                e.preventDefault();
            } else {
                window.scrollTo({ top: minAllowedScrollY, behavior: 'auto' });
                e.preventDefault();
            }
        } else if (nextY > maxAllowedScrollY) {
            if (currentY >= maxAllowedScrollY) {
                e.preventDefault();
            } else {
                window.scrollTo({ top: maxAllowedScrollY, behavior: 'auto' });
                e.preventDefault();
            }
        }
    }

    /**
     * Touch events to restrict mobile scrolling outside focused section
     */
    function onTouchStart(e) {
        if (e.touches && e.touches.length > 0) {
            touchStartY = e.touches[0].clientY;
        }
    }

    function onTouchMove(e) {
        if (!active || isTransitioning || !e.touches || e.touches.length === 0) return;

        const popover = document.getElementById('dex-tour-popover');
        if (popover && popover.contains(e.target)) return;

        const currentTouchY = e.touches[0].clientY;
        const deltaY = touchStartY - currentTouchY;
        const nextY = window.scrollY + deltaY;

        if (nextY < minAllowedScrollY || nextY > maxAllowedScrollY) {
            e.preventDefault();
        }
    }

    /**
     * Scroll & Resize event throttle
     */
    function onScrollOrResize() {
        if (!active || !activeSteps[currentStepIdx]) return;

        // If user moved scrollbar or forced scroll past allowed section bounds, clamp it
        if (!isTransitioning) {
            const currentY = window.scrollY;
            if (currentY < minAllowedScrollY - 2) {
                window.scrollTo({ top: minAllowedScrollY, behavior: 'auto' });
            } else if (currentY > maxAllowedScrollY + 2) {
                window.scrollTo({ top: maxAllowedScrollY, behavior: 'auto' });
            }
        }

        if (scrollRafId) cancelAnimationFrame(scrollRafId);
        scrollRafId = requestAnimationFrame(() => {
            const step = activeSteps[currentStepIdx];
            const el = document.querySelector(step.target);
            if (el) {
                updatePositions(el, step);
                if (!isTransitioning) {
                    computeScrollBounds(el);
                }
            }
        });
    }

    /**
     * Keyboard navigation handler
     */
    function onKeyDown(e) {
        if (!active) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            closeTour();
        } else if (e.key === 'ArrowRight' || e.key === 'Enter') {
            e.preventDefault();
            nextStep();
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            prevStep();
        } else if (['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End', ' '].includes(e.key)) {
            const scrollDeltas = {
                'ArrowUp': -50,
                'ArrowDown': 50,
                'PageUp': -Math.round(window.innerHeight * 0.75),
                'PageDown': Math.round(window.innerHeight * 0.75),
                ' ': Math.round(window.innerHeight * 0.75),
                'Home': -100000,
                'End': 100000
            };

            const delta = scrollDeltas[e.key] || 0;
            const targetY = window.scrollY + delta;

            if (targetY < minAllowedScrollY) {
                e.preventDefault();
                window.scrollTo({ top: minAllowedScrollY, behavior: 'smooth' });
            } else if (targetY > maxAllowedScrollY) {
                e.preventDefault();
                window.scrollTo({ top: maxAllowedScrollY, behavior: 'smooth' });
            }
        }
    }

    /**
     * Advance to next step or complete
     */
    function nextStep() {
        if (currentStepIdx < activeSteps.length - 1) {
            goToStep(currentStepIdx + 1);
        } else {
            closeTour(true);
        }
    }

    /**
     * Return to previous step
     */
    function prevStep() {
        if (currentStepIdx > 0) {
            goToStep(currentStepIdx - 1);
        }
    }

    /**
     * Close and cancel tour
     */
    function closeTour(completed = false) {
        active = false;
        isTransitioning = false;

        // Restore previous minimized/collapsible states in project view
        restoreProjectShowSections();

        // In Staff Requests module, automatically return to the Request Approvals tab when finished, skipped, or cancelled
        if (currentModuleKey === 'pending-actions') {
            switchStaffRequestsTab('list');
        } else if (currentModuleKey === 'reports') {
            switchReportsTab('projects');
        } else if (currentModuleKey === 'decision-support') {
            switchDssTourView('simulator', 'feasibility');
        }

        const container = document.getElementById('dex-tour-container');
        if (container) container.style.display = 'none';

        const popover = document.getElementById('dex-tour-popover');
        if (popover) popover.classList.remove('is-visible');

        window.removeEventListener('wheel', onWheel);
        window.removeEventListener('touchstart', onTouchStart);
        window.removeEventListener('touchmove', onTouchMove);
        window.removeEventListener('scroll', onScrollOrResize);
        window.removeEventListener('resize', onScrollOrResize);
        window.removeEventListener('keydown', onKeyDown);

        if (window.innerWidth <= 768) {
            const sidebarEl = document.getElementById('sidebar');
            if (sidebarEl) sidebarEl.classList.remove('sidebar-open');
        }

        // Mark tour as completed for current user and module
        if (userId && userId !== '0') {
            const storageKey = `dex_tour_completed_${userId}_${currentModuleKey}`;
            localStorage.setItem(storageKey, 'true');
        }

        if (completed && typeof window.showToast === 'function') {
            window.showToast({
                message: 'Tutorial completed! You can reopen it anytime from the Info button in the header.',
                type: 'success'
            });
        }
    }

    /**
     * Initializer for first-time visitors
     */
    function initFirstTimeTour() {
        if (!userId || userId === '0') return;
        const storageKey = `dex_tour_completed_${userId}_${currentModule}`;
        if (!localStorage.getItem(storageKey)) {
            // First time visiting this module — trigger tour automatically
            setTimeout(() => {
                if (!active) {
                    startTour(currentModule, false);
                }
            }, 650);
        }
    }

    // Bind event listeners to popover buttons
    document.addEventListener('DOMContentLoaded', function() {
        const closeBtn = document.getElementById('dex-tour-close-btn');
        if (closeBtn) closeBtn.onclick = () => closeTour();

        const skipBtn = document.getElementById('dex-tour-skip-btn');
        if (skipBtn) skipBtn.onclick = () => closeTour();

        const nextBtn = document.getElementById('dex-tour-next-btn');
        if (nextBtn) nextBtn.onclick = () => nextStep();

        const prevBtn = document.getElementById('dex-tour-prev-btn');
        if (prevBtn) prevBtn.onclick = () => prevStep();

        initFirstTimeTour();
    });

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(initFirstTimeTour, 500);
    }

    // Expose global manual trigger function
    window.startOnboardingTour = function(moduleKey = null) {
        startTour(moduleKey || currentModule, true);
    };

    window.closeOnboardingTour = closeTour;
})();
</script>

