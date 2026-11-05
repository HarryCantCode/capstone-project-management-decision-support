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
                            placeholder="Search projects, tasks, files, expenses, resources... (Press '/' to focus)"
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
                                            :class="{ 'is-keyboard-active': selectedIndex === getItemIndex('projects', idx) }"
                                            @mouseenter="selectedIndex = getItemIndex('projects', idx)"
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
                                            :class="{ 'is-keyboard-active': selectedIndex === getItemIndex('manpower', idx) }"
                                            @mouseenter="selectedIndex = getItemIndex('manpower', idx)"
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
                                            :class="{ 'is-keyboard-active': selectedIndex === getItemIndex('resources', idx) }"
                                            @mouseenter="selectedIndex = getItemIndex('resources', idx)"
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

                            {{-- Major Tasks Section --}}
                            <template x-if="results.major_tasks && results.major_tasks.length > 0">
                                <div>
                                    <div class="search-category-header">
                                        <span>Major Tasks (Projects)</span>
                                        <span x-text="`${results.major_tasks.length} found`"></span>
                                    </div>
                                    <template x-for="(task, idx) in results.major_tasks" :key="'mtask-' + task.id">
                                        <a
                                            :href="task.url"
                                            class="search-result-item"
                                            :class="{ 'is-keyboard-active': selectedIndex === getItemIndex('major_tasks', idx) }"
                                            @mouseenter="selectedIndex = getItemIndex('major_tasks', idx)"
                                        >
                                            <div class="search-result-icon search-result-icon--task" title="Major Task">
                                                <svg style="width: 18px; height: 18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                                    <path d="m9 16 2 2 4-4"/>
                                                </svg>
                                            </div>
                                            <div class="search-result-info">
                                                <div class="search-result-top">
                                                    <span class="search-result-title" x-text="task.title"></span>
                                                    <span class="search-result-code" x-text="task.code"></span>
                                                </div>
                                                <div class="search-result-bottom">
                                                    <span style="font-weight: 500; color: var(--color-ink);" x-text="task.subtitle"></span>
                                                    <span>·</span>
                                                    <span x-text="task.meta"></span>
                                                </div>
                                            </div>
                                            <div class="search-result-badge">
                                                <span class="status-pill" :class="`status-pill--${task.status_class}`" x-text="task.status"></span>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </template>

                            {{-- Specific Tasks / Subtasks Section --}}
                            <template x-if="results.specific_tasks && results.specific_tasks.length > 0">
                                <div>
                                    <div class="search-category-header">
                                        <span>Specific Tasks (Subtasks)</span>
                                        <span x-text="`${results.specific_tasks.length} found`"></span>
                                    </div>
                                    <template x-for="(subtask, idx) in results.specific_tasks" :key="'stask-' + subtask.id">
                                        <a
                                            :href="subtask.url"
                                            class="search-result-item"
                                            :class="{ 'is-keyboard-active': selectedIndex === getItemIndex('specific_tasks', idx) }"
                                            @mouseenter="selectedIndex = getItemIndex('specific_tasks', idx)"
                                        >
                                            <div class="search-result-icon search-result-icon--subtask" title="Specific Subtask">
                                                <svg style="width: 18px; height: 18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="9 11 12 14 22 4"/>
                                                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                                </svg>
                                            </div>
                                            <div class="search-result-info">
                                                <div class="search-result-top">
                                                    <span class="search-result-title" x-text="subtask.title"></span>
                                                    <span class="search-result-code" x-text="subtask.code"></span>
                                                </div>
                                                <div class="search-result-bottom">
                                                    <span x-text="subtask.subtitle"></span>
                                                    <span>·</span>
                                                    <span x-text="subtask.meta"></span>
                                                </div>
                                            </div>
                                            <div class="search-result-badge">
                                                <span class="status-pill" :class="`status-pill--${subtask.status_class}`" x-text="subtask.status"></span>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </template>

                            {{-- Project Files Section --}}
                            <template x-if="results.files && results.files.length > 0">
                                <div>
                                    <div class="search-category-header">
                                        <span>Project Attachments & Files</span>
                                        <span x-text="`${results.files.length} found`"></span>
                                    </div>
                                    <template x-for="(file, idx) in results.files" :key="'file-' + file.id">
                                        <a
                                            :href="file.url"
                                            class="search-result-item"
                                            :class="{ 'is-keyboard-active': selectedIndex === getItemIndex('files', idx) }"
                                            @mouseenter="selectedIndex = getItemIndex('files', idx)"
                                        >
                                            <div class="search-result-icon search-result-icon--file" title="Attached File">
                                                <svg style="width: 18px; height: 18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                                                </svg>
                                            </div>
                                            <div class="search-result-info">
                                                <div class="search-result-top">
                                                    <span class="search-result-title" x-text="file.title"></span>
                                                    <span class="search-result-code" x-text="file.code"></span>
                                                </div>
                                                <div class="search-result-bottom">
                                                    <span x-text="file.subtitle"></span>
                                                    <span>·</span>
                                                    <span x-text="file.meta"></span>
                                                </div>
                                            </div>
                                            <div class="search-result-badge">
                                                <span class="status-pill status-pill--completed" x-text="file.status"></span>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </template>

                            {{-- Project Expenses Section --}}
                            <template x-if="results.expenses && results.expenses.length > 0">
                                <div>
                                    <div class="search-category-header">
                                        <span>Logged Expenses & Costs</span>
                                        <span x-text="`${results.expenses.length} found`"></span>
                                    </div>
                                    <template x-for="(expense, idx) in results.expenses" :key="'exp-' + expense.id">
                                        <a
                                            :href="expense.url"
                                            class="search-result-item"
                                            :class="{ 'is-keyboard-active': selectedIndex === getItemIndex('expenses', idx) }"
                                            @mouseenter="selectedIndex = getItemIndex('expenses', idx)"
                                        >
                                            <div class="search-result-icon search-result-icon--expense" title="Logged Expense">
                                                <svg style="width: 18px; height: 18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <line x1="12" y1="1" x2="12" y2="23"/>
                                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                                </svg>
                                            </div>
                                            <div class="search-result-info">
                                                <div class="search-result-top">
                                                    <span class="search-result-title" x-text="expense.title"></span>
                                                    <span class="search-result-code" x-text="expense.code"></span>
                                                </div>
                                                <div class="search-result-bottom">
                                                    <span x-text="expense.subtitle"></span>
                                                    <span>·</span>
                                                    <span x-text="expense.meta"></span>
                                                </div>
                                            </div>
                                            <div class="search-result-badge">
                                                <span class="status-pill status-pill--ongoing font-mono font-bold" x-text="expense.status"></span>
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
                                <p style="font-size: 0.8125rem; margin: 0; color: var(--color-muted);">No projects, tasks, files, expenses, manpower, or resources match "<strong x-text="query"></strong>".</p>
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
                <div class="topbar-right-group" style="margin-left: auto; display: flex; align-items: center; gap: var(--space-3);">

                    {{-- Notification Bell --}}
                    @if(Auth::user()->hasRole(['Admin', 'Manager']))
                    <div
                        class="notification-bell-wrap"
                        x-data="notificationBell()"
                        @click.outside="open = false"
                        @keydown.escape.window="open = false"
                    >
                        <button
                            type="button"
                            class="notification-bell-btn"
                            :class="{ 'notification-bell-btn--active': open }"
                            @click="toggle()"
                            :aria-expanded="open.toString()"
                            aria-haspopup="true"
                            aria-controls="notification-modal"
                            aria-label="Notifications"
                            title="Notifications"
                        >
                            <svg class="notification-bell-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                            </svg>
                            <span
                                class="notification-badge font-mono"
                                x-show="unreadCount > 0"
                                x-text="unreadCount > 99 ? '99+' : unreadCount"
                                x-cloak
                                aria-live="polite"
                            ></span>
                        </button>

                        {{-- Technical Dropdown Modal --}}
                        <div
                            id="notification-modal"
                            class="notification-modal"
                            x-show="open"
                            x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 transform scale-95"
                            x-transition:enter-end="opacity-100 transform scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 transform scale-100"
                            x-transition:leave-end="opacity-0 transform scale-95"
                            role="dialog"
                            aria-label="Notifications"
                        >
                            {{-- Modal Header --}}
                            <div class="notification-modal-header">
                                <div class="notification-header-top">
                                    <div style="display: flex; align-items: center; gap: var(--space-2);">
                                        <h3 class="notification-modal-title">Notifications</h3>
                                        <template x-if="unreadCount > 0">
                                            <span class="status-pill status-pill--ongoing font-mono" style="font-size: 0.6875rem; padding: 1px 6px;" x-text="unreadCount + ' Unread'"></span>
                                        </template>
                                    </div>
                                    <button
                                        type="button"
                                        class="notification-mark-all-link"
                                        @click="markAllRead()"
                                        x-show="activeTab !== 'archived' && unreadCount > 0"
                                        title="Mark all notifications as read"
                                    >
                                        Mark All Read
                                    </button>
                                </div>

                                {{-- Tab Switcher --}}
                                <div class="btn-group" role="tablist" style="display: flex; width: 100%; border: 1px solid var(--color-border); border-radius: var(--radius-sm); overflow: hidden; margin-top: var(--space-2);">
                                    <button
                                        type="button"
                                        class="btn btn-sm"
                                        :class="activeTab === 'all' ? 'btn-primary' : 'btn-secondary'"
                                        style="flex: 1; padding: 0.25rem 0.5rem; font-size: 0.75rem; border: none; border-radius: 0;"
                                        @click="switchTab('all')"
                                    >
                                        All (<span class="font-mono" x-text="activeNotifications.length"></span>)
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm"
                                        :class="activeTab === 'unread' ? 'btn-primary' : 'btn-secondary'"
                                        style="flex: 1; padding: 0.25rem 0.5rem; font-size: 0.75rem; border: none; border-radius: 0;"
                                        @click="switchTab('unread')"
                                    >
                                        Unread (<span class="font-mono" x-text="unreadCount"></span>)
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm"
                                        :class="activeTab === 'archived' ? 'btn-primary' : 'btn-secondary'"
                                        style="flex: 1; padding: 0.25rem 0.5rem; font-size: 0.75rem; border: none; border-radius: 0;"
                                        @click="switchTab('archived')"
                                    >
                                        Archived (<span class="font-mono" x-text="archivedNotifications.length"></span>)
                                    </button>
                                </div>
                            </div>

                            {{-- Modal Body --}}
                            <div class="notification-modal-body" x-ref="notificationList">
                                {{-- Loading Spinner --}}
                                <div x-show="loading" class="notification-loading-state">
                                    <div class="topbar-search-spinner"></div>
                                    <span class="notification-loading-text">Loading notifications...</span>
                                </div>

                                {{-- Empty State: All --}}
                                <div x-show="!loading && activeTab === 'all' && displayNotifications.length === 0" class="notification-empty-box">
                                    <p class="notification-empty-title">No notifications yet</p>
                                    <p class="notification-empty-subtitle">New project assignments, timeline tasks, and warranty alerts will appear here.</p>
                                </div>

                                {{-- Empty State: Unread --}}
                                <div x-show="!loading && activeTab === 'unread' && displayNotifications.length === 0" class="notification-empty-box">
                                    <p class="notification-empty-title">All caught up</p>
                                    <p class="notification-empty-subtitle">There are no unread notifications right now.</p>
                                </div>

                                {{-- Empty State: Archived --}}
                                <div x-show="!loading && activeTab === 'archived' && displayNotifications.length === 0" class="notification-empty-box">
                                    <p class="notification-empty-title">No archived items</p>
                                    <p class="notification-empty-subtitle">Notifications you archive will be kept here for historical reference.</p>
                                </div>

                                {{-- Notification Items List --}}
                                <div x-show="!loading && displayNotifications.length > 0" class="notification-cards-list">
                                    <template x-for="notification in displayNotifications" :key="notification.id">
                                        <div
                                            class="notification-card-item"
                                            :class="{ 'notification-card-item--unread': !notification.is_read && activeTab !== 'archived' }"
                                        >
                                            <div class="notification-card-main">
                                                <div class="notification-card-meta-row">
                                                    {{-- Status Pill indicating event type --}}
                                                    <template x-if="notification.type === 'warranty_expiry'">
                                                        <span class="status-pill status-pill--ongoing">Warranty</span>
                                                    </template>
                                                    <template x-if="notification.type === 'new_project'">
                                                        <span class="status-pill status-pill--completed">New Project</span>
                                                    </template>
                                                    <template x-if="notification.type === 'new_task'">
                                                        <span class="status-pill status-pill--pending">Task Logged</span>
                                                    </template>

                                                    <span class="notification-card-time font-mono" x-text="notification.time_ago"></span>
                                                </div>

                                                <a
                                                    :href="notification.url || 'javascript:void(0)'"
                                                    class="notification-card-link-block"
                                                    @click="onNotificationClick(notification)"
                                                >
                                                    <div class="notification-card-title" x-text="notification.title"></div>
                                                    <div class="notification-card-msg" x-text="notification.message"></div>
                                                </a>

                                                <div class="notification-card-bottom-row">
                                                    <div class="notification-status-indicator">
                                                        <template x-if="!notification.is_read && activeTab !== 'archived'">
                                                            <span class="status-pill status-pill--pending" style="font-size: 0.6875rem; padding: 1px 6px;">Unread</span>
                                                        </template>
                                                    </div>

                                                    <div class="notification-card-actions">
                                                        <button
                                                            type="button"
                                                            class="notification-text-action"
                                                            x-show="!notification.is_read && activeTab !== 'archived'"
                                                            @click.stop="markRead(notification)"
                                                        >
                                                            Mark Read
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="notification-text-action"
                                                            x-show="activeTab !== 'archived'"
                                                            @click.stop="archiveNotification(notification)"
                                                        >
                                                            Archive
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="notification-text-action notification-text-action--restore"
                                                            x-show="activeTab === 'archived'"
                                                            @click.stop="unarchiveNotification(notification)"
                                                        >
                                                            Restore
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Modal Footer --}}
                            <div class="notification-modal-footer">
                                <span class="font-mono" x-text="displayNotifications.length + ' record' + (displayNotifications.length === 1 ? '' : 's')"></span>
                                <a href="{{ route('projects.index') }}" class="notification-footer-link">Go to Projects →</a>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- User menu --}}
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
                </div>{{-- end .topbar-right-group --}}
            </header>

            {{-- Global Toast Notifications (Centered auto-dismissing floating alert) --}}
            @if (session('status') || session('success') || session('error') || session('warning'))
                <div
                    style="position: fixed; top: 1.5rem; left: 0; right: 0; display: flex; flex-direction: column; align-items: center; gap: 0.75rem; z-index: 99999; pointer-events: none; padding: 0 1rem;"
                    role="region"
                    aria-label="Notification alerts"
                >
                    @if (session('status') || session('success'))
                        <div
                            x-data="{ show: true }"
                            x-show="show"
                            x-init="setTimeout(() => show = false, 5000)"
                            x-transition.duration.300ms
                            style="pointer-events: auto; width: 100%; max-width: 440px;"
                            role="status"
                            aria-live="polite"
                        >
                            <div style="display: flex; align-items: flex-start; gap: 0.75rem; background: #064e3b; color: #ecfdf5; padding: 1rem 1.25rem; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.1); border: 1px solid #059669;">
                                <span style="display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; background: #10b981; border-radius: 50%; color: #ffffff; flex-shrink: 0; font-size: 13px; font-weight: bold;">
                                    &#10003;
                                </span>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; font-size: 0.925rem; margin-bottom: 0.15rem; color: #ffffff;">Success</div>
                                    <div style="font-size: 0.85rem; color: #d1fae5; line-height: 1.4;">{{ session('status') ?? session('success') }}</div>
                                </div>
                                <button
                                    type="button"
                                    @click="show = false"
                                    style="background: transparent; border: none; color: #a7f3d0; cursor: pointer; padding: 0.2rem; font-size: 1.25rem; line-height: 1;"
                                    aria-label="Close notification"
                                >
                                    &times;
                                </button>
                            </div>
                        </div>
                    @endif

                    @if (session('error'))
                        <div
                            x-data="{ show: true }"
                            x-show="show"
                            x-init="setTimeout(() => show = false, 6000)"
                            x-transition.duration.300ms
                            style="pointer-events: auto; width: 100%; max-width: 440px;"
                            role="alert"
                            aria-live="assertive"
                        >
                            <div style="display: flex; align-items: flex-start; gap: 0.75rem; background: #7f1d1d; color: #fef2f2; padding: 1rem 1.25rem; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.1); border: 1px solid #dc2626;">
                                <span style="display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; background: #ef4444; border-radius: 50%; color: #ffffff; flex-shrink: 0; font-size: 13px; font-weight: bold;">
                                    &times;
                                </span>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; font-size: 0.925rem; margin-bottom: 0.15rem; color: #ffffff;">Error</div>
                                    <div style="font-size: 0.85rem; color: #fee2e2; line-height: 1.4;">{{ session('error') }}</div>
                                </div>
                                <button
                                    type="button"
                                    @click="show = false"
                                    style="background: transparent; border: none; color: #fca5a5; cursor: pointer; padding: 0.2rem; font-size: 1.25rem; line-height: 1;"
                                    aria-label="Close notification"
                                >
                                    &times;
                                </button>
                            </div>
                        </div>
                    @endif

                    @if (session('warning'))
                        <div
                            x-data="{ show: true }"
                            x-show="show"
                            x-init="setTimeout(() => show = false, 6000)"
                            x-transition.duration.300ms
                            style="pointer-events: auto; width: 100%; max-width: 440px;"
                            role="alert"
                            aria-live="polite"
                        >
                            <div style="display: flex; align-items: flex-start; gap: 0.75rem; background: #78350f; color: #fffbeb; padding: 1rem 1.25rem; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.1); border: 1px solid #d97706;">
                                <span style="display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; background: #f59e0b; border-radius: 50%; color: #ffffff; flex-shrink: 0; font-size: 13px; font-weight: bold;">
                                    !
                                </span>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; font-size: 0.925rem; margin-bottom: 0.15rem; color: #ffffff;">Notice</div>
                                    <div style="font-size: 0.85rem; color: #fef3c7; line-height: 1.4;">{{ session('warning') }}</div>
                                </div>
                                <button
                                    type="button"
                                    @click="show = false"
                                    style="background: transparent; border: none; color: #fde68a; cursor: pointer; padding: 0.2rem; font-size: 1.25rem; line-height: 1;"
                                    aria-label="Close notification"
                                >
                                    &times;
                                </button>
                            </div>
                        </div>
                    @endif
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
                results: {
                    projects: [],
                    manpower: [],
                    resources: [],
                    major_tasks: [],
                    specific_tasks: [],
                    files: [],
                    expenses: [],
                    total: 0
                },
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

                getItemIndex(category, idx) {
                    const order = ['projects', 'manpower', 'resources', 'major_tasks', 'specific_tasks', 'files', 'expenses'];
                    let offset = 0;
                    for (const cat of order) {
                        if (cat === category) {
                            return offset + idx;
                        }
                        offset += (this.results[cat] ? this.results[cat].length : 0);
                    }
                    return idx;
                },

                async performSearch() {
                    const q = this.query.trim();
                    if (q.length < 1) {
                        this.results = {
                            projects: [],
                            manpower: [],
                            resources: [],
                            major_tasks: [],
                            specific_tasks: [],
                            files: [],
                            expenses: [],
                            total: 0
                        };
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
                        ...(this.results.resources || []).map(item => ({ ...item, category: 'resource' })),
                        ...(this.results.major_tasks || []).map(item => ({ ...item, category: 'major_task' })),
                        ...(this.results.specific_tasks || []).map(item => ({ ...item, category: 'specific_task' })),
                        ...(this.results.files || []).map(item => ({ ...item, category: 'file' })),
                        ...(this.results.expenses || []).map(item => ({ ...item, category: 'expense' }))
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

        /**
         * Notification Bell — Alpine.js component for the topbar bell icon & drop-down modal.
         *
         * Fetches notifications via JSON API, supports tabs (All / Unread / Archived),
         * mark-as-read, archive, and unarchive. Polls every 60 seconds.
         */
        function notificationBell() {
            return {
                open: false,
                loading: false,
                activeTab: 'all', // 'all', 'unread', 'archived'
                activeNotifications: [],
                archivedNotifications: [],
                unreadCount: 0,
                pollInterval: null,

                init() {
                    this.fetchUnreadCount();
                    this.pollInterval = setInterval(() => {
                        if (!this.open) {
                            this.fetchUnreadCount();
                        }
                    }, 60000);
                },

                destroy() {
                    if (this.pollInterval) clearInterval(this.pollInterval);
                },

                toggle() {
                    this.open = !this.open;
                    if (this.open) {
                        if (this.activeTab === 'archived') {
                            this.fetchArchived();
                        } else {
                            this.fetchActive();
                        }
                    }
                },

                get displayNotifications() {
                    if (this.activeTab === 'archived') {
                        return this.archivedNotifications;
                    }
                    if (this.activeTab === 'unread') {
                        return this.activeNotifications.filter(n => !n.is_read);
                    }
                    return this.activeNotifications;
                },

                async switchTab(tab) {
                    this.activeTab = tab;
                    if (tab === 'archived') {
                        await this.fetchArchived();
                    } else if (this.activeNotifications.length === 0) {
                        await this.fetchActive();
                    }
                },

                async fetchActive() {
                    this.loading = true;
                    try {
                        const response = await fetch('{{ route("notifications.index") }}?filter=active', {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (response.ok) {
                            const data = await response.json();
                            this.activeNotifications = data.notifications;
                            this.unreadCount = data.unread_count;
                        }
                    } catch (err) {
                        console.error('Active notifications fetch error:', err);
                    } finally {
                        this.loading = false;
                    }
                },

                async fetchArchived() {
                    this.loading = true;
                    try {
                        const response = await fetch('{{ route("notifications.index") }}?filter=archived', {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (response.ok) {
                            const data = await response.json();
                            this.archivedNotifications = data.notifications;
                        }
                    } catch (err) {
                        console.error('Archived notifications fetch error:', err);
                    } finally {
                        this.loading = false;
                    }
                },

                async fetchUnreadCount() {
                    try {
                        const response = await fetch('{{ route("notifications.unread-count") }}', {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (response.ok) {
                            const data = await response.json();
                            this.unreadCount = data.unread_count;
                        }
                    } catch (err) {
                        // Silent fail — poll will retry
                    }
                },

                async onNotificationClick(notification) {
                    if (!notification.is_read) {
                        await this.markRead(notification);
                    }
                    if (notification.url && notification.url !== '#' && !notification.url.startsWith('javascript:')) {
                        window.location.href = notification.url;
                    }
                },

                async markRead(notification) {
                    if (notification.is_read) return;
                    try {
                        const response = await fetch(`/notifications/${notification.id}/read`, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (response.ok) {
                            notification.is_read = true;
                            this.unreadCount = Math.max(0, this.unreadCount - 1);
                        }
                    } catch (err) {
                        console.error('Mark read error:', err);
                    }
                },

                async markAllRead() {
                    try {
                        const response = await fetch('{{ route("notifications.mark-all-read") }}', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (response.ok) {
                            this.activeNotifications.forEach(n => n.is_read = true);
                            this.unreadCount = 0;
                        }
                    } catch (err) {
                        console.error('Mark all read error:', err);
                    }
                },

                async archiveNotification(notification) {
                    try {
                        const response = await fetch(`/notifications/${notification.id}/archive`, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (response.ok) {
                            if (!notification.is_read) {
                                this.unreadCount = Math.max(0, this.unreadCount - 1);
                            }
                            this.activeNotifications = this.activeNotifications.filter(n => n.id !== notification.id);
                        }
                    } catch (err) {
                        console.error('Archive error:', err);
                    }
                },

                async unarchiveNotification(notification) {
                    try {
                        const response = await fetch(`/notifications/${notification.id}/unarchive`, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (response.ok) {
                            this.archivedNotifications = this.archivedNotifications.filter(n => n.id !== notification.id);
                            this.activeNotifications.unshift(notification);
                        }
                    } catch (err) {
                        console.error('Unarchive error:', err);
                    }
                }
            };
        }
    </script>

    <script src="{{ asset('js/philippine-address.js') }}"></script>
    @stack('scripts')
</body>
</html>
