@props(['dashboard'])

@php
    $filters = $dashboard['filters'];
    $recentSdms = $dashboard['recent_sdms'];
    $chartConfig = [
        'monthlyTrend' => $dashboard['monthly_trend'],
        'serviceDistribution' => $dashboard['service_distribution'],
        'workflowByService' => $dashboard['workflow_by_service'],
        'aging' => $dashboard['aging_buckets'],
        'chartMemosUrl' => route('dashboard.chart-sdms'),
        'filters' => $filters,
    ];
    $baseFilterQuery = array_filter([
        'date_from' => $filters['date_from'],
        'date_to' => $filters['date_to'],
        'service_type' => $filters['service_type'],
    ]);
@endphp

<section
    aria-labelledby="my-sdm-dashboard-heading"
    class="flex flex-col gap-6"
    x-data="dashboardCharts(@js($chartConfig))"
>
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        {{-- <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-400">SDM workspace</p>
            <h1 id="my-sdm-dashboard-heading" class="mt-1 text-2xl font-black tracking-tight text-gray-950 dark:text-white sm:text-3xl">My SDM Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Track your filed memos, workflow progress, and records needing attention.</p>
        </div> --}}

        <div class="flex flex-wrap gap-2">
            @can('create', \App\Models\ServiceDeficiencyMemo::class)
                <a href="{{ route('sdm-memos.create') }}" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                    <span aria-hidden="true">+</span> Create New SDM
                </a>
            @endcan
            <a href="{{ route('sdm-memos.index', array_filter(['service_type' => $filters['service_type'], 'status' => $filters['status']])) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:border-emerald-300 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-emerald-700 dark:hover:text-emerald-300 dark:focus:ring-offset-gray-900">
                View All SDMs
            </a>
        </div>
    </div>

    {{-- <form method="GET" action="{{ route('dashboard') }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.3fr)_minmax(0,1.3fr)_auto] xl:items-end">
            <label class="block">
                <span class="mb-1.5 block text-xs font-bold text-gray-600 dark:text-gray-300">From</span>
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="block min-h-10 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-bold text-gray-600 dark:text-gray-300">To</span>
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="block min-h-10 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-bold text-gray-600 dark:text-gray-300">Service</span>
                <select name="service_type" class="block min-h-10 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                    <option value="">All services</option>
                    @foreach ($dashboard['services'] as $service)
                        <option value="{{ $service->value }}" @selected($filters['service_type'] === $service->value)>{{ $service->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-bold text-gray-600 dark:text-gray-300">Status</span>
                <select name="status" class="block min-h-10 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                    <option value="">All statuses</option>
                    @foreach ($dashboard['statuses'] as $status)
                        <option value="{{ $status['value'] }}" @selected($filters['status'] === $status['value'])>{{ $status['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex gap-2 sm:col-span-2 xl:col-span-1">
                <button type="submit" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">Apply</button>
                <a href="{{ route('dashboard') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-bold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
            </div>
        </div>
        @if ($filters['aging'])
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4 text-sm dark:border-gray-700">
                <span class="font-semibold text-gray-600 dark:text-gray-300">Table aging filter:</span>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900 dark:bg-amber-950 dark:text-amber-200">{{ collect(array_combine($dashboard['aging_buckets']['values'], $dashboard['aging_buckets']['labels']))->get($filters['aging']) }}</span>
                <a href="{{ route('dashboard', array_filter([...$baseFilterQuery, 'status' => $filters['status']])) }}" class="font-bold text-emerald-700 hover:underline dark:text-emerald-400">Clear aging filter</a>
            </div>
        @endif
    </form> --}}

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('dashboard', $baseFilterQuery) }}" class="group rounded-2xl bg-emerald-700 p-5 text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-4xl font-black tracking-tight">{{ $dashboard['total_sdms'] }}</p>
                    <p class="mt-2 text-sm font-bold text-emerald-50">SDMs Filled</p>
                </div>
                <span class="text-xl text-emerald-100 transition group-hover:translate-x-0.5" aria-hidden="true">→</span>
            </div>
            {{-- <p class="mt-3 text-xs text-emerald-100">Selected period and filters</p> --}}
        </a>

        @foreach ($dashboard['kpis'] as $kpi)
            <a href="{{ route('dashboard', [...$baseFilterQuery, 'status' => $kpi['status']]) }}" class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-700 dark:focus:ring-offset-gray-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-4xl font-black tracking-tight text-gray-950 dark:text-white">{{ $kpi['count'] }}</p>
                        <p class="mt-2 text-sm font-bold text-gray-700 dark:text-gray-200">{{ $kpi['label'] }}</p>
                    </div>
                    <span class="text-xl text-emerald-600 transition group-hover:translate-x-0.5 dark:text-emerald-400" aria-hidden="true">→</span>
                </div>
                {{-- <p class="mt-3 text-xs font-medium text-gray-500 dark:text-gray-400">{{ $kpi['percentage'] }}% of total</p> --}}
            </a>
        @endforeach
    </div>

    {{-- <div class="grid grid-cols-1 gap-5 xl:grid-cols-5">
        <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 xl:col-span-3">
            <div>
                <h2 class="text-lg font-black text-gray-950 dark:text-white">SDMs filed over time</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Latest six months. Select a line to filter by service.</p>
            </div>
            <div class="mt-5 h-72 min-w-0 sm:h-80">
                <canvas x-ref="trend" role="img" aria-label="Monthly SDMs filed over the latest six months"></canvas>
            </div>
            <p class="sr-only">{{ collect($dashboard['monthly_trend']['labels'])->join(', ') }}</p>
        </article>

        <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 xl:col-span-2">
            <div>
                <h2 class="text-lg font-black text-gray-950 dark:text-white">SDMs by service</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Select a segment to filter the dashboard.</p>
            </div>
            <div class="mx-auto mt-5 h-56 max-w-xs min-w-0">
                <canvas x-ref="service" role="img" aria-label="Distribution of SDMs by service"></canvas>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-2">
                @foreach ($dashboard['service_distribution']['labels'] as $index => $label)
                    <a href="{{ route('dashboard', [...$baseFilterQuery, 'service_type' => $dashboard['service_distribution']['service_types'][$index], 'status' => $filters['status']]) }}" class="rounded-xl bg-gray-50 px-3 py-2 text-sm transition hover:bg-emerald-50 dark:bg-gray-900/60 dark:hover:bg-emerald-950/40">
                        <span class="font-bold text-gray-900 dark:text-white">{{ $label }} · {{ $dashboard['service_distribution']['counts'][$index] }}</span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $dashboard['service_distribution']['percentages'][$index] }}%</span>
                    </a>
                @endforeach
            </div>
        </article>
    </div> --}}

    {{-- <div class="grid grid-cols-1 gap-5 xl:grid-cols-5">
        <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 xl:col-span-3">
            <div>
                <h2 class="text-lg font-black text-gray-950 dark:text-white">SM-level status by service</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cases waiting for or processed by the Station Manager. Select a bar to view its SDMs.</p>
            </div>
            <div class="mt-5 h-72 min-w-0">
                <canvas x-ref="workflow" role="img" aria-label="Station Manager-level statuses grouped by service"></canvas>
            </div>
        </article>

        <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 xl:col-span-2">
            <div>
                <h2 class="text-lg font-black text-gray-950 dark:text-white">Pending aging</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Age of cases currently waiting for Station Manager action. Select a bar to view its SDMs.</p>
            </div>
            <div class="mt-5 h-72 min-w-0">
                <canvas x-ref="aging" role="img" aria-label="Pending SDMs grouped into aging buckets"></canvas>
            </div>
        </article>
    </div> --}}

    <article class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-5 dark:border-gray-700 sm:flex-row sm:items-end sm:justify-between sm:px-6">
            <div>
                <h2 class="text-lg font-black text-gray-950 dark:text-white">Recent SDMs</h2>
                {{-- <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Up to 10 records matching the selected filters.</p> --}}
            </div>
            <a href="{{ route('sdm-memos.index', array_filter(['service_type' => $filters['service_type'], 'status' => $filters['status']])) }}" class="text-sm font-bold text-emerald-700 hover:underline dark:text-emerald-400">View all SDMs →</a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/60">
                    <tr class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <th scope="col" class="px-5 py-3 sm:px-6">Submitted On</th>
                        <th scope="col" class="px-5 py-3 sm:px-6">Service</th>
                        <th scope="col" class="px-5 py-3 sm:px-6">Status</th>
                        <th scope="col" class="px-5 py-3 text-right sm:px-6">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($recentSdms as $memo)
                        <tr class="transition hover:bg-emerald-50/60 dark:hover:bg-emerald-950/20">
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300 sm:px-6">{{ $memo->created_at->format('d M Y') }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-gray-700 dark:text-gray-200 sm:px-6">{{ $memo->service_type->code() }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm sm:px-6">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                                    'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200' => $memo->dashboard_status === \App\Enums\MemoStatus::AwaitingStationManager->value,
                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' => $memo->dashboard_status === \App\Enums\MemoStatus::LineReview->value,
                                    'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200' => $memo->dashboard_status === \App\Enums\MemoStatus::Draft->value,
                                ])>{{ $memo->dashboard_status_label }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-right text-sm sm:px-6">
                                @can('view', $memo)
                                    <a href="{{ route('sdm-memos.show', $memo) }}" class="font-bold text-emerald-700 hover:underline dark:text-emerald-400">View</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">No SDMs match the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>

    <x-dashboard-sdm-modal />
</section>
