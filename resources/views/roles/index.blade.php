<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">Roles &amp; permissions</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Define user types and the actions each type can perform.</p>
            </div>
            <a href="{{ route('roles.create') }}" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">Create role</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>
            @endif

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($roles as $role)
                    <article class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ \App\Support\RoleLabel::singular($role->name) }}</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $role->users_count }} {{ \Illuminate\Support\Str::plural('user', $role->users_count) }}</p>
                            </div>
                            <a href="{{ route('roles.edit', $role) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit</a>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($role->permissions as $permission)
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ $permission->name }}</span>
                            @empty
                                <span class="text-sm text-gray-500 dark:text-gray-400">No permissions assigned.</span>
                            @endforelse
                        </div>
                        <a href="{{ route('users.create', $role->name) }}" class="mt-auto text-sm font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-300">Create a {{ \App\Support\RoleLabel::singular($role->name) }} user</a>
                    </article>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
