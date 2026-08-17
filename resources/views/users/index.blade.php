<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">{{ $role->pluralLabel() }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create, update, enable, or disable {{ strtolower($role->pluralLabel()) }}.</p>
            </div>
            <a href="{{ route('users.create', $role->value) }}" class="inline-flex items-center justify-center rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">Create {{ $role->label() }}</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>
            @endif

            <form method="GET" action="{{ route('users.index', $role->value) }}" class="grid gap-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_12rem_auto] dark:border-gray-700 dark:bg-gray-800">
                <div>
                    <label for="search" class="sr-only">Search users</label>
                    <input id="search" name="search" value="{{ request('search') }}" placeholder="Search name, email, employee no. or phone" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                </div>
                <div>
                    <label for="status" class="sr-only">Status</label>
                    <select id="status" name="status" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">All statuses</option>
                        <option value="enabled" @selected(request('status') === 'enabled')>Enabled</option>
                        <option value="disabled" @selected(request('status') === 'disabled')>Disabled</option>
                    </select>
                </div>
                <button class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Filter</button>
            </form>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/60">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">User</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Personnel</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Station</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($users as $managedUser)
                                <tr>
                                    <td class="px-5 py-4"><p class="font-semibold text-gray-900 dark:text-gray-100">{{ $managedUser->name }}</p><p class="text-sm text-gray-500 dark:text-gray-400">{{ $managedUser->email }}</p></td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300"><p>{{ $managedUser->employee_number ?: '—' }}</p><p class="text-gray-400">{{ $managedUser->phone ?: 'No phone' }}</p></td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $managedUser->station ? $managedUser->station->code.' · '.$managedUser->station->name : '—' }}</td>
                                    <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $managedUser->is_enabled ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' }}">{{ $managedUser->is_enabled ? 'Enabled' : 'Disabled' }}</span></td>
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end gap-2" x-data="{ confirming: false }">
                                            <a href="{{ route('users.edit', $managedUser) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit</a>
                                            <button type="button" @click="confirming = true" class="rounded-lg px-3 py-2 text-sm font-semibold {{ $managedUser->is_enabled ? 'text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950' : 'text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950' }} focus:outline-none focus:ring-2 focus:ring-emerald-500">{{ $managedUser->is_enabled ? 'Disable' : 'Enable' }}</button>
                                            <div x-cloak x-show="confirming" class="fixed inset-0 z-50 grid place-items-center bg-gray-950/60 p-4" role="dialog" aria-modal="true">
                                                <div @click.outside="confirming = false" class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800">
                                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $managedUser->is_enabled ? 'Disable' : 'Enable' }} user?</h3>
                                                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">This will {{ $managedUser->is_enabled ? 'block access for' : 'restore access for' }} {{ $managedUser->name }}.</p>
                                                    <div class="mt-6 flex justify-end gap-3">
                                                        <button type="button" @click="confirming = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold dark:border-gray-600 dark:text-gray-200">Cancel</button>
                                                        <form method="POST" action="{{ route('users.status', $managedUser) }}">@csrf @method('patch')<input type="hidden" name="is_enabled" value="{{ $managedUser->is_enabled ? 0 : 1 }}"><button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Confirm</button></form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No users match the selected filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($users->hasPages())
                    <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-700">{{ $users->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
