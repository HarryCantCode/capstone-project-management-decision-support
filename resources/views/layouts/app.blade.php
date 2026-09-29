<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') — Dex International Co.</title>
    <meta name="description" content="@yield('meta-description', 'Dex International Co. Project Management System')">

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
                <div class="topbar-search" style="flex: 1; max-width: 400px; position: relative;">
                    <svg style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--color-muted);" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                    <input type="text" placeholder="Search projects, resources..." class="form-control" style="padding-left: 36px; border-radius: 20px; background: var(--color-surface-alt); border: none; font-size: 0.875rem;" disabled>
                </div>

                <span class="topbar-page-title" style="margin-left: auto; margin-right: 16px; font-weight: 600; color: var(--color-ink); letter-spacing: -0.01em;">Project Management System</span>

                {{-- User dropdown --}}
                <div class="topbar-user" x-data="{ open: false }">
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

    @stack('scripts')
</body>
</html>
