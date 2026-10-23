<x-app-layout title="Roles & permissions">
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

            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-200">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($roles as $role)
                    <article class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ \App\Support\RoleLabel::singular($role->name) }}</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $role->users_count }} {{ \Illuminate\Support\Str::plural('user', $role->users_count) }}</p>
                            </div>
                            <div x-data="{ confirmingDelete: false }" class="flex flex-wrap items-center justify-end gap-2">
                                <a x-show="! confirmingDelete" href="{{ route('roles.edit', $role) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit</a>
                                @if ($role->users_count === 0)
                                    <button x-show="! confirmingDelete" type="button" @click="confirmingDelete = true" class="rounded-lg border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-950/40">Delete role</button>
                                    <div x-cloak x-show="confirmingDelete" class="flex items-center gap-2">
                                        <button type="button" @click="confirmingDelete = false" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
                                        <form method="POST" action="{{ route('roles.destroy', $role) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg bg-rose-700 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-500">Delete permanently</button>
                                        </form>
                                    </div>
                                @else
                                    <button type="button" disabled title="Remove assigned users before deleting this role" class="cursor-not-allowed rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-400 dark:border-gray-700 dark:text-gray-500">Delete role</button>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Permissions</h4>
                            <div class="flex flex-wrap gap-2">
                                @forelse ($role->permissions as $permission)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.31a1 1 0 0 1-1.42 0l-3.25-3.277a1 1 0 1 1 1.42-1.408l2.54 2.56 6.54-6.593a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" /></svg>
                                        {{ \App\Support\PermissionLabel::for($permission->name) }}
                                    </span>
                                @empty
                                    <span class="text-sm text-gray-500 dark:text-gray-400">No permissions assigned.</span>
                                @endforelse
                            </div>
                        </div>
                        <a href="{{ route('users.create', $role->name) }}" class="mt-auto text-sm font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-300">Create a {{ \App\Support\RoleLabel::singular($role->name) }} user</a>
                    </article>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
