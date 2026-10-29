<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Sign In') &mdash; Dex International Co.</title>
    <meta name="description" content="Dex International Co. Project Management System &mdash; sign in to continue.">

    {{-- Google Fonts: Inter for clean UI, Roboto Mono for numbers/tags --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto+Mono:wght@500&display=swap" rel="stylesheet">

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
<body class="auth-body h-full" x-data="{
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

    {{-- Top bar with subtle theme switcher --}}
    <header class="auth-topbar">
        <button
            type="button"
            class="auth-theme-btn"
            @click="toggleTheme()"
            :aria-label="darkMode ? 'Switch to light theme' : 'Switch to dark theme'"
            :title="darkMode ? 'Switch to light theme' : 'Switch to dark theme'"
        >
            <span x-show="!darkMode" class="d-inline-flex align-items-center gap-1">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                <span>Dark Mode</span>
            </span>
            <span x-show="darkMode" class="d-inline-flex align-items-center gap-1" style="display: none;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                <span>Light Mode</span>
            </span>
        </button>
    </header>

    {{-- Main Container Card with 50/50 vertical split --}}
    <main class="auth-container">
        <div class="auth-card-split" role="main">

            {{-- Left Column: Pictures in Slideshow --}}
            <div class="auth-slideshow-pane" aria-label="Dex International Co. Visual Showcase">
                @if (View::hasSection('auth-media'))
                    @yield('auth-media')
                @else
                    @include('auth.partials.slideshow')
                @endif
            </div>

            {{-- Right Column: Login Fields & Brand --}}
            <div class="auth-form-pane">
                <div class="auth-form-content">
                    {{-- Wordmark / Brand --}}
                    <div class="auth-brand" aria-label="Dex PMS">
                        <svg class="auth-brand-icon" width="34" height="34" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                            <rect width="32" height="32" rx="7" fill="var(--color-accent)"/>
                            <path d="M8 10h8a6 6 0 0 1 0 12H8V10z" fill="white"/>
                            <rect x="19" y="17" width="5" height="5" rx="1.5" fill="white" opacity="0.8"/>
                        </svg>
                        <div class="auth-brand-text">
                            <span class="auth-brand-name">Dex International Co.</span>
                            <span class="auth-brand-badge">Project Management System</span>
                        </div>
                    </div>

                    @yield('content')

                    <footer class="auth-footer-bar">
                        <p class="auth-footer-note">&copy; {{ date('Y') }} Dex International Co. &bull; Internal System</p>
                    </footer>
                </div>
            </div>

        </div>
    </main>

    @stack('scripts')
</body>
</html>
