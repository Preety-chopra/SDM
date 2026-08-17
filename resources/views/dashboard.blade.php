<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">Dashboard</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Welcome back, {{ auth()->user()->name }}.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if ($canManageUsers)
                <div class="mb-6 flex flex-col gap-1">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">User management</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Review the availability of each managed user group.</p>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
                    @foreach ($roles as $role)
                        @php($counts = $roleCounts[$role->value])
                        <a href="{{ route('users.index', $role->value) }}" class="group rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-700 dark:focus:ring-offset-gray-900">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $role->pluralLabel() }}</p>
                                    <p class="mt-2 text-3xl font-bold text-gray-950 dark:text-white">{{ $counts['total'] }}</p>
                                </div>
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-sm font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{{ mb_substr($role->label(), 0, 1) }}</span>
                            </div>
                            <div class="mt-6 grid grid-cols-2 gap-3 border-t border-gray-100 pt-4 text-sm dark:border-gray-700">
                                <div><span class="block text-gray-500 dark:text-gray-400">Enabled</span><strong class="text-emerald-700 dark:text-emerald-400">{{ $counts['enabled'] }}</strong></div>
                                <div><span class="block text-gray-500 dark:text-gray-400">Disabled</span><strong class="text-rose-700 dark:text-rose-400">{{ $counts['disabled'] }}</strong></div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">You are logged in</h3>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">Your SDM workspace is ready. Use the navigation above to access available features.</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
