<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') — Dex International Co.</title>
    <meta name="description" content="@yield('meta-description', 'Dex International Co. Project Management System')">

    {{-- Favicon --}}
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    {{-- Google Fonts: Inter (body) + Roboto Mono (numbers/IDs) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">

    <script>
        (function() {
            const savedTheme = localStorage.getItem('dex_theme');
            const systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (savedTheme === 'dark' || (!savedTheme && systemDark)) {
                document.documentElement.setAttribute('data-theme', 'dark');
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="app-body" x-data="{
    sidebarOpen: false,
    darkMode: document.documentElement.getAttribute('data-theme') === 'dark',
    toggleTheme() {
        this.darkMode = !this.darkMode;
        const newTheme = this.darkMode ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', newTheme);
        if (this.darkMode) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        localStorage.setItem('dex_theme', newTheme);
    }
}">

    {{-- Skip link for keyboard users --}}
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <div class="app-layout">

        {{-- Sidebar --}}
        <aside
            id="sidebar"
            class="app-sidebar"
            :class="{ 'sidebar-open': sidebarOpen }"
            aria-label="Main navigation"
        >
            @include('layouts.partials.sidebar')
        </aside>

        {{-- Sidebar overlay (mobile) --}}
        <div
            class="sidebar-overlay"
            :class="{ 'visible': sidebarOpen }"
            @click="sidebarOpen = false"
            aria-hidden="true"
        ></div>

        {{-- Main content area --}}
        <div class="app-main">

            {{-- Top bar --}}
            <header class="app-topbar" role="banner">
                {{-- Global Searchbar (Top-Left) --}}
                <div
                    class="topbar-search"
                    x-data="globalSearch()"
                    @keydown="onKeydown($event)"
                    @click.outside="open = false"
                >
                    <div class="topbar-search-input-wrap">
                        <svg class="topbar-search-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                        </svg>
                        <input
                            type="text"
                            x-ref="searchInput"
                            x-model="query"
                            @input.debounce.250ms="performSearch()"
                            @focus="if(query.trim().length >= 1) open = true"
                            placeholder="Search projects, manpower, resources... (Press '/' to focus)"
                            class="topbar-search-input"
                            autocomplete="off"
                            aria-label="Global real-time search"
                            aria-autocomplete="list"
                            :aria-expanded="open.toString()"
                            role="combobox"
                        >
                        <div class="topbar-search-actions">
                            <div x-show="loading" class="topbar-search-spinner" aria-hidden="true" style="display: none;"></div>
                            <button
                                type="button"
                                x-show="query.length > 0 && !loading"
                                @click="clear()"
                                class="topbar-search-clear"
                                title="Clear search query"
                                aria-label="Clear search query"
                                style="display: none;"
                            >
                                <svg style="width: 14px; height: 14px;" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Real-time Dropdown Results --}}
                    <div
                        class="search-dropdown"
                        x-show="open && query.trim().length >= 1"
                        x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 transform scale-95"
                        x-transition:enter-end="opacity-100 transform scale-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 transform scale-100"
                        x-transition:leave-end="opacity-0 transform scale-95"
                        role="listbox"
                    >
                        <div class="search-dropdown-content" x-ref="resultsContainer">
                            {{-- Projects Section --}}
                            <template x-if="results.projects && results.projects.length > 0">
                                <div>
                                    <div class="search-category-header">
                                        <span>Projects</span>
                                        <span x-text="`${results.projects.length} found`"></span>
                                    </div>
                                    <template x-for="(project, idx) in results.projects" :key="'proj-' + project.id">
                                        <a
                                            :href="project.url"
                                            class="search-result-item"
                                            :class="{ 'is-keyboard-active': selectedIndex === idx }"
                                            @mouseenter="selectedIndex = idx"
                                        >
                                            <div class="search-result-icon search-result-icon--project" title="Project">
                                                <svg style="width: 18px; height: 18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                                                </svg>
                                            </div>
                                            <div class="search-result-info">
                                                <div class="search-result-top">
                                                    <span class="search-result-title" x-text="project.title"></span>
                                                    <span class="search-result-code" x-text="project.code"></span>
                                                </div>
                                                <div class="search-result-bottom">
                                                    <span x-text="project.subtitle"></span>
                                                    <span>·</span>
                                                    <span x-text="project.meta"></span>
                                                </div>
                                            </div>
                                            <div class="search-result-badge">
                                                <span class="status-pill" :class="`status-pill--${project.status_class}`" x-text="project.status"></span>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </template>

                            {{-- Manpower Section --}}
                            <template x-if="results.manpower && results.manpower.length > 0">
                                <div>
                                    <div class="search-category-header">
                                        <span>Manpower</span>
                                        <span x-text="`${results.manpower.length} found`"></span>
                                    </div>
                                    <template x-for="(person, idx) in results.manpower" :key="'man-' + person.id">
                                        <a
                                            :href="person.url"
                                            class="search-result-item"
                                            :class="{ 'is-keyboard-active': selectedIndex === (results.projects ? results.projects.length : 0) + idx }"
                                            @mouseenter="selectedIndex = (results.projects ? results.projects.length : 0) + idx"
                                        >
                                            <div class="search-result-icon search-result-icon--manpower" title="Manpower Personnel">
                                                <svg style="width: 18px; height: 18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                                    <circle cx="9" cy="7" r="4"/>
                                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                                </svg>
                                            </div>
                                            <div class="search-result-info">
                                                <div class="search-result-top">
                                                    <span class="search-result-title" x-text="person.title"></span>
                                                    <span class="search-result-code" x-text="person.code"></span>
                                                </div>
                                                <div class="search-result-bottom">
                                                    <span style="font-weight: 500; color: var(--color-ink);" x-text="person.subtitle"></span>
                                                    <span>·</span>
                                                    <span x-text="person.meta"></span>
                                                </div>
                                            </div>
                                            <div class="search-result-badge">
                                                <span class="status-pill" :class="`status-pill--${person.status_class}`" x-text="person.status"></span>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </template>

                            {{-- Resources Section --}}
                            <template x-if="results.resources && results.resources.length > 0">
                                <div>
                                    <div class="search-category-header">
                                        <span>Resources</span>
                                        <span x-text="`${results.resources.length} found`"></span>
                                    </div>
                                    <template x-for="(res, idx) in results.resources" :key="'res-' + res.id">
                                        <a
                                            :href="res.url"
                                            class="search-result-item"
                                            :class="{ 'is-keyboard-active': selectedIndex === (results.projects ? results.projects.length : 0) + (results.manpower ? results.manpower.length : 0) + idx }"
                                            @mouseenter="selectedIndex = (results.projects ? results.projects.length : 0) + (results.manpower ? results.manpower.length : 0) + idx"
                                        >
                                            <div class="search-result-icon search-result-icon--resource" title="Resource">
                                                <svg style="width: 18px; height: 18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                                                    <line x1="12" y1="22.08" x2="12" y2="12"/>
                                                </svg>
                                            </div>
                                            <div class="search-result-info">
                                                <div class="search-result-top">
                                                    <span class="search-result-title" x-text="res.title"></span>
                                                    <span class="search-result-code" x-text="res.code"></span>
                                                </div>
                                                <div class="search-result-bottom">
                                                    <span x-text="res.subtitle"></span>
                                                    <span>·</span>
                                                    <span x-text="res.meta"></span>
                                                </div>
                                            </div>
                                            <div class="search-result-badge">
                                                <span class="status-pill" :class="`status-pill--${res.status_class}`" x-text="res.status"></span>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </template>

                            {{-- No Results Empty State --}}
                            <div x-show="!loading && results.total === 0" class="search-empty-state">
                                <svg style="width: 32px; height: 32px; margin: 0 auto 8px; color: var(--color-muted); opacity: 0.6;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <circle cx="11" cy="11" r="8"/>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                </svg>
                                <p style="font-weight: 600; font-size: 0.875rem; margin-bottom: 2px; color: var(--color-ink);">No matching records found</p>
                                <p style="font-size: 0.8125rem; margin: 0; color: var(--color-muted);">No projects, manpower, or resources match "<strong x-text="query"></strong>".</p>
                            </div>
                        </div>

                        {{-- Footer with keyboard hints --}}
                        <div class="search-dropdown-footer">
                            <span x-text="results.total > 0 ? `${results.total} matching result${results.total === 1 ? '' : 's'}` : 'Global Search'"></span>
                            <div class="search-kbd-hints">
                                <span><kbd class="search-kbd">↑</kbd><kbd class="search-kbd">↓</kbd> Navigate</span>
                                <span><kbd class="search-kbd">↵</kbd> Select</span>
                                <span><kbd class="search-kbd">Esc</kbd> Close</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- User dropdown (Right-aligned) --}}
                <div class="topbar-user" style="margin-left: auto; display: flex; align-items: center;" x-data="{ open: false }">
                    <button
                        id="user-menu-button"
                        type="button"
                        class="user-menu-trigger"
                        @click="open = !open"
                        :aria-expanded="open.toString()"
                        aria-haspopup="true"
                        aria-controls="user-menu"
                    >
                        <div style="display: flex; flex-direction: column; align-items: flex-start; text-align: left; line-height: 1.25;">
                            <span class="user-menu-name">{{ Auth::user()->name }}</span>
                            @if(Auth::user()->account_number)
                                <span class="user-menu-account font-mono text-muted" style="font-size: 0.75rem; font-weight: 500;">Acc: {{ Auth::user()->account_number }}</span>
                            @endif
                        </div>
                        <span class="user-menu-role badge">{{ Auth::user()->getRoleNames()->first() }}</span>
                    </button>

                    <ul
                        id="user-menu"
                        class="user-dropdown"
                        x-show="open"
                        @click.outside="open = false"
                        x-transition
                        role="menu"
                        aria-labelledby="user-menu-button"
                    >
                        <li role="none">
                            <a href="{{ route('account.settings') }}" role="menuitem" class="user-dropdown-item">
                                <svg class="dropdown-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                                </svg>
                                Account Settings
                            </a>
                        </li>
                        <li role="none">
                            <button
                                type="button"
                                role="menuitem"
                                class="user-dropdown-item"
                                @click="toggleTheme()"
                            >
                                <span style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                    <span style="display: flex; align-items: center;">
                                        <svg x-show="!darkMode" class="dropdown-icon" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                                        </svg>
                                        <svg x-show="darkMode" x-cloak class="dropdown-icon" style="color: #FBBF24;" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
                                        </svg>
                                        <span x-text="darkMode ? 'Dark Mode' : 'Dark Mode'">Dark Mode</span>
                                    </span>
                                    <span class="theme-toggle-switch" :class="{ 'theme-toggle-switch--active': darkMode }">
                                        <span class="theme-toggle-switch-thumb"></span>
                                    </span>
                                </span>
                            </button>
                        </li>
                        <li role="separator" class="dropdown-divider"></li>
                        <li role="none">
                            <button
                                id="topbar-logout"
                                type="button"
                                role="menuitem"
                                class="user-dropdown-item user-dropdown-item--danger"
                                onclick="document.getElementById('topbar-logout-form').submit()"
                            >
                                <svg class="dropdown-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 001-1V4a1 1 0 00-1-1H3zm11 4.414l-4.293 4.293a1 1 0 01-1.414 0L4 7.414 5.414 6l3.293 3.293L12.586 6 14 7.414z" clip-rule="evenodd" />
                                </svg>
                                Sign Out
                            </button>
                        </li>
                    </ul>

                    <form id="topbar-logout-form" method="POST" action="{{ route('logout') }}" class="d-none">
                        @csrf
                    </form>
                </div>
            </header>

            {{-- Flash messages --}}
            @if (session('status'))
                <div class="flash-banner flash-banner--success" role="alert" x-data="{ show: true }" x-show="show">
                    {{ session('status') }}
                    <button type="button" class="flash-close" @click="show = false" aria-label="Dismiss">×</button>
                </div>
            @endif

            @if (session('error'))
                <div class="flash-banner flash-banner--error" role="alert" x-data="{ show: true }" x-show="show">
                    {{ session('error') }}
                    <button type="button" class="flash-close" @click="show = false" aria-label="Dismiss">×</button>
                </div>
            @endif

            {{-- Page content --}}
            <main id="main-content" class="app-content" tabindex="-1">
                @yield('content')
            </main>

        </div>
    </div>

    <script>
        function globalSearch() {
            return {
                query: '',
                open: false,
                loading: false,
                results: { projects: [], manpower: [], resources: [], total: 0 },
                selectedIndex: -1,
                searchUrl: '{{ route('global-search') }}',
                abortController: null,

                init() {
                    window.addEventListener('keydown', (e) => {
                        if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                            e.preventDefault();
                            this.$refs.searchInput.focus();
                        }
                    });
                },

                async performSearch() {
                    const q = this.query.trim();
                    if (q.length < 1) {
                        this.results = { projects: [], manpower: [], resources: [], total: 0 };
                        this.open = false;
                        this.selectedIndex = -1;
                        return;
                    }

                    this.loading = true;
                    this.open = true;

                    if (this.abortController) {
                        this.abortController.abort();
                    }
                    this.abortController = new AbortController();

                    try {
                        const response = await fetch(`${this.searchUrl}?q=${encodeURIComponent(q)}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            signal: this.abortController.signal
                        });

                        if (response.ok) {
                            const data = await response.json();
                            this.results = data;
                            this.selectedIndex = -1;
                        }
                    } catch (err) {
                        if (err.name !== 'AbortError') {
                            console.error('Search error:', err);
                        }
                    } finally {
                        this.loading = false;
                    }
                },

                get allItems() {
                    return [
                        ...(this.results.projects || []).map(item => ({ ...item, category: 'project' })),
                        ...(this.results.manpower || []).map(item => ({ ...item, category: 'manpower' })),
                        ...(this.results.resources || []).map(item => ({ ...item, category: 'resource' }))
                    ];
                },

                onKeydown(e) {
                    const items = this.allItems;
                    if (!this.open || items.length === 0) return;

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        this.selectedIndex = (this.selectedIndex + 1) % items.length;
                        this.scrollToActive();
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        this.selectedIndex = (this.selectedIndex - 1 + items.length) % items.length;
                        this.scrollToActive();
                    } else if (e.key === 'Enter') {
                        if (this.selectedIndex >= 0 && this.selectedIndex < items.length) {
                            e.preventDefault();
                            window.location.href = items[this.selectedIndex].url;
                        }
                    } else if (e.key === 'Escape') {
                        this.open = false;
                    }
                },

                scrollToActive() {
                    this.$nextTick(() => {
                        const activeEl = this.$refs.resultsContainer?.querySelector('.is-keyboard-active');
                        if (activeEl) {
                            activeEl.scrollIntoView({ block: 'nearest' });
                        }
                    });
                },

                clear() {
                    this.query = '';
                    this.results = { projects: [], manpower: [], resources: [], total: 0 };
                    this.open = false;
                    this.selectedIndex = -1;
                    this.$refs.searchInput.focus();
                }
            };
        }
    </script>

    @stack('scripts')
</body>
</html>
