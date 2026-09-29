{{--
    Sidebar partial — role-filtered navigation.

    A role that cannot access a module does not see it rendered here at all
    (per ui-design-system.md — "full stop, not just visually deprioritized").
    Server-side Policies are still the real access control; this is UX only.
--}}

<div class="sidebar-header">
    <a href="{{ route('dashboard') }}" class="sidebar-brand" aria-label="Dex PMS home">
        <svg width="24" height="24" viewBox="0 0 32 32" fill="none" aria-hidden="true">
            <rect width="32" height="32" rx="6" fill="var(--color-accent)"/>
            <path d="M8 10h8a6 6 0 0 1 0 12H8V10z" fill="white"/>
            <rect x="19" y="17" width="5" height="5" rx="1" fill="white" opacity="0.7"/>
        </svg>
        <span>Dex International Co.</span>
    </a>
</div>

<nav class="sidebar-nav" aria-label="Application navigation">

    {{-- Dashboard — Admin and Manager only --}}
    @role('Admin|Manager')
    <div class="nav-section">
        <span class="nav-section-label">Overview</span>
        <ul class="nav-list" role="list">
            <li>
                <a href="{{ route('dashboard') }}"
                   class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link--active' : '' }}"
                   aria-current="{{ request()->routeIs('dashboard') ? 'page' : 'false' }}">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M2 11l8-8 8 8v9a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-9z"/>
                    </svg>
                    Dashboard
                </a>
            </li>
        </ul>
    </div>
    @endrole

    {{-- Project Management — Admin and Manager --}}
    @role('Admin|Manager')
    <div class="nav-section">
        <span class="nav-section-label">Projects</span>
        <ul class="nav-list" role="list">
            <li>
                <a href="{{ route('projects.index') }}"
                   class="nav-link {{ request()->routeIs('projects.*') ? 'nav-link--active' : '' }}"
                   aria-current="{{ request()->routeIs('projects.*') ? 'page' : 'false' }}">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M6 2a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7l-5-5H6zm5 1v4h4l-4-4z" clip-rule="evenodd"/>
                    </svg>
                    Projects
                </a>
            </li>
            <li>
                <a href="{{ route('costing.index') }}"
                   class="nav-link {{ request()->routeIs('costing.*') ? 'nav-link--active' : '' }}"
                   aria-current="{{ request()->routeIs('costing.*') ? 'page' : 'false' }}">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 0 1-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 0 1-.567.267z"/>
                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm1-13a1 1 0 1 0-2 0v.092a4.535 4.535 0 0 0-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 1 0-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 1 0 2 0v-.092a4.535 4.535 0 0 0 1.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0 0 11 9.092V7.151c.391.127.68.317.843.504a1 1 0 1 0 1.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                    </svg>
                    Costing
                </a>
            </li>
        </ul>
    </div>
    @endrole

    {{-- Resources — Admin, Manager and Staff --}}
    @role('Admin|Manager|Staff')
    <div class="nav-section">
        <span class="nav-section-label">Resources</span>
        <ul class="nav-list" role="list">
            <li>
                <a href="{{ route('resources.index') }}"
                   class="nav-link {{ request()->routeIs('resources.*') ? 'nav-link--active' : '' }}"
                   aria-current="{{ request()->routeIs('resources.*') ? 'page' : 'false' }}">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M5 3a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5zm0 2h10v10H5V5z"/>
                    </svg>
                    Resource Allocation
                </a>
            </li>
            <li>
                <a href="{{ route('equipment.index') }}"
                   class="nav-link {{ request()->routeIs('equipment.*') ? 'nav-link--active' : '' }}"
                   aria-current="{{ request()->routeIs('equipment.*') ? 'page' : 'false' }}">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 0 1-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 0 1 .947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 0 1 2.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 0 1 2.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 0 1 .947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 0 1-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 0 1-2.287-.947zM10 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z" clip-rule="evenodd"/>
                    </svg>
                    Equipment Records
                </a>
            </li>
        </ul>
    </div>
    @endrole

    {{-- Manpower — Admin and Manager --}}
    @role('Admin|Manager')
    <div class="nav-section">
        <span class="nav-section-label">Personnel</span>
        <ul class="nav-list" role="list">
            <li>
                <a href="{{ route('scheduling.index') }}"
                   class="nav-link {{ request()->routeIs('scheduling.*') ? 'nav-link--active' : '' }}"
                   aria-current="{{ request()->routeIs('scheduling.*') ? 'page' : 'false' }}">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M9 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM17 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 0 0-1.5-4.33A5 5 0 0 1 19 16v1h-6.07zM6 11a5 5 0 0 1 5 5v1H1v-1a5 5 0 0 1 5-5z"/>
                    </svg>
                    Manpower
                </a>
            </li>
        </ul>
    </div>
    @endrole

    {{-- Reports — Admin and Manager --}}
    @role('Admin|Manager')
    <div class="nav-section">
        <span class="nav-section-label">Insights</span>
        <ul class="nav-list" role="list">
            <li>
                <a href="{{ route('reports.index') }}"
                   class="nav-link {{ request()->routeIs('reports.*') ? 'nav-link--active' : '' }}"
                   aria-current="{{ request()->routeIs('reports.*') ? 'page' : 'false' }}">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M6 2a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7.414A2 2 0 0 0 15.414 7L11 2.586A2 2 0 0 0 9.586 2H6zm2 10a1 1 0 1 0 0 2h4a1 1 0 1 0 0-2H8zm0-4a1 1 0 1 0 0 2h4a1 1 0 1 0 0-2H8z" clip-rule="evenodd"/>
                    </svg>
                    Reports
                </a>
            </li>
            <li>
                <a href="{{ route('decision-support.index') }}"
                   class="nav-link {{ request()->routeIs('decision-support.*') ? 'nav-link--active' : '' }}"
                   aria-current="{{ request()->routeIs('decision-support.*') ? 'page' : 'false' }}">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M11 3a1 1 0 1 0-2 0v1a1 1 0 1 0 2 0V3zM15.657 5.757a1 1 0 0 0-1.414-1.414l-.707.707a1 1 0 0 0 1.414 1.414l.707-.707zM18 10a1 1 0 0 1-1 1h-1a1 1 0 1 1 0-2h1a1 1 0 0 1 1 1zM5.05 6.464A1 1 0 1 0 6.464 5.05l-.707-.707a1 1 0 0 0-1.414 1.414l.707.707zM5 10a1 1 0 0 1-1 1H3a1 1 0 1 1 0-2h1a1 1 0 0 1 1 1zM8 16v-1h4v1a2 2 0 1 1-4 0zM12 14c.015-.34.208-.646.477-.859a4 4 0 1 0-4.954 0c.27.213.462.519.476.859h4.001z"/>
                    </svg>
                    Decision Support
                </a>
            </li>
        </ul>
    </div>
    @endrole

    {{-- User management — Admin only --}}
    @role('Admin')
    <div class="nav-section">
        <span class="nav-section-label">Administration</span>
        <ul class="nav-list" role="list">
            <li>
                <a href="{{ route('account.settings') }}"
                   class="nav-link {{ request()->routeIs('account.settings') || request()->routeIs('account.users.*') ? 'nav-link--active' : '' }}"
                   aria-current="{{ request()->routeIs('account.settings') || request()->routeIs('account.users.*') ? 'page' : 'false' }}">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-7 9a7 7 0 1 1 14 0H3z" clip-rule="evenodd"/>
                    </svg>
                    User Management
                </a>
            </li>
        </ul>
    </div>
    @endrole

</nav>
