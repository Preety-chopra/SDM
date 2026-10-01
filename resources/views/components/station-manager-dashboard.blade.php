@props(['dashboard'])

@php
    $filters = $dashboard['filters'];
    $chartConfig = [
        'monthlyTrend' => $dashboard['monthly_trend'],
        'stationDistribution' => $dashboard['station_distribution'],
        'serviceDistribution' => $dashboard['service_distribution'],
    ];
@endphp

<section aria-labelledby="station-manager-dashboard-heading" class="flex flex-col gap-6" x-data="stationManagerDashboardCharts(@js($chartConfig))">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-400">My review portfolio</p>
            <h1 id="station-manager-dashboard-heading" class="mt-1 text-2xl font-black tracking-tight text-gray-950 dark:text-white sm:text-3xl">Station Manager Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Track every SDM submitted to you, including pending reviews and memos forwarded to AM/Line.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('create', \App\Models\ServiceDeficiencyMemo::class)
                <a href="{{ route('sdm-memos.create') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-emerald-700 bg-white px-4 py-2 text-sm font-bold text-emerald-700 shadow-sm transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:bg-gray-800 dark:text-emerald-300 dark:hover:bg-emerald-950 dark:focus:ring-offset-gray-900">Create New SDM</a>
            @endcan
            <a href="{{ route('sdm-memos.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:border-emerald-300 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-emerald-700 dark:hover:text-emerald-300 dark:focus:ring-offset-gray-900">View All SDMs</a>
            <a href="{{ route('sdm-memos.index', ['category' => 'pending']) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">Open pending queue</a>
        </div>
    </div>

    <form method="GET" action="{{ route('dashboard') }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-5">
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
                <span class="mb-1.5 block text-xs font-bold text-gray-600 dark:text-gray-300">Current status</span>
                <select name="status" class="block min-h-10 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                    <option value="">All statuses</option>
                    @foreach ($dashboard['statuses'] as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex gap-2 sm:col-span-2 xl:col-span-1">
                <button type="submit" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">Apply</button>
                <a href="{{ route('dashboard') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-bold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
            </div>
        </div>
    </form>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        @foreach ([
            ['label' => 'Submitted to me', 'count' => $dashboard['total_submitted'], 'tone' => 'emerald'],
            ['label' => 'Generated by me', 'count' => $dashboard['generated_count'], 'tone' => 'amber'],
            ['label' => 'Forwarded to AM/Manager (Line)', 'count' => $dashboard['forwarded_count'] + $dashboard['generated_count'], 'tone' => 'cyan'],
        ] as $metric)
            <article @class([
                'rounded-2xl border p-5 shadow-sm',
                'border-emerald-700 bg-emerald-700 text-white' => $metric['tone'] === 'emerald',
                'border-amber-200 bg-white dark:border-amber-900 dark:bg-gray-800' => $metric['tone'] === 'amber',
                'border-cyan-200 bg-white dark:border-cyan-900 dark:bg-gray-800' => $metric['tone'] === 'cyan',
            ])>
                <p @class(['text-4xl font-black tracking-tight', 'text-gray-950 dark:text-white' => $metric['tone'] !== 'emerald'])>{{ $metric['count'] }}</p>
                <p @class(['mt-2 text-sm font-bold', 'text-emerald-50' => $metric['tone'] === 'emerald', 'text-gray-700 dark:text-gray-200' => $metric['tone'] !== 'emerald'])>{{ $metric['label'] }}</p>
            </article>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-5">
        <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 xl:col-span-3">
            <h2 class="text-lg font-black text-gray-950 dark:text-white">SDMs submitted over time</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Latest six months, with one line for each SDM type forwarded by you.</p>
            <div class="mt-5 h-72 min-w-0"><canvas x-ref="managerTrend" role="img" aria-label="Monthly SDMs forwarded by this Station Manager"></canvas></div>
        </article>
        <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 xl:col-span-2">
            <h2 class="text-lg font-black text-gray-950 dark:text-white">Station-wise SDMs</h2>
            {{-- <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Distribution of submissions by originating station.</p> --}}
            <div class="mx-auto mt-5 h-72 max-w-sm min-w-0">
                <canvas x-ref="managerStations" role="img" aria-label="SDMs submitted to this Station Manager grouped by station"></canvas>
            </div>
        </article>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-5">
        <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 xl:col-span-2">
            <h2 class="text-lg font-black text-gray-950 dark:text-white">SDMs by service</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Service mix for all matching submissions.</p>
            <div class="mx-auto mt-5 h-64 max-w-sm min-w-0"><canvas x-ref="managerServices" role="img" aria-label="SDMs submitted to this Station Manager grouped by service"></canvas></div>
        </article>

        <article class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 xl:col-span-3">
            <div class="flex items-end justify-between gap-3 border-b border-gray-100 px-5 py-5 dark:border-gray-700 sm:px-6">
                <div>
                    <h2 class="text-lg font-black text-gray-950 dark:text-white">Recent submissions</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Latest SDMs submitted to you.</p>
                </div>
                <a href="{{ route('sdm-memos.index') }}" class="shrink-0 text-sm font-bold text-emerald-700 hover:underline dark:text-emerald-400">View all →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/60">
                        <tr class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            {{-- <th scope="col" class="px-5 py-3 sm:px-6">SDM</th> --}}
                            <th scope="col" class="px-5 py-3 sm:px-6">Station</th>
                            <th scope="col" class="px-5 py-3 sm:px-6">Status</th>
                            <th scope="col" class="px-5 py-3 text-right sm:px-6">Submitted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($dashboard['recent_sdms'] as $memo)
                            <tr class="transition hover:bg-emerald-50/60 dark:hover:bg-emerald-950/20">
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300 sm:px-6">{{ $memo->station_name ?: 'Not recorded' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-gray-700 dark:text-gray-200 sm:px-6">{{ $memo->status->label() }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-600 dark:text-gray-300 sm:px-6">{{ $memo->submitted_at?->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">No SDMs match the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </div>
</section>
