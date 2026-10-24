<x-app-layout title="Service Deficiency Memos">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Service Deficiency Memos</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Draft and submitted CFA/TVS records.</p></div>@can('create', \App\Models\ServiceDeficiencyMemo::class)<a href="{{ route('sdm-memos.create') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">New memo</a>@endcan</div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @can(\App\Enums\UserPermission::ActOnRoutedMemos->value)
        <nav aria-label="SDM categories" class="mb-5 overflow-x-auto">
            <div class="inline-flex min-w-full gap-1 rounded-xl border border-gray-200 bg-gray-100 p-1 sm:min-w-0 dark:border-gray-700 dark:bg-gray-900">
                @foreach ($memoCategoryTabs as $tab)
                    @php
                        $tabParameters = array_filter([
                            ...$filters,
                            'pending_stage' => $pendingStage?->value,
                            'category' => $tab['category'],
                        ]);
                    @endphp
                    <a
                        href="{{ route('sdm-memos.index', $tabParameters) }}"
                        @if($category === $tab['category']) aria-current="page" @endif
                        @class([
                            'flex-1 whitespace-nowrap rounded-lg px-4 py-2.5 text-center text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 sm:flex-none dark:focus:ring-offset-gray-900',
                            'bg-white text-emerald-800 shadow-sm dark:bg-gray-800 dark:text-emerald-300' => $category === $tab['category'],
                            'text-gray-600 hover:bg-white/70 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-gray-800/70 dark:hover:text-white' => $category !== $tab['category'],
                        ])
                    >{{ $tab['label'] }}</a>
                @endforeach
            </div>
        </nav>
        @endcan
        @if ($pendingStage)
            <div class="mb-5 flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                <p><strong>Pending at {{ $pendingStage->levelLabel() }}:</strong> showing memos awaiting {{ $pendingStage->label() }} action.</p>
                <a href="{{ route('sdm-memos.index', array_filter([...$filters, 'category' => $category])) }}" class="shrink-0 font-bold text-amber-900 hover:underline dark:text-amber-200">Clear pending-stage filter</a>
            </div>
        @endif
        @unless($headerFilterTable)
        <form method="GET" action="{{ route('sdm-memos.index') }}" class="mb-5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            @if ($category)
                <input type="hidden" name="category" value="{{ $category }}">
            @endif
            @if ($pendingStage)
                <input type="hidden" name="pending_stage" value="{{ $pendingStage->value }}">
            @endif

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <x-input-label for="service_type" value="SDM type" />
                    <select id="service_type" name="service_type" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">All types</option>
                        @foreach ($sdmTypes as $sdmType)
                            <option value="{{ $sdmType->value }}" @selected(($filters['service_type'] ?? '') === $sdmType->value)>{{ $sdmType->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="station_id" value="Station" />
                    <select id="station_id" name="station_id" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">All stations</option>
                        @foreach ($stations as $station)
                            <option value="{{ $station->id }}" @selected((string) ($filters['station_id'] ?? '') === (string) $station->id)>{{ $station->code }} · {{ $station->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="line_code" value="Line" />
                    <select id="line_code" name="line_code" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">All lines</option>
                        @foreach ($lines as $line)
                            <option value="{{ $line->code }}" @selected(($filters['line_code'] ?? '') === $line->code)>{{ $line->code }} · {{ $line->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="incident_date" value="Incident date" />
                    <x-text-input id="incident_date" name="incident_date" type="date" :value="$filters['incident_date'] ?? ''" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('incident_date')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="incident_date_from" value="Incident date from" />
                    <x-text-input id="incident_date_from" name="incident_date_from" type="date" :value="$filters['incident_date_from'] ?? ''" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('incident_date_from')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="incident_date_to" value="Incident date to" />
                    <x-text-input id="incident_date_to" name="incident_date_to" type="date" :value="$filters['incident_date_to'] ?? ''" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('incident_date_to')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="status" value="Current status" />
                    <select id="status" name="status" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $lineManagerStatusView && $status instanceof \App\Enums\MemoStatus ? $status->lineManagerFilterLabel() : $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap justify-end gap-3">
                <a href="{{ route('sdm-memos.index', array_filter(['pending_stage' => $pendingStage?->value, 'category' => $category])) }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Clear filters</a>
                <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">Apply filters</button>
            </div>
        </form>
        @else
            <form id="sdm-filter-form" method="GET" action="{{ route('sdm-memos.index') }}">
                @if($category)<input type="hidden" name="category" value="{{ $category }}">@endif
                @if($pendingStage)<input type="hidden" name="pending_stage" value="{{ $pendingStage->value }}">@endif
            </form>
        @endunless
        @if($bulkForwardingEnabled || $bulkCmcActionsEnabled)
        <div
            x-data="{{ $bulkCmcActionsEnabled ? 'cmcMemoSelection' : 'bulkMemoSelection' }}"
            @if($bulkForwardingEnabled) data-destinations='@json($bulkForwardDestinations)' @endif
            @if($bulkCmcActionsEnabled) data-actions='@json($bulkCmcActions)' @endif
            @if($bulkCmcActionsEnabled) data-initial-action="{{ old('action', 'assign') }}" data-selected='@json(old('memo_ids', []))' @endif
            class="flex flex-col gap-4"
        >
            @if($bulkForwardingEnabled)
            <form id="bulk-forward-form" method="POST" action="{{ route('sdm-memos.bulk-forward') }}" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm dark:border-emerald-900 dark:bg-emerald-950/40">
                @csrf
                @foreach($filters as $filterName => $filterValue)
                    <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
                @endforeach
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h3 class="font-semibold text-emerald-950 dark:text-emerald-100">Bulk forward Manager CMC memos</h3>
                        {{-- <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-200">Choose the workflow destination, then select individual SDMs or every eligible SDM matching the current filters.</p> --}}
                    </div>
                    <div class="grid gap-3 sm:grid-cols-[minmax(16rem,1fr)_auto] sm:items-end">
                        <div>
                            <x-input-label for="bulk_destination" value="Forward to" />
                            <select id="bulk_destination" name="destination" x-model="destination" class="mt-1 block w-full rounded-lg border-emerald-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-emerald-800 dark:bg-gray-900 dark:text-gray-100">
                                <option value="{{ \App\Enums\WorkflowStepType::AmCmcToScrutiny->value }}">CMC Team SM for scrutiny</option>
                                <option value="{{ \App\Enums\WorkflowStepType::AmCmcToDgm->value }}">DGM/CMC</option>
                            </select>
                        </div>
                        <button type="submit" :disabled="!selectAllFiltered && selected.length === 0" class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50">Forward selected</button>
                    </div>
                </div>
                <label class="mt-4 flex items-start gap-3 rounded-xl border border-emerald-200 bg-white p-3 text-sm text-gray-700 dark:border-emerald-900 dark:bg-gray-900 dark:text-gray-200">
                    <input form="bulk-forward-form" type="checkbox" name="select_all" value="1" x-model="selectAllFiltered" class="mt-0.5 rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
                    <span><strong>Select every matching memo across all pages</strong><span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">This applies the current filters and the selected destination.</span></span>
                </label>
                <p x-show="!selectAllFiltered && selected.length > 0" class="mt-3 text-sm font-medium text-emerald-900 dark:text-emerald-200"><span x-text="selected.length"></span> selected on this page</p>
                @error('memo_ids')<p class="mt-3 text-sm font-semibold text-rose-700 dark:text-rose-300">{{ $message }}</p>@enderror
                @error('destination')<p class="mt-3 text-sm font-semibold text-rose-700 dark:text-rose-300">{{ $message }}</p>@enderror
            </form>
            @endif
            @if($bulkCmcActionsEnabled)
            <form id="bulk-cmc-form" method="POST" action="{{ route('sdm-memos.bulk-cmc-action') }}" x-show="selected.length > 0" x-cloak class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm dark:border-emerald-900 dark:bg-emerald-950/40">
                @csrf
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <h3 class="font-semibold text-emerald-950 dark:text-emerald-100"><span x-text="selected.length"></span> SDM(s) selected</h3>
                        <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-200">Choose the CMC SM action to apply to the selected eligible SDMs.</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(13rem,1fr)_minmax(15rem,1fr)_auto] xl:items-end">
                        <div>
                            <x-input-label for="bulk_cmc_action" value="Action" />
                            <select id="bulk_cmc_action" name="action" x-model="action" class="mt-1 block w-full rounded-lg border-emerald-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-emerald-800 dark:bg-gray-900 dark:text-gray-100">
                                <option value="assign">Assign to CMC staff</option>
                                <option value="forward">Forward to Manager CMC</option>
                                <option value="return">Revert for rechecking</option>
                            </select>
                        </div>
                        <div x-show="action === 'assign'">
                            <x-input-label for="bulk_cmc_staff_id" value="CMC staff" />
                            <select id="bulk_cmc_staff_id" name="cmc_staff_id" :required="action === 'assign'" class="mt-1 block w-full rounded-lg border-emerald-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-emerald-800 dark:bg-gray-900 dark:text-gray-100">
                                <option value="">Select CMC staff</option>
                                @foreach($cmcStaffUsers as $cmcUser)<option value="{{ $cmcUser->id }}">{{ $cmcUser->name }} · {{ $cmcUser->employee_number }}</option>@endforeach
                            </select>
                        </div>
                        <div x-show="action === 'return'">
                            <x-input-label for="bulk_cmc_remarks" value="Reason for rechecking" />
                            <input id="bulk_cmc_remarks" name="remarks" :required="action === 'return'" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                        </div>
                        <button type="submit" :disabled="selected.length === 0" class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50">Apply action</button>
                    </div>
                </div>
                @error('memo_ids')<p class="mt-3 text-sm font-semibold text-rose-700 dark:text-rose-300">{{ $message }}</p>@enderror
                @error('cmc_staff_id')<p class="mt-3 text-sm font-semibold text-rose-700 dark:text-rose-300">{{ $message }}</p>@enderror
                @error('remarks')<p class="mt-3 text-sm font-semibold text-rose-700 dark:text-rose-300">{{ $message }}</p>@enderror
            </form>
            @endif
        @endif
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            @if($bulkForwardingEnabled || $bulkCmcActionsEnabled)
                                <th class="whitespace-nowrap px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <label class="inline-flex items-center gap-2 normal-case tracking-normal">
                                        <input type="checkbox" :checked="allPageSelected" :disabled="{{ $bulkForwardingEnabled ? 'selectAllFiltered || ' : '' }}availableIds().length === 0" @change="togglePage($event.target.checked)" class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
                                        <span>Select page</span>
                                    </label>
                                </th>
                            @endif
                            @foreach(array_filter(['SDM serial number', 'SDM type', 'SDM deficiency', 'Line / station', 'Incident date', 'Submitted on', 'Current status', 'Last updated on', $showActionColumn ? 'Action' : null]) as $heading)
                                <th scope="col" class="whitespace-nowrap px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    @if($headerFilterTable)
                                        @switch($heading)
                                            @case('SDM type')
                                                <x-column-filter-dropdown label="SDM type" :active="filled($filters['service_type'] ?? null)">
                                                    <select x-ref="picker" data-filter-picker form="sdm-filter-form" name="service_type" aria-label="Filter by SDM type" @change="$el.form.requestSubmit()" class="block w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                                        <option value="">All types</option>
                                                        @foreach($sdmTypes as $sdmType)<option value="{{ $sdmType->value }}" @selected(($filters['service_type'] ?? '') === $sdmType->value)>{{ $sdmType->code() }}</option>@endforeach
                                                    </select>
                                                </x-column-filter-dropdown>
                                                @break
                                            @case('Line / station')
                                                <x-column-filter-dropdown label="Line / station" :active="filled($filters['line_code'] ?? null) || filled($filters['station_id'] ?? null)" width="w-64">
                                                    <div class="grid gap-3">
                                                        <label class="grid gap-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Line<select x-ref="picker" data-filter-picker form="sdm-filter-form" name="line_code" aria-label="Filter by line" @change="$el.form.requestSubmit()" class="block w-full rounded-md border-gray-300 text-sm font-normal dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"><option value="">All lines</option>@foreach($lines as $line)<option value="{{ $line->code }}" @selected(($filters['line_code'] ?? '') === $line->code)>{{ $line->code }}</option>@endforeach</select></label>
                                                        <label class="grid gap-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Station<select form="sdm-filter-form" name="station_id" aria-label="Filter by station" @change="$el.form.requestSubmit()" class="block w-full rounded-md border-gray-300 text-sm font-normal dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"><option value="">All stations</option>@foreach($stations as $station)<option value="{{ $station->id }}" @selected((string) ($filters['station_id'] ?? '') === (string) $station->id)>{{ $station->code }}</option>@endforeach</select></label>
                                                    </div>
                                                </x-column-filter-dropdown>
                                                @break
                                            @case('Incident date')
                                                <x-column-filter-dropdown label="Incident date" :active="filled($filters['incident_date'] ?? null)">
                                                    <input x-ref="picker" data-filter-picker form="sdm-filter-form" name="incident_date" type="date" value="{{ $filters['incident_date'] ?? '' }}" aria-label="Filter by incident date" @change="$el.form.requestSubmit()" class="block w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                                </x-column-filter-dropdown>
                                                @break
                                            @case('Submitted on')
                                                <x-column-filter-dropdown label="Submitted on" :active="filled($filters['incident_date_from'] ?? null) || filled($filters['incident_date_to'] ?? null)" width="w-72">
                                                    <fieldset aria-label="Submitted date range" class="grid gap-3">
                                                        <label class="grid min-w-0 gap-1 text-xs font-semibold text-gray-600 dark:text-gray-300">From<input x-ref="picker" data-filter-picker form="sdm-filter-form" name="incident_date_from" type="date" value="{{ $filters['incident_date_from'] ?? '' }}" aria-label="Submitted from" class="block min-w-0 w-full rounded-md border-gray-300 text-sm font-normal dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"></label>
                                                        <label class="grid min-w-0 gap-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Till<input form="sdm-filter-form" name="incident_date_to" type="date" value="{{ $filters['incident_date_to'] ?? '' }}" aria-label="Submitted till" class="block min-w-0 w-full rounded-md border-gray-300 text-sm font-normal dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"></label>
                                                    </fieldset>
                                                    <button form="sdm-filter-form" type="submit" class="mt-3 w-full rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Apply date range</button>
                                                </x-column-filter-dropdown>
                                                @break
                                            @case('Current status')
                                                <x-column-filter-dropdown label="Current status" :active="filled($filters['status'] ?? null)">
                                                    <select id="status" x-ref="picker" data-filter-picker form="sdm-filter-form" name="status" aria-label="Filter by status" @change="$el.form.requestSubmit()" class="block w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $lineManagerStatusView && $status instanceof \App\Enums\MemoStatus ? $status->lineManagerFilterLabel() : $status->label() }}</option>@endforeach</select>
                                                </x-column-filter-dropdown>
                                                @break
                                            @default
                                                {{ $heading }}
                                        @endswitch
                                    @else
                                        {{ $heading }}
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($memos as $memo)
                            @php
                                $statusLabel = $stationManagerStatusView
                                    ? $memo->stationManagerListStatusLabel()
                                    : ($memo->cancellationLabel()
                                        ?? ($memo->currentWorkflowStep?->stage?->label()
                                            ? 'With '.$memo->currentWorkflowStep->stage->label()
                                            : ($memo->current_stage?->label()
                                                ? 'With '.$memo->current_stage->label()
                                                : str($memo->status->value)->headline())));
                                $isDirectCancellationStage = $memo->workflow_version >= 2
                                    ? $memo->currentWorkflowStep?->stage === \App\Enums\WorkflowStepType::LineReview
                                    : $memo->current_stage === \App\Enums\WorkflowStage::LineManager;
                                $canSeeSerial = auth()->user()->canAny([
                                    \App\Enums\UserPermission::ViewAllMemos->value,
                                    \App\Enums\UserPermission::ViewRoutedMemos->value,
                                ]) || $memo->created_by === auth()->id();
                            @endphp
                            <tr class="align-top">
                                @if($bulkForwardingEnabled || $bulkCmcActionsEnabled)
                                    <td class="whitespace-nowrap px-5 py-4">
                                        @if(($bulkForwardingEnabled && $bulkForwardDestinations->has((string) $memo->id)) || $bulkCmcActionsEnabled)
                                            <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                                <input
                                                    form="{{ $bulkCmcActionsEnabled ? 'bulk-cmc-form' : 'bulk-forward-form' }}"
                                                    type="checkbox"
                                                    name="memo_ids[]"
                                                    value="{{ $memo->id }}"
                                                    x-model="selected"
                                                    :disabled="{{ $bulkForwardingEnabled ? 'selectAllFiltered || ' : '' }}!isAvailable('{{ $memo->id }}')"
                                                    class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-500"
                                                >
                                                <span class="sr-only">Select {{ $memo->serial_number }}</span>
                                            </label>
                                        @else
                                            <span aria-hidden="true" class="text-gray-300 dark:text-gray-600">—</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="min-w-64 px-5 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    @if($canSeeSerial && $memo->serial_number)
                                        <div class="flex flex-col items-start gap-2">
                                            <div class="flex items-center gap-2">
                                                <a
                                                    href="{{ $memo->status === \App\Enums\MemoStatus::Draft ? route('sdm-memos.edit', $memo) : route('sdm-memos.show', $memo) }}"
                                                    aria-label="Open SDM {{ $memo->serial_number }}"
                                                    class="inline-flex items-center gap-2 text-emerald-700 transition hover:underline focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:text-emerald-400 dark:focus:ring-offset-gray-900"
                                                ><span>{{ $memo->serial_number }}</span><x-urgent-flag :memo="$memo" /></a>
                                                @if($category === null && $memo->latestRevertedWorkflowStep)
                                                    <span aria-label="Reverted SDM" title="This SDM was reverted" class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                                        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M9 7 4 12l5 5" stroke-linecap="round" stroke-linejoin="round" />
                                                            <path d="M5 12h9a5 5 0 0 1 5 5v1" stroke-linecap="round" />
                                                        </svg>
                                                    </span>
                                                @endif
                                            </div>
                                            @if($category === null && $memo->latestRevertedWorkflowStep)
                                                <p class="max-w-xs break-words text-xs font-normal leading-5 text-amber-800 dark:text-amber-200">
                                                    <span class="font-semibold">Revert remarks:</span>
                                                    {{ $memo->latestRevertedWorkflowStep->remarks }}
                                                </p>
                                            @endif
                                        </div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $memo->service_type->code() }}</td>
                                <td class="min-w-64 px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $memo->deficiencies->map(fn ($deficiency) => $deficiency->type->label())->join(', ') ?: '—' }}</td>
                                <td class="min-w-48 px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    <span class="block font-medium text-gray-900 dark:text-gray-100">{{ $headerFilterTable ? ($memo->line_code ?: '—') : ($memo->line_name ?: '—') }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $memo->station_name ?: '—' }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $memo->incident_date?->format('d-m-Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $memo->submitted_at?->format('d-m-Y H:i') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200' => $memo->status === \App\Enums\MemoStatus::Draft || in_array($statusLabel, ['Pending (at SM level)', 'Cancellation requested'], true),
                                        'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200' => str_starts_with($statusLabel, 'Cancelled'),
                                        'bg-cyan-100 text-cyan-800 dark:bg-cyan-950 dark:text-cyan-200' => $statusLabel === 'Forwarded to AM',
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' => $memo->status !== \App\Enums\MemoStatus::Draft && ! str_starts_with($statusLabel, 'Cancelled') && ! in_array($statusLabel, ['Pending (at SM level)', 'Cancellation requested', 'Forwarded to AM'], true),
                                    ])>{{ $statusLabel }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $memo->updated_at?->format('d-m-Y H:i') ?? '—' }}</td>
                                @if($showActionColumn)
                                <td class="min-w-52 px-5 py-4">
                                    <div class="flex items-start justify-end gap-3">
                                        @if($cmcTeamLeadTable && $memo->currentWorkflowStep?->assigned_to === auth()->id() && $memo->currentWorkflowStep?->decision === 'pending')
                                            @if($memo->currentWorkflowStep->stage === \App\Enums\WorkflowStepType::CmcAssignment)
                                                <details class="relative"><summary class="cursor-pointer list-none font-semibold text-amber-700 hover:underline dark:text-amber-300">Assign to CMC staff</summary><form method="POST" action="{{ route('sdm-memos.workflow.store', $memo) }}" class="absolute right-0 z-10 mt-2 flex w-64 flex-col gap-2 rounded-xl border border-gray-200 bg-white p-3 shadow-xl dark:border-gray-700 dark:bg-gray-900">@csrf<input type="hidden" name="action" value="assign"><select name="cmc_staff_id" required class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">@foreach($cmcStaffUsers as $cmcUser)<option value="{{ $cmcUser->id }}">{{ $cmcUser->name }} · {{ $cmcUser->employee_number }}</option>@endforeach</select><button class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white">Assign</button></form></details>
                                            @elseif($memo->currentWorkflowStep->stage === \App\Enums\WorkflowStepType::CmcVerification)
                                                <form method="POST" action="{{ route('sdm-memos.workflow.store', $memo) }}">@csrf<input type="hidden" name="action" value="forward"><button class="font-semibold text-emerald-700 hover:underline dark:text-emerald-400">Forward to Manager CMC</button></form>
                                                <details class="relative"><summary class="cursor-pointer list-none font-semibold text-amber-700 hover:underline dark:text-amber-300">Revert for rechecking</summary><form method="POST" action="{{ route('sdm-memos.workflow.store', $memo) }}" class="absolute right-0 z-10 mt-2 flex w-72 flex-col gap-2 rounded-xl border border-gray-200 bg-white p-3 shadow-xl dark:border-gray-700 dark:bg-gray-900">@csrf<input type="hidden" name="action" value="return"><input name="remarks" required placeholder="Reason for rechecking" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800"><button class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white">Revert</button></form></details>
                                            @endif
                                        @endif
                                        @can('delete', $memo)
                                            <form method="POST" action="{{ route('sdm-memos.destroy', $memo) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="font-semibold text-rose-700 hover:underline dark:text-rose-400">Cancel</button>
                                            </form>
                                        @else
                                            @can('act', $memo)
                                                @if($isDirectCancellationStage)
                                                    <x-memo-cancellation-dialog :memo="$memo" action="cancel" />
                                                    <x-memo-cancellation-dialog :memo="$memo" action="cancel_after_counselling" />
                                                @endif
                                            @endcan
                                        @endcan
                                    </div>
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ 8 + ($showActionColumn ? 1 : 0) + (($bulkForwardingEnabled || $bulkCmcActionsEnabled) ? 1 : 0) }}" class="px-5 py-12 text-center text-gray-500 dark:text-gray-400">{{ match ($category) { 'pending' => 'No memos are currently pending with you.', 'transferred' => 'No transferred memos are currently pending with you.', default => 'No memos match the selected filters.' } }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div><div class="mt-5">{{ $memos->links() }}</div>
        @if($bulkForwardingEnabled || $bulkCmcActionsEnabled)
        </div>
        @endif
    </div></div>
</x-app-layout>
