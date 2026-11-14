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
    } elseif (request()->routeIs('projects.*')) {
        $currentModule = 'projects';
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
    font-size: 0.6875rem;
    font-weight: 600;
    line-height: 1;
    padding: 3px 8px;
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
    gap: var(--space-3);
    margin-top: 1rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--color-border);
}

[data-theme="dark"] .dex-tour-footer {
    border-top-color: #243042;
}

.dex-tour-dots {
    display: flex;
    align-items: center;
    gap: 5px;
}

.dex-tour-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--color-border);
    border: none;
    padding: 0;
    cursor: pointer;
    transition: background-color var(--transition-fast), transform var(--transition-fast), width var(--transition-fast);
}

.dex-tour-dot:hover {
    background: var(--color-accent);
}

.dex-tour-dot.is-active {
    width: 16px;
    border-radius: var(--radius-pill);
    background: var(--color-accent);
}

[data-theme="dark"] .dex-tour-dot {
    background: #2E3C52;
}

[data-theme="dark"] .dex-tour-dot.is-active {
    background: #38BDF8;
}

.dex-tour-actions {
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.dex-tour-nav-btns {
    display: flex;
    align-items: center;
    gap: 6px;
}

.dex-tour-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8125rem;
    font-weight: 600;
    border-radius: var(--radius-md);
    padding: 0.375rem 0.75rem;
    cursor: pointer;
    line-height: 1.25;
    transition: background var(--transition-fast), border-color var(--transition-fast), color var(--transition-fast), transform var(--transition-fast);
}

.dex-tour-btn--skip {
    background: transparent;
    border: none;
    color: var(--color-muted);
    font-weight: 500;
    padding: 0.375rem 0.5rem;
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
                target: '[data-tour-nav="costing"]',
                title: 'Costing Module',
                moduleName: 'Costing',
                placement: 'right',
                padding: 4,
                content: '<p><strong>Costing module</strong> — Where you can track your expenses and budget for a project.</p>'
            },
            {
                target: '.costing-kpi-grid, .page-header, main',
                title: 'Budget & Cost Tracking',
                moduleName: 'Costing',
                placement: 'bottom',
                padding: 8,
                content: '<p>Monitor contracted budgets, actual logged expenses (materials, labor, equipment), and calculate variances to prevent cost overruns.</p>'
            }
        ],
        'pending-actions': [
            {
                target: '[data-tour-nav="pending-actions"]',
                title: 'Staff Request Module',
                moduleName: 'Staff Requests',
                placement: 'right',
                padding: 4,
                content: '<p><strong>Staff request module (for admin/manager)</strong> — You can see the request that was made by your staff related to progression of a project.</p>'
            },
            {
                target: '.pending-actions-table, .card, main',
                title: 'Review & Action Queue',
                moduleName: 'Staff Requests',
                placement: 'bottom',
                padding: 8,
                content: '<p>Inspect pending staff submissions, examine task justifications or resource requests, and approve or reject with audit notes.</p>'
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
                target: '[data-tour-nav="resources"]',
                title: 'Materials Catalog Module',
                moduleName: 'Materials',
                placement: 'right',
                padding: 4,
                content: '<p><strong>Materials catalog module</strong> — Monitor your the changes from your material list.</p>'
            },
            {
                target: '.table-responsive, .card, main',
                title: 'Catalog Inventory & Stock',
                moduleName: 'Materials',
                placement: 'bottom',
                padding: 8,
                content: '<p>Manage physical materials, track remaining balances, set low-stock warning thresholds, and update unit pricing.</p>'
            }
        ],
        'scheduling': [
            {
                target: '[data-tour-nav="scheduling"]',
                title: 'Manpower Module',
                moduleName: 'Manpower',
                placement: 'right',
                padding: 4,
                content: '<p><strong>Manpower module</strong> — List of all your active available people that you may assign from each project.</p>'
            },
            {
                target: '.schedule-grid, .card, main',
                title: 'Personnel Roster & Deployments',
                moduleName: 'Manpower',
                placement: 'bottom',
                padding: 8,
                content: '<p>Review available personnel, assign skilled tradespeople to project tasks, and prevent double-booking.</p>'
            }
        ],
        'reports': [
            {
                target: '[data-tour-nav="reports"]',
                title: 'Reports Module',
                moduleName: 'Reports',
                placement: 'right',
                padding: 4,
                content: '<p><strong>Reports module</strong> — Generate an exportable information about your project, materials, revenue, and manpower.</p>'
            },
            {
                target: '.reports-form, .card, main',
                title: 'Exportable Analytics & Reports',
                moduleName: 'Reports',
                placement: 'bottom',
                padding: 8,
                content: '<p>Configure custom date ranges and export publication-ready PDF and Excel reports for projects, financials, materials, and labor.</p>'
            }
        ],
        'decision-support': [
            {
                target: '[data-tour-nav="decision-support"]',
                title: 'Decision Support Module',
                moduleName: 'Decision Support',
                placement: 'right',
                padding: 4,
                content: '<p><strong>Decision Support module</strong> — Helps you strategize and provides informed choices guiding you toward the best possible action for your business.</p>'
            },
            {
                target: '.decision-support-grid, .card, main',
                title: 'Strategic Insights & Recommendations',
                moduleName: 'Decision Support',
                placement: 'bottom',
                padding: 8,
                content: '<p>Leverage algorithmic forecasting and risk modeling to uncover project bottlenecks and optimize business decisions.</p>'
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
     * Start the tour for a specific module
     */
    function startTour(moduleKey, isManual = false) {
        const key = moduleKey || currentModule;
        currentModuleKey = key;
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
        window.addEventListener('scroll', onScrollOrResize, { passive: true });
        window.addEventListener('resize', onScrollOrResize, { passive: true });
        window.addEventListener('keydown', onKeyDown);

        goToStep(0);
    }

    /**
     * Navigate to a step by index
     */
    function goToStep(index) {
        if (!active || index < 0 || index >= activeSteps.length) return;

        currentStepIdx = index;
        const step = activeSteps[index];
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

        // Smooth scroll to element
        el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });

        // Update UI
        renderStepContent(step, index);

        // Synchronize spotlight position after scroll starts and settles
        requestAnimationFrame(() => updatePositions(el, step));
        setTimeout(() => updatePositions(el, step), 150);
        setTimeout(() => updatePositions(el, step), 320);
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

        // Render progress dots
        if (dotsContainer) {
            dotsContainer.innerHTML = '';
            for (let i = 0; i < total; i++) {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = `dex-tour-dot ${i === index ? 'is-active' : ''}`;
                dot.setAttribute('aria-label', `Go to step ${i + 1}`);
                dot.onclick = () => goToStep(i);
                dotsContainer.appendChild(dot);
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

        if (finalPlacement === 'bottom') {
            if (y + h + 16 + popoverH > window.innerHeight - 16 && y - popoverH - 16 > 16) {
                finalPlacement = 'top';
            }
        }

        if (finalPlacement === 'right') {
            left = x + w + 16;
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
            if (finalPlacement === 'bottom' || finalPlacement === 'top') {
                const targetCenter = x + w / 2;
                const arrowLeft = Math.max(24, Math.min(popoverW - 24, targetCenter - left));
                arrow.style.left = `${arrowLeft}px`;
                arrow.style.top = '';
            } else if (finalPlacement === 'right') {
                const targetCenter = y + Math.min(h / 2, 60);
                const arrowTop = Math.max(20, Math.min(popoverH - 20, targetCenter - top));
                arrow.style.top = `${arrowTop}px`;
                arrow.style.left = '';
            }
        }
    }

    /**
     * Scroll & Resize event throttle
     */
    function onScrollOrResize() {
        if (!active || !activeSteps[currentStepIdx]) return;
        if (scrollRafId) cancelAnimationFrame(scrollRafId);
        scrollRafId = requestAnimationFrame(() => {
            const step = activeSteps[currentStepIdx];
            const el = document.querySelector(step.target);
            if (el) updatePositions(el, step);
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
        const container = document.getElementById('dex-tour-container');
        if (container) container.style.display = 'none';

        const popover = document.getElementById('dex-tour-popover');
        if (popover) popover.classList.remove('is-visible');

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

