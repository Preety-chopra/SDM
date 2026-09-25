<x-app-layout title="Service Deficiency Memos">
    <x-slot name="header"><div class="flex items-center justify-between gap-4"><div><h2 class="text-xl font-semibold text-gray-900 dark:text-white">Service Deficiency Memos</h2><p class="text-sm text-gray-500 dark:text-gray-400">Draft and submitted CFA/TVS records.</p></div>@can('create', \App\Models\ServiceDeficiencyMemo::class)<a href="{{ route('sdm-memos.create') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">New memo</a>@endcan</div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @can(\App\Enums\UserPermission::ActOnRoutedMemos->value)
        <nav aria-label="SDM categories" class="mb-5 overflow-x-auto">
            <div class="inline-flex min-w-full gap-1 rounded-xl border border-gray-200 bg-gray-100 p-1 sm:min-w-0 dark:border-gray-700 dark:bg-gray-900">
                @foreach ([
                    ['category' => null, 'label' => 'All Memos'],
                    ['category' => 'pending', 'label' => 'Pending Memos'],
                    ['category' => 'transferred', 'label' => 'Transferred Memos'],
                ] as $tab)
                    @php($tabParameters = array_filter([...$filters, 'pending_stage' => $pendingStage?->value, 'category' => $tab['category']]))
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
                </div>

                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ str($status->value)->headline() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap justify-end gap-3">
                <a href="{{ route('sdm-memos.index', array_filter(['pending_stage' => $pendingStage?->value, 'category' => $category])) }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Clear filters</a>
                <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">Apply filters</button>
            </div>
        </form>
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"><thead class="bg-gray-50 dark:bg-gray-900"><tr>@foreach(['Type','Station / Line','Incident date','Status','Last Updated on',''] as $heading)<th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($memos as $memo)
                    <tr>
                        <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $memo->service_type->code() }}</td>
                        <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $memo->station_name }}
                            <span class="block text-xs text-gray-400">{{ $memo->line_name }}</span>
                        </td>
                        <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $memo->incident_date?->format('d-m-Y') ?? '—' }}</td>
                        <td class="px-5 py-4">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $memo->status === \App\Enums\MemoStatus::Draft ? 'bg-amber-100 text-amber-800' : ($memo->status === \App\Enums\MemoStatus::Cancelled ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800') }}">{{ $memo->cancellationLabel() ?? ($memo->currentWorkflowStep?->stage?->label() ? 'With '.$memo->currentWorkflowStep->stage->label() : ($memo->current_stage?->label() ? 'With '.$memo->current_stage->label() : str($memo->status->value)->headline())) }}</span>
                        </td>
                        <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $memo->statusUpdatedAt()?->format('d-m-Y H:i') ?? '—' }}</td>
                        <td class="px-5 py-4">
                            <div class="flex items-center justify-end gap-3">
                                <a class="font-semibold text-emerald-700 hover:underline" href="{{ auth()->user()->can('update', $memo) ? route('sdm-memos.edit', $memo) : route('sdm-memos.show', $memo) }}">Open</a>
                                @can('delete', $memo)
                                    <form method="POST" action="{{ route('sdm-memos.destroy', $memo) }}">
                                        @csrf 
                                        @method('DELETE')
                                        <button type="submit" class="font-semibold text-rose-700 hover:underline dark:text-rose-400">Delete</button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty<tr><td colspan="7" class="px-5 py-12 text-center text-gray-500">{{ match ($category) { 'pending' => 'No memos are currently pending with you.', 'transferred' => 'No transferred memos are currently pending with you.', default => 'No memos match the selected filters.' } }}</td></tr>@endforelse
            </tbody></table></div>
        </div><div class="mt-5">{{ $memos->links() }}</div>
    </div></div>
</x-app-layout>
