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
    <body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
        <main class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-8 sm:px-6">
            <section class="flex w-full max-w-xl flex-col rounded-3xl border border-slate-200/80 bg-white px-6 py-8 shadow-xl shadow-slate-200/60 sm:px-12 sm:py-10">
                <div class="flex items-center justify-between gap-6">
                    <a href="/" class="inline-flex items-center gap-2" aria-label="SDM home">
                        {{-- <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#0f766e] font-bold text-white">S</span> --}}
                        {{-- <span class="text-lg font-bold tracking-tight">SDM</span> --}}
                    </a>
                    {{-- <p class="text-sm text-slate-500">
                        Need help?
                        <a href="mailto:support@example.com" class="font-semibold text-[#0f766e] transition hover:text-[#115e59] focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">Contact support</a>
                    </p> --}}
                </div>

                <div class="mt-10 w-full max-w-md self-center py-4 sm:py-6">
                    <div class="mb-9">
                        <p class="mb-3 text-sm font-semibold text-[#0f766e]">Welcome back</p>
                        <h2 class="text-3xl font-bold tracking-[-0.025em] text-slate-950 sm:text-4xl">Sign in to your account</h2>
                        {{-- <p class="mt-3 text-[15px] leading-6 text-slate-500">Enter your work credentials to continue to the SDM workspace.</p> --}}
                    </div>

                    <x-auth-session-status class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" :status="session('status')" />

                    <form method="POST" action="{{ route('login') }}" class="space-y-5" x-data="{ showPassword: false }">
                        @csrf

                        <div>
                            <label for="username" class="mb-2 block text-sm font-semibold text-slate-700">Username</label>
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" stroke-linecap="round" /></svg>
                                <input id="username" class="block h-[52px] w-full rounded-xl border border-slate-200 bg-white py-3.5 pl-12 pr-4 text-[15px] text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100" type="text" name="username" value="{{ old('username') }}" placeholder="Enter your username" required autofocus autocomplete="username" />
                            </div>
                            <x-input-error :messages="$errors->get('username')" class="mt-2 text-sm" />
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <label for="password" class="block text-sm font-semibold text-slate-700">Password</label>
                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="rounded text-sm font-semibold text-[#0f766e] transition hover:text-[#115e59] focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">Forgot password?</a>
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

                        <label for="remember_me" class="flex w-fit cursor-pointer items-center gap-3 text-sm text-slate-600">
                            <input id="remember_me" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#0f766e] focus:ring-emerald-500" name="remember">
                            <span>Keep me signed in</span>
                        </label>

                        <button type="submit" class="group flex w-full items-center justify-center gap-2 rounded-xl bg-[#0f766e] px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-emerald-900/15 transition hover:bg-[#115e59] hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-emerald-200 active:translate-y-px">
                            Sign in
                            <svg class="h-4 w-4 transition group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </button>
                    </form>

                    <div class="mt-8 flex items-center gap-3 text-xs text-slate-400">
                        <span class="h-px flex-1 bg-slate-200"></span>
                        {{-- <span class="inline-flex items-center gap-1.5">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            Protected workspace
                        </span> --}}
                        <span class="h-px flex-1 bg-slate-200"></span>
                    </div>
                </div>

                <p class="mt-8 text-center text-xs text-slate-400">Authorized users only · Activity may be monitored for security.</p>
            </section>
        </main>
    </body>
</html>
