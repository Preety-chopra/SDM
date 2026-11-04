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
            <div
                @if ($showSidebar) :class="collapsed ? 'md:pl-20' : 'md:pl-72'" @endif
                @class(['min-h-screen', 'transition-all duration-300' => $showSidebar])
            >
                @include('layouts.navigation', ['showSidebar' => $showSidebar])

                @isset($header)
                    <div class="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-5 sm:px-6 lg:px-8">
                            <div data-page-heading class="min-w-0 flex-1">{{ $header }}</div>
                            @if ($showBackButton)
                                <a data-global-back href="{{ $backUrl }}" aria-label="Back" class="inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-gray-200 px-3 text-sm font-semibold text-gray-600 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:text-gray-300 dark:hover:border-emerald-700 dark:hover:bg-emerald-950 dark:hover:text-emerald-300">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 18-6-6 6-6" /></svg>
                                    <span class="hidden sm:inline">Back</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @endisset

                <main>{{ $slot }}</main>
            </div>
        </div>

        <x-feedback-dialog />

        @if(request()->routeIs('sdm-memos.*'))
            <x-sdm-action-feedback />
        @endif

        @if(session('pending_sdm_summary'))
            <x-modal name="pending-sdm-summary" :show="true" maxWidth="lg" focusable>
                <section role="dialog" aria-modal="true" aria-labelledby="pending-sdm-title" aria-describedby="pending-sdm-description" class="p-6 sm:p-7">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-700 dark:text-amber-400">Your pending queue</p>
                            <h2 id="pending-sdm-title" class="mt-1 text-xl font-black text-gray-950 dark:text-white">Pending SDMs awaiting your action</h2>
                            <p id="pending-sdm-description" class="mt-1 text-sm text-gray-600 dark:text-gray-300">Only SDMs pending at your level. Urgent SDMs are included in the total.</p>
                        </div>
                        <button type="button" x-on:click="$dispatch('close')" aria-label="Close pending SDMs" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-300 text-xl font-bold text-gray-600 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">&times;</button>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <a href="{{ route('sdm-memos.index', ['category' => 'pending_action']) }}" class="block rounded-xl border border-gray-200 bg-gray-50 p-4 transition hover:border-emerald-400 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-emerald-600 dark:hover:bg-emerald-950 dark:focus:ring-offset-gray-800">
                            <dl>
                                <dt class="text-sm font-semibold text-gray-600 dark:text-gray-300">Pending SDMs</dt>
                                <dd class="mt-2 text-3xl font-black text-gray-950 dark:text-white">{{ session('pending_sdm_summary.total') }}</dd>
                            </dl>
                        </a>
                        <a href="{{ route('sdm-memos.index', ['category' => 'urgent_pending_action']) }}" class="block rounded-xl border border-red-200 bg-red-50 p-4 transition hover:border-red-400 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:border-red-900 dark:bg-red-950/40 dark:hover:border-red-700 dark:hover:bg-red-950 dark:focus:ring-offset-gray-800">
                            <dl>
                                <dt class="text-sm font-semibold text-red-700 dark:text-red-300">Urgent SDMs</dt>
                                <dd class="mt-2 text-3xl font-black text-red-700 dark:text-red-300">{{ session('pending_sdm_summary.urgent') }}</dd>
                            </dl>
                        </a>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="button" x-on:click="$dispatch('close')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Dismiss</button>
                    </div>
                </section>
            </x-modal>
        @endif
    </body>
</html>
