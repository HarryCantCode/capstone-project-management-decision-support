<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') — Dex PMS</title>
    <meta name="description" content="@yield('meta-description', 'Dex International Co. Project Management System')">

    {{-- Google Fonts: Inter (body) + Roboto Mono (numbers/IDs) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="app-body" x-data="{ sidebarOpen: false }">

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
                {{-- Mobile hamburger --}}
                <button
                    id="sidebar-toggle"
                    class="sidebar-toggle"
                    type="button"
                    @click="sidebarOpen = !sidebarOpen"
                    :aria-expanded="sidebarOpen.toString()"
                    aria-controls="sidebar"
                    aria-label="Toggle navigation"
                >
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M3 5h14a1 1 0 0 1 0 2H3a1 1 0 0 1 0-2zm0 4h14a1 1 0 0 1 0 2H3a1 1 0 0 1 0-2zm0 4h14a1 1 0 0 1 0 2H3a1 1 0 0 1 0-2z" clip-rule="evenodd"/>
                    </svg>
                </button>

                <span class="topbar-page-title">@yield('title', 'Dashboard')</span>

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
                        <span class="user-menu-name">{{ Auth::user()->name }}</span>
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
                                Account settings
                            </a>
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
                                Sign out
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
