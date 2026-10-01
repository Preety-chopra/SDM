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

        @if(session('urgent_attention_memos'))
            <x-modal name="urgent-attention" :show="true" maxWidth="lg" focusable>
                <section role="dialog" aria-modal="true" aria-labelledby="urgent-attention-title" class="p-6">
                    <div class="flex items-start gap-4">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-700 animate-pulse dark:bg-red-950 dark:text-red-300" aria-hidden="true">!</span>
                        <div class="min-w-0 flex-1">
                            <h2 id="urgent-attention-title" class="text-xl font-bold text-red-700 dark:text-red-300">Urgent attention required</h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">The following urgent SDM {{ count(session('urgent_attention_memos')) === 1 ? 'is' : 'are' }} awaiting your action.</p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-col gap-3">
                        @foreach(session('urgent_attention_memos') as $urgentMemo)
                            <a href="{{ route('sdm-memos.show', $urgentMemo['id']) }}" class="flex items-center justify-between gap-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 transition hover:border-red-400 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 dark:border-red-900 dark:bg-red-950/40 dark:hover:border-red-700">
                                <span class="min-w-0">
                                    <span class="flex flex-wrap items-center gap-2 font-semibold text-gray-950 dark:text-white"><span>{{ $urgentMemo['serial_number'] }}</span><span class="rounded-full bg-red-600 px-2 py-0.5 text-[0.65rem] font-bold uppercase tracking-wide text-white animate-pulse dark:bg-red-500">Urgent</span></span>
                                    <span class="mt-1 block text-xs text-gray-600 dark:text-gray-300">{{ $urgentMemo['service_type'] }} · {{ $urgentMemo['station_name'] }}</span>
                                </span>
                                <span class="shrink-0 font-semibold text-red-700 dark:text-red-300">Open →</span>
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="button" x-on:click="$dispatch('close')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-500 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Dismiss</button>
                    </div>
                </section>
            </x-modal>
        @endif
    </body>
</html>
