<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ __('Sign in') }} · SDM</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
        <main
            class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-100 px-4 py-6 sm:px-6 lg:py-10"
            x-data="{ forgotPasswordOpen: false }"
            @keydown.escape.window="forgotPasswordOpen = false"
        >
            <div class="pointer-events-none absolute -left-20 top-10 h-72 w-72 rounded-full bg-emerald-200/40 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-24 right-0 h-80 w-80 rounded-full bg-sky-200/40 blur-3xl" aria-hidden="true"></div>

            <section class="relative grid w-full max-w-6xl overflow-hidden rounded-[2rem] border border-white/80 bg-white shadow-2xl shadow-slate-300/60 lg:grid-cols-2">
                <aside class="relative overflow-hidden bg-gradient-to-br from-[#d5ebe8] via-[#a4d1cb] to-[#68afa6] px-6 py-8 text-slate-900 sm:px-10 sm:py-10 lg:flex lg:min-h-[720px] lg:flex-col lg:justify-between lg:px-12 lg:py-12">
                    <div class="pointer-events-none absolute -right-32 -top-28 h-80 w-80 rounded-full border-[48px] border-white/25" aria-hidden="true"></div>
                    <div class="pointer-events-none absolute -bottom-24 -left-20 h-64 w-64 rounded-full bg-[#0f766e]/20 blur-3xl" aria-hidden="true"></div>

                    <div class="relative z-10">
                        <a href="/" class="inline-flex rounded-2xl bg-white px-4 py-3 shadow-lg shadow-emerald-900/10 transition hover:bg-emerald-50 focus:outline-none focus:ring-4 focus:ring-[#0f766e]/20" aria-label="SDM home">
                            <img src="{{ asset('images/dmrc-logo.svg') }}" alt="Delhi Metro Rail Corporation" class="h-auto w-36 sm:w-40">
                        </a>

                        <div class="mt-10 max-w-md lg:mt-20">
                            <p class="text-xs font-bold uppercase tracking-[0.24em] text-[#0b5f59]">Digitized Service Deficiency Memo</p>
                            <h1 class="mt-4 text-3xl font-bold leading-tight tracking-[-0.03em] text-slate-950 sm:text-4xl lg:text-5xl">Service Deficiency Management</h1>
                            <p class="mt-5 text-[15px] leading-7 text-slate-700 sm:text-base">A secure workspace for creating, reviewing, and tracking service deficiency memos across DMRC operations.</p>
                        </div>
                    </div>

                    <div class="relative z-10 mt-10 hidden gap-4 sm:grid sm:grid-cols-2 lg:grid-cols-1">
                        <div class="flex items-start gap-3 rounded-2xl border border-white/50 bg-white/65 p-4 shadow-sm shadow-emerald-900/10 backdrop-blur-sm">
                            <span class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#0f766e]/10 text-[#0f766e]" aria-hidden="true">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 6h8M8 10h8M8 14h5" stroke-linecap="round"/><path d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-slate-900">One connected workflow</p>
                                <p class="mt-1 text-sm leading-6 text-slate-700">From memo creation and approvals through penalty tracking and closure.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 rounded-2xl border border-white/50 bg-white/65 p-4 shadow-sm shadow-emerald-900/10 backdrop-blur-sm">
                            <span class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#0f766e]/10 text-[#0f766e]" aria-hidden="true">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.6 2.9 8.3 7 10 4.1-1.7 7-5.4 7-10V6l-7-3Z" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Clear accountability</p>
                                <p class="mt-1 text-sm leading-6 text-slate-700">Role-based actions and auditable records keep every decision traceable.</p>
                            </div>
                        </div>
                    </div>
                </aside>

                <div class="flex flex-col px-6 py-8 sm:px-10 sm:py-10 lg:px-14 lg:py-12">
                    <div class="w-full max-w-md self-center lg:my-auto">
                    <div class="mb-9">
                        <p class="mb-3 text-sm font-semibold text-[#0f766e]">Welcome back</p>
                        <h2 class="text-3xl font-bold tracking-[-0.025em] text-slate-950 sm:text-4xl">Sign in to your account</h2>
                        {{-- <p class="mt-3 text-[15px] leading-6 text-slate-500">Enter your work credentials to continue to the SDM workspace.</p> --}}
                    </div>

                    <x-auth-session-status class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" :status="session('status')" />

                    <form method="POST" action="{{ route('login') }}" class="space-y-5" x-data="{ showPassword: false }">
                        @csrf

                        <div>
                            <label for="username" class="mb-2 block text-sm font-semibold text-slate-700">Username <span data-required-marker aria-hidden="true" class="font-bold !text-red-600">*</span><span class="sr-only"> (required)</span></label>
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" stroke-linecap="round" /></svg>
                                <input id="username" class="block h-[52px] w-full rounded-xl border border-slate-200 bg-white py-3.5 pl-12 pr-4 text-[15px] text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100" type="text" name="username" value="{{ old('username') }}" placeholder="Enter your username" required autofocus autocomplete="username" />
                            </div>
                            <x-input-error :messages="$errors->get('username')" class="mt-2 text-sm" />
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <label for="password" class="block text-sm font-semibold text-slate-700">Password <span data-required-marker aria-hidden="true" class="font-bold !text-red-600">*</span><span class="sr-only"> (required)</span></label>
                                @if (Route::has('password.request'))
                                    <button type="button" class="rounded text-sm font-semibold text-[#0f766e] transition hover:text-[#115e59] focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2" @click="forgotPasswordOpen = true">Forgot password?</button>
                                @endif
                            </div>
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke-linecap="round" /></svg>
                                <input id="password" class="block h-[52px] w-full rounded-xl border border-slate-200 bg-white py-3.5 pl-12 pr-12 text-[15px] text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100" :type="showPassword ? 'text' : 'password'" name="password" placeholder="Enter your password" required autocomplete="current-password" />
                                <button type="button" class="absolute right-4 top-1/2 -translate-y-1/2 rounded-md p-1 text-slate-400 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500" @click="showPassword = ! showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'">
                                    <svg x-show="! showPassword" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" stroke-linecap="round" stroke-linejoin="round" /><circle cx="12" cy="12" r="2.5" /></svg>
                                    <svg x-cloak x-show="showPassword" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m3 3 18 18M10.6 10.7a2 2 0 0 0 2.7 2.7M9.9 4.2A11 11 0 0 1 12 4c6.5 0 10 8 10 8a16 16 0 0 1-2.2 3.2M6.2 6.2C3.5 8 2 12 2 12s3.5 8 10 8a9.5 9.5 0 0 0 4-.9" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                </button>
                            </div>
                            <x-input-error :messages="$errors->get('password')" class="mt-2 text-sm" />
                        </div>

                        <div>
                            <label for="station_id" class="mb-2 block text-sm font-semibold text-slate-700">Station <span class="font-normal text-slate-500">(required for Station Controller and Station Manager)</span></label>
                            <select id="station_id" name="station_id" class="block h-[52px] w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-[15px] text-slate-900 shadow-sm outline-none transition hover:border-slate-300 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100">
                                <option value="">Select station</option>
                                @foreach($stations as $station)
                                    <option value="{{ $station->id }}" @selected((string) old('station_id') === (string) $station->id)>{{ $station->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('station_id')" class="mt-2 text-sm" />
                        </div>

                        <label for="remember_me" class="flex w-fit cursor-pointer items-center gap-3 text-sm text-slate-600">
                            <input id="remember_me" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#0f766e] focus:ring-emerald-500" name="remember">
                            <span>Keep me signed in</span>
                        </label>

                        <button type="submit" class="group flex w-full items-center justify-center gap-2 rounded-xl bg-[#0f766e] px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-emerald-900/15 transition hover:bg-[#115e59] hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-emerald-200 active:translate-y-px">
                            Sign in
                            <svg class="h-4 w-4 transition group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </button>
                    </form>

                    </div>

                    <p class="mt-10 text-center text-xs text-slate-400">Authorized users only · Activity may be monitored for security.</p>
                </div>
            </section>

            <div
                x-cloak
                x-show="forgotPasswordOpen"
                x-transition.opacity
                class="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 px-4 py-6 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                aria-labelledby="forgot-password-title"
                @click.self="forgotPasswordOpen = false"
            >
                <div x-show="forgotPasswordOpen" x-transition class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl sm:p-7">
                    <div class="flex items-start justify-between gap-4">
                        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-emerald-100 text-[#0f766e]" aria-hidden="true">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v14H4z" stroke-linejoin="round"/><path d="m4 7 8 6 8-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500" aria-label="Close password help" @click="forgotPasswordOpen = false">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round"/></svg>
                        </button>
                    </div>

                    <h2 id="forgot-password-title" class="mt-5 text-xl font-bold text-slate-950">Password assistance</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Please contact Ops IT Cell - 110426 or send an email to itcellops@dmrc.org</p>

                    <button type="button" class="mt-6 flex w-full items-center justify-center rounded-xl bg-[#0f766e] px-4 py-3 text-sm font-bold text-white transition hover:bg-[#115e59] focus:outline-none focus:ring-4 focus:ring-emerald-200" @click="forgotPasswordOpen = false">Close</button>
                </div>
            </div>
        </main>
    </body>
</html>
