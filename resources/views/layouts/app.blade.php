<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'SDM Workspace') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div data-app-shell x-data="appShell" @keydown.escape.window="mobileOpen = false" class="min-h-screen bg-gray-50 dark:bg-gray-900">
            <div :class="collapsed ? 'md:pl-20' : 'md:pl-72'" class="min-h-screen transition-all duration-300">
                @include('layouts.navigation')

                @isset($header)
                    <div class="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-5 sm:px-6 lg:px-8">
                            <div data-page-heading class="min-w-0 flex-1">{{ $header }}</div>
                            @unless(request()->routeIs('dashboard'))
                                <a data-global-back href="{{ $backUrl }}" aria-label="Back" class="inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-gray-200 px-3 text-sm font-semibold text-gray-600 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:text-gray-300 dark:hover:border-emerald-700 dark:hover:bg-emerald-950 dark:hover:text-emerald-300">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 18-6-6 6-6" /></svg>
                                    <span class="hidden sm:inline">Back</span>
                                </a>
                            @endunless
                        </div>
                    </div>
                @endisset

                <main>{{ $slot }}</main>
            </div>
        </div>
    </body>
</html>
