<x-app-layout>
    <x-slot name="header">
        {{-- <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-400">SDM Workspace</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Good to see you, {{ auth()->user()->name }}</h1>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Choose an action and get straight to work.</p>
        </div> --}}
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="mx-auto flex max-w-7xl flex-col gap-8 px-4 sm:px-6 lg:px-8">
            <section aria-labelledby="quick-actions-heading">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <div>
                        <h2 id="quick-actions-heading" class="text-lg font-bold text-gray-950 dark:text-white">Quick actions</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Your most useful tools, one click away.</p>
                    </div>
                    <span class="hidden rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 sm:inline-flex">Direct access</span>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    @can('create', \App\Models\ServiceDeficiencyMemo::class)
                        <a href="{{ route('sdm-memos.create') }}" class="group flex min-h-32 flex-col justify-between rounded-2xl bg-emerald-700 p-5 text-white shadow-sm transition duration-200 hover:-translate-y-1 hover:bg-emerald-800 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                            <span class="text-xs font-bold uppercase tracking-wider text-emerald-100">Start</span>
                            <span class="flex items-end justify-between gap-3 text-base font-bold">Create SDM memo <span aria-hidden="true" class="text-2xl transition-transform group-hover:translate-x-1">→</span></span>
                        </a>
                    @endcan

                    @if ($canUseMemos)
                        <a href="{{ route('sdm-memos.index') }}" class="group flex min-h-32 flex-col justify-between rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-emerald-300 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-700 dark:focus:ring-offset-gray-900">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Records</span>
                            <span class="flex items-end justify-between gap-3 font-bold text-gray-950 dark:text-white">View all memos <span aria-hidden="true" class="text-2xl text-emerald-600 transition-transform group-hover:translate-x-1 dark:text-emerald-400">→</span></span>
                        </a>
                    @endif

                    @can(\App\Enums\UserPermission::CreateUsers->value)
                        <a href="{{ route('users.create_any') }}" class="group flex min-h-32 flex-col justify-between rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-emerald-300 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-700 dark:focus:ring-offset-gray-900">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-400">People</span>
                            <span class="flex items-end justify-between gap-3 font-bold text-gray-950 dark:text-white">Create user <span aria-hidden="true" class="text-2xl text-emerald-600 transition-transform group-hover:translate-x-1 dark:text-emerald-400">→</span></span>
                        </a>
                    @endcan

                    @can(\App\Enums\UserPermission::ManageLines->value)
                        <a href="{{ route('lines.index') }}" class="group flex min-h-32 flex-col justify-between rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-emerald-300 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-700 dark:focus:ring-offset-gray-900">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Network</span>
                            <span class="flex items-end justify-between gap-3 font-bold text-gray-950 dark:text-white">Manage lines <span aria-hidden="true" class="text-2xl text-emerald-600 transition-transform group-hover:translate-x-1 dark:text-emerald-400">→</span></span>
                        </a>
                    @endcan

                    @can(\App\Enums\UserPermission::ManageStations->value)
                        <a href="{{ route('stations.index') }}" class="group flex min-h-32 flex-col justify-between rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-emerald-300 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-700 dark:focus:ring-offset-gray-900">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Locations</span>
                            <span class="flex items-end justify-between gap-3 font-bold text-gray-950 dark:text-white">Manage stations <span aria-hidden="true" class="text-2xl text-emerald-600 transition-transform group-hover:translate-x-1 dark:text-emerald-400">→</span></span>
                        </a>
                    @endcan

                    @can(\App\Enums\UserPermission::ManageContractors->value)
                        <a href="{{ route('contractors.index') }}" class="group flex min-h-32 flex-col justify-between rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-emerald-300 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-700 dark:focus:ring-offset-gray-900">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Partners</span>
                            <span class="flex items-end justify-between gap-3 font-bold text-gray-950 dark:text-white">Manage contractors <span aria-hidden="true" class="text-2xl text-emerald-600 transition-transform group-hover:translate-x-1 dark:text-emerald-400">→</span></span>
                        </a>
                    @endcan
                </div>
            </section>

            @if ($workflowStages !== [])
                <section aria-labelledby="pending-memos-heading">
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 id="pending-memos-heading" class="text-lg font-bold text-gray-950 dark:text-white">Pending memos by approval level</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Track SDMs currently awaiting action at each workflow level.</p>
                        </div>
                        <a href="{{ route('sdm-memos.index') }}" class="text-sm font-bold text-emerald-700 hover:text-emerald-800 hover:underline dark:text-emerald-400">View all memos →</a>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        @foreach ($workflowStages as $stage)
                            @php($pending = $pendingMemoStages[$stage->value])
                            <article class="flex min-h-64 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                                    <div>
                                        <h3 class="font-bold text-gray-950 dark:text-white">{{ $stage->levelLabel() }}</h3>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $stage->label() }}</p>
                                    </div>
                                    <span class="inline-flex min-w-8 items-center justify-center rounded-full bg-amber-100 px-2.5 py-1 text-sm font-black text-amber-800 dark:bg-amber-950 dark:text-amber-300">{{ $pending['total'] }}</span>
                                </div>

                                <div class="flex flex-1 flex-col divide-y divide-gray-100 dark:divide-gray-700">
                                    @forelse ($pending['memos'] as $memo)
                                        <a href="{{ route('sdm-memos.show', $memo) }}" class="group flex items-start justify-between gap-3 px-5 py-3 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-emerald-500 dark:hover:bg-gray-900/50">
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-bold text-gray-900 group-hover:text-emerald-700 dark:text-white dark:group-hover:text-emerald-400">{{ $memo->serial_number }}</span>
                                                <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $memo->station_name ?? 'Station not recorded' }}</span>
                                                @if ($memo->approvals->first()?->assigned_name)
                                                    <span class="block truncate text-xs text-gray-400">{{ $memo->approvals->first()->assigned_name }}</span>
                                                @endif
                                            </span>
                                            <span aria-hidden="true" class="text-emerald-600 transition-transform group-hover:translate-x-1 dark:text-emerald-400">→</span>
                                        </a>
                                    @empty
                                        <p class="flex flex-1 items-center justify-center px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No pending memos.</p>
                                    @endforelse
                                </div>

                                <a href="{{ route('sdm-memos.index', ['pending_stage' => $stage->value]) }}" class="border-t border-gray-100 px-5 py-3 text-center text-sm font-bold text-emerald-700 hover:bg-emerald-50 hover:underline focus:outline-none focus:ring-2 focus:ring-inset focus:ring-emerald-500 dark:border-gray-700 dark:text-emerald-400 dark:hover:bg-emerald-950/30">
                                    View all {{ $stage->levelLabel() }} memos
                                </a>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- @if ($canManageUsers)
                <section aria-labelledby="user-management-heading">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 id="user-management-heading" class="text-lg font-bold text-gray-950 dark:text-white">User management</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Open any team directory directly.</p>
                        </div>
                        @can(\App\Enums\UserPermission::ManageRoles->value)
                            <a href="{{ route('roles.index') }}" class="text-sm font-bold text-emerald-700 hover:text-emerald-800 hover:underline dark:text-emerald-400">Roles &amp; permissions →</a>
                        @endcan
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        @foreach ($roles as $role)
                            @php($counts = $roleCounts[$role->name])
                            <a href="{{ route('users.index', $role->name) }}" class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-emerald-300 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-700 dark:focus:ring-offset-gray-900">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-semibold text-gray-700 dark:text-gray-200">{{ \App\Support\RoleLabel::plural($role->name) }}</p>
                                        <p class="mt-2 text-3xl font-black text-gray-950 dark:text-white">{{ $counts['total'] }}</p>
                                    </div>
                                    <span aria-hidden="true" class="text-2xl text-emerald-600 transition-transform group-hover:translate-x-1 dark:text-emerald-400">→</span>
                                </div>
                                <div class="mt-5 grid grid-cols-2 gap-3 border-t border-gray-100 pt-4 text-sm dark:border-gray-700">
                                    <div><span class="block text-xs text-gray-500 dark:text-gray-400">Enabled</span><strong class="text-emerald-700 dark:text-emerald-400">{{ $counts['enabled'] }}</strong></div>
                                    <div><span class="block text-xs text-gray-500 dark:text-gray-400">Disabled</span><strong class="text-rose-700 dark:text-rose-400">{{ $counts['disabled'] }}</strong></div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif --}}
        </div>
    </div>
</x-app-layout>
