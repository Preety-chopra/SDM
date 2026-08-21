<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'SDM Workspace') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-50 font-sans antialiased dark:bg-gray-900">
        <main class="grid min-h-screen place-items-center px-6 py-12">
            <div class="w-full max-w-lg rounded-3xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-12">
                <x-application-logo class="mx-auto h-20 w-24" />
                <h1 class="mt-6 text-3xl font-black tracking-tight text-gray-950 dark:text-white">SDM Workspace</h1>
                <p class="mt-3 text-gray-600 dark:text-gray-300">Create, review, and manage service deficiency memos from one focused workspace.</p>
                <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="mt-8 inline-flex items-center justify-center rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                    {{ auth()->check() ? 'Open dashboard' : 'Sign in' }}
                </a>
            </div>
        </main>
    </body>
</html>
