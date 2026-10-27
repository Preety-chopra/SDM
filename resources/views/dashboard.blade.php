<x-app-layout title="Dashboard">
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-400">SDM Workspace</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Good to see you, {{ auth()->user()->name }}</h1>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Choose an action and get straight to work.</p>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="mx-auto flex max-w-7xl flex-col gap-8 px-4 sm:px-6 lg:px-8">
            @unless ($isStationController || $isStationManager || $isLineManager || $isWorkflowDashboardUser)
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
                            <span class="flex items-end justify-between gap-3 text-base font-bold">Create SDM memo <span aria-hidden="true" class="text-2xl transition-transform group-hover:translate-x-1"></span></span>
                        </a>
                    @endcan

                    @if ($canUseMemos)
                        <a href="{{ route('sdm-memos.index') }}" class="group flex min-h-32 flex-col justify-between rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-emerald-300 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-700 dark:focus:ring-offset-gray-900">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Records</span>
                            <span class="flex items-end justify-between gap-3 font-bold text-gray-950 dark:text-white">View all memos <span aria-hidden="true" class="text-2xl text-emerald-600 transition-transform group-hover:translate-x-1 dark:text-emerald-400"></span></span>
                        </a>
                    @endif

                    @can(\App\Enums\UserPermission::CreateUsers->value)
                        <a href="{{ route('users.create_any') }}" class="group flex min-h-32 flex-col justify-between rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-emerald-300 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-700 dark:focus:ring-offset-gray-900">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-400">People</span>
                            <span class="flex items-end justify-between gap-3 font-bold text-gray-950 dark:text-white">Create user <span aria-hidden="true" class="text-2xl text-emerald-600 transition-transform group-hover:translate-x-1 dark:text-emerald-400"></span></span>
                        </a>
                    @endcan

                    @can(\App\Enums\UserPermission::ManageLines->value)
                        <a href="{{ route('lines.index') }}" class="group flex min-h-32 flex-col justify-between rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-emerald-300 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-700 dark:focus:ring-offset-gray-900">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Network</span>
                            <span class="flex items-end justify-between gap-3 font-bold text-gray-950 dark:text-white">Manage lines <span aria-hidden="true" class="text-2xl text-emerald-600 transition-transform group-hover:translate-x-1 dark:text-emerald-400"></span></span>
                        </a>
                    @endcan

                    @can(\App\Enums\UserPermission::ManageStations->value)
                        <a href="{{ route('stations.index') }}" class="group flex min-h-32 flex-col justify-between rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-emerald-300 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-700 dark:focus:ring-offset-gray-900">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Locations</span>
                            <span class="flex items-end justify-between gap-3 font-bold text-gray-950 dark:text-white">Manage stations <span aria-hidden="true" class="text-2xl text-emerald-600 transition-transform group-hover:translate-x-1 dark:text-emerald-400"></span></span>
                        </a>
                    @endcan

                    @can(\App\Enums\UserPermission::ManageContractors->value)
                        <a href="{{ route('contractors.index') }}" class="group flex min-h-32 flex-col justify-between rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-emerald-300 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-700 dark:focus:ring-offset-gray-900">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Partners</span>
                            <span class="flex items-end justify-between gap-3 font-bold text-gray-950 dark:text-white">Manage contractors <span aria-hidden="true" class="text-2xl text-emerald-600 transition-transform group-hover:translate-x-1 dark:text-emerald-400"></span></span>
                        </a>
                    @endcan
                </div>
            </section>
            @endunless

            @if ($isStationController)
                <x-station-controller-dashboard :dashboard="$stationControllerDashboard" />
            @endif

            @if ($isStationManager)
                <x-station-manager-dashboard :dashboard="$stationManagerDashboard" />
            @endif

            @if ($isLineManager)
                <x-line-manager-dashboard :dashboard="$lineManagerDashboard" />
            @endif

            @if ($isWorkflowDashboardUser)
                <x-workflow-role-dashboard :dashboard="$workflowRoleDashboard" />
            @endif
{{-- 
            @if (!empty($workflowStages))
                <section aria-labelledby="approval-level-heading">
                    <div class="mb-4">
                        <h2 id="approval-level-heading" class="text-lg font-bold text-gray-950 dark:text-white">Pending memos by approval level</h2>
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        @foreach ($workflowStages as $stage)
                            @php
                                $queue = $pendingMemoStages[$stage->value];
                            @endphp
                            <a href="{{ route('sdm-memos.index', ['pending_stage' => $stage->value]) }}" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="font-bold text-gray-950 dark:text-white">{{ $stage->levelLabel() }}</h3>
                                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-900 dark:bg-amber-950 dark:text-amber-200">{{ $queue['total'] }}</span>
                                </div>
                                <div class="mt-4 space-y-2">
                                    @forelse ($queue['memos'] as $memo)
                                        <p class="truncate text-sm text-gray-600 dark:text-gray-300">{{ $memo->serial_number }}</p>
                                    @empty
                                        <p class="text-sm text-gray-500 dark:text-gray-400">No pending memos</p>
                                    @endforelse
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif --}}

            @if (!empty($workflowStepTypes))
                @php
                    $totalPendingWorkflowSteps = collect($pendingWorkflowSteps)->sum('total');
                @endphp

                <section data-workflow-board aria-labelledby="contract-workflow-heading" class="overflow-hidden rounded-3xl border border-gray-200 bg-gray-100/80 shadow-sm dark:border-gray-700 dark:bg-gray-950/40">
                    <div class="flex flex-col gap-5 bg-gradient-to-r from-emerald-700 via-teal-700 to-cyan-700 px-5 py-6 text-white sm:flex-row sm:items-end sm:justify-between sm:px-7">
                        <div class="flex items-start gap-4">
                            <div class="flex flex-col gap-1">
                                <h2 id="contract-workflow-heading" class="text-xl font-black tracking-tight sm:text-2xl">CFA / TVS workflow queues</h2>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-3 rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-inset ring-white/15">
                            <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-xl bg-amber-300 px-2 text-lg font-black text-amber-950">{{ $totalPendingWorkflowSteps }}</span>
                            <span class="text-sm font-semibold leading-tight text-gray-100">
                                {{ $totalPendingWorkflowSteps === 1 ? 'memo waiting' : 'memos waiting' }}
                                <span class="block text-xs font-normal text-gray-300">across all stages</span>
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 p-4 sm:p-5 md:grid-cols-2 xl:grid-cols-3">
                        @foreach($workflowStepTypes as $stepType)
                            @php
                                $queue = $pendingWorkflowSteps[$stepType->value];
                                $hasPendingMemos = $queue['total'] > 0;
                                $stageNumber = $loop->iteration;
                            @endphp

                            <article
                                data-workflow-stage="{{ $stepType->value }}"
                                aria-label="Stage {{ $stageNumber }} of {{ $loop->count }}"
                                @class([
                                    'group flex min-h-56 flex-col overflow-hidden rounded-2xl border bg-white transition duration-200 dark:bg-gray-900',
                                    'border-emerald-300 shadow-md ring-1 ring-emerald-100 hover:-translate-y-0.5 hover:shadow-lg dark:border-emerald-700 dark:ring-emerald-950' => $hasPendingMemos,
                                    'border-gray-200 shadow-sm dark:border-gray-800' => ! $hasPendingMemos,
                                ])
                            >
                                <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span @class([
                                            'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-black',
                                            'bg-emerald-600 text-white shadow-sm shadow-emerald-200 dark:shadow-none' => $hasPendingMemos,
                                            'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => ! $hasPendingMemos,
                                        ])>{{ str_pad((string) $stageNumber, 2, '0', STR_PAD_LEFT) }}</span>
                                        <div class="min-w-0">
                                            <p class="text-[0.65rem] font-bold uppercase tracking-[0.16em] text-gray-400">Workflow stage</p>
                                            <h3 class="mt-1 text-sm font-bold leading-5 text-gray-950 dark:text-white">{{ $stepType->label() }}</h3>
                                        </div>
                                    </div>

                                    <span @class([
                                        'inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-bold',
                                        'bg-amber-100 text-amber-900 ring-1 ring-inset ring-amber-200 dark:bg-amber-950 dark:text-amber-200 dark:ring-amber-800' => $hasPendingMemos,
                                        'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-900' => ! $hasPendingMemos,
                                    ])>
                                        {{ $hasPendingMemos ? $queue['total'].' pending' : 'Clear' }}
                                    </span>
                                </div>

                                <div class="flex flex-1 flex-col gap-2 p-3">
                                    @forelse($queue['memos'] as $queuedMemo)
                                        <a href="{{ route('sdm-memos.show', $queuedMemo) }}" class="group/memo flex items-center justify-between gap-3 rounded-xl border border-transparent bg-gray-50 px-4 py-3 transition hover:border-emerald-200 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 dark:bg-gray-800/70 dark:hover:border-emerald-800 dark:hover:bg-emerald-950/50 dark:focus:ring-offset-gray-900">
                                            <span class="min-w-0">
                                                <span class="mt-1 block truncate text-xs font-medium text-gray-600 dark:text-gray-300">{{ $queuedMemo->station_name ?: 'Station not recorded' }}</span>
                                                <span class="mt-0.5 block truncate text-xs text-gray-400">Assigned to {{ $queuedMemo->currentWorkflowStep?->assigned_name ?: 'Unassigned' }}</span>
                                            </span>
                                            <span aria-hidden="true" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-lg font-bold text-emerald-700 shadow-sm ring-1 ring-gray-200 transition group-hover/memo:translate-x-0.5 group-hover/memo:ring-emerald-300 dark:bg-gray-900 dark:text-emerald-300 dark:ring-gray-700">→</span>
                                        </a>
                                    @empty
                                        <div class="flex flex-1 items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-gray-50/70 px-4 py-5 dark:border-gray-700 dark:bg-gray-800/40">
                                            <span aria-hidden="true" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-base font-black text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">✓</span>
                                            <span>
                                                <strong class="block text-sm text-gray-700 dark:text-gray-200">Queue clear</strong>
                                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">No memos are waiting at this stage.</span>
                                            </span>
                                        </div>
                                    @endforelse
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
            @if ($canManageUsers)
                <section aria-labelledby="user-management-heading">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 id="user-management-heading" class="text-lg font-bold text-gray-950 dark:text-white">User management</h2>
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
                                    <span aria-hidden="true" class="text-2xl text-emerald-600 transition-transform group-hover:translate-x-1 dark:text-emerald-400"></span>
                                </div>
                                <div class="mt-5 grid grid-cols-2 gap-3 border-t border-gray-100 pt-4 text-sm dark:border-gray-700">
                                    <div><span class="block text-xs text-gray-500 dark:text-gray-400">Enabled</span><strong class="text-emerald-700 dark:text-emerald-400">{{ $counts['enabled'] }}</strong></div>
                                    <div><span class="block text-xs text-gray-500 dark:text-gray-400">Disabled</span><strong class="text-rose-700 dark:text-rose-400">{{ $counts['disabled'] }}</strong></div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
