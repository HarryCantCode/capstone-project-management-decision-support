<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Login') — Dex International Co.</title>
    <meta name="description" content="Dex International Co. Project Management System — sign in to continue.">

    {{-- Google Fonts: Inter for body text --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

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
</head>
<body class="auth-body h-full">

    <div class="auth-wrapper">
        <div class="auth-card" role="main">

            {{-- Wordmark --}}
            <div class="auth-brand" aria-label="Dex PMS">
                <svg class="auth-brand-icon" width="32" height="32" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                    <rect width="32" height="32" rx="6" fill="var(--color-accent)"/>
                    <path d="M8 10h8a6 6 0 0 1 0 12H8V10z" fill="white"/>
                    <rect x="19" y="17" width="5" height="5" rx="1" fill="white" opacity="0.7"/>
                </svg>
                <span class="auth-brand-name">Dex International Co.</span>
            </div>

            @yield('content')

        </div>

        <p class="auth-footer">
            Dex International Co. &mdash; Internal use only
        </p>
    </div>

</body>
</html>
