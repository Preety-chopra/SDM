@props(['dashboard'])

@php
    $filters = $dashboard['filters'];
    $total = max(1, $dashboard['total_received']);
    $chartConfig = [
        'monthlyTrend' => $dashboard['monthly_trend'],
        'lineDistribution' => $dashboard['line_distribution'],
        'serviceDistribution' => $dashboard['service_distribution'],
        'lineMemosUrl' => route('dashboard.line-sdms'),
        'filters' => $filters,
    ];
@endphp

<section aria-labelledby="line-manager-dashboard-heading" class="flex flex-col gap-6" x-data="lineManagerDashboardCharts(@js($chartConfig))">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
            {{-- <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-400">My line review portfolio</p> --}}
            <h1 id="line-manager-dashboard-heading" class="mt-1 text-2xl font-black tracking-tight text-gray-950 dark:text-white sm:text-3xl">AM/Manager (Line) Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Track every SDM received for line review, including pending, forwarded, and cancelled cases.</p>
        </div>
        <div class="flex flex-wrap gap-2">
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
                        <option value="{{ $status['value'] }}" @selected($filters['status'] === $status['value'])>{{ $status['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex gap-2 sm:col-span-2 xl:col-span-1">
                <button type="submit" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">Apply</button>
                <a href="{{ route('dashboard') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-bold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
            </div>
        </div>
    </form>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Total received', 'count' => $dashboard['total_received'], 'tone' => 'emerald'],
            ['label' => 'Pending with me', 'count' => $dashboard['pending_count'], 'tone' => 'amber'],
            ['label' => 'Forwarded onward', 'count' => $dashboard['forwarded_count'], 'tone' => 'cyan'],
            ['label' => 'Cancelled at line review', 'count' => $dashboard['cancelled_count'], 'tone' => 'rose'],
        ] as $metric)
            <article @class([
                'rounded-2xl border p-5 shadow-sm',
                'border-emerald-700 bg-emerald-700 text-white' => $metric['tone'] === 'emerald',
                'border-amber-200 bg-white dark:border-amber-900 dark:bg-gray-800' => $metric['tone'] === 'amber',
                'border-cyan-200 bg-white dark:border-cyan-900 dark:bg-gray-800' => $metric['tone'] === 'cyan',
                'border-rose-200 bg-white dark:border-rose-900 dark:bg-gray-800' => $metric['tone'] === 'rose',
            ])>
                <p @class(['text-4xl font-black tracking-tight', 'text-gray-950 dark:text-white' => $metric['tone'] !== 'emerald'])>{{ $metric['count'] }}</p>
                <p @class(['mt-2 text-sm font-bold', 'text-emerald-50' => $metric['tone'] === 'emerald', 'text-gray-700 dark:text-gray-200' => $metric['tone'] !== 'emerald'])>{{ $metric['label'] }}</p>
                {{-- <p @class(['mt-3 text-xs font-medium', 'text-emerald-100' => $metric['tone'] === 'emerald', 'text-gray-500 dark:text-gray-400' => $metric['tone'] !== 'emerald'])>{{ round(($metric['count'] / $total) * 100, 1) }}% of selected total</p> --}}
            </article>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-5">
        <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 xl:col-span-3">
            <h2 class="text-lg font-black text-gray-950 dark:text-white">SDMs received over time</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Latest six months, with one line for each SDM type received by you.</p>
            <div class="mt-5 h-72 min-w-0"><canvas x-ref="lineManagerTrend" role="img" aria-label="Monthly SDMs received by this AM or Line Manager by service type"></canvas></div>
        </article>
        <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 xl:col-span-2">
            <h2 class="text-lg font-black text-gray-950 dark:text-white">SDMs by service</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Service mix for all matching received SDMs.</p>
            <div class="mx-auto mt-5 h-64 max-w-sm min-w-0"><canvas x-ref="lineManagerServices" role="img" aria-label="Received SDMs grouped by service"></canvas></div>
        </article>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-5">
        <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 xl:col-span-3">
            <h2 class="text-lg font-black text-gray-950 dark:text-white">Line-wise received SDMs</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Received SDM volume grouped by originating line. Select a bar to view its SDMs.</p>
            <div class="mt-5 h-72 min-w-0"><canvas x-ref="lineManagerLines" role="img" aria-label="Received SDMs grouped by line"></canvas></div>
        </article>

        <article class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 xl:col-span-2">
            <div class="flex items-end justify-between gap-3 border-b border-gray-100 px-5 py-5 dark:border-gray-700 sm:px-6">
                <div>
                    <h2 class="text-lg font-black text-gray-950 dark:text-white">Recent received SDMs</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Latest SDMs routed to you.</p>
                </div>
                <a href="{{ route('sdm-memos.index') }}" class="shrink-0 text-sm font-bold text-emerald-700 hover:underline dark:text-emerald-400">View all →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/60">
                        <tr class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            {{-- <th scope="col" class="px-5 py-3 sm:px-6">SDM</th> --}}
                            <th scope="col" class="px-5 py-3 sm:px-6">Line</th>
                            <th scope="col" class="px-5 py-3 sm:px-6">Status</th>
                            <th scope="col" class="px-5 py-3 text-right sm:px-6">Received</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($dashboard['recent_sdms'] as $memo)
                            <tr class="transition hover:bg-emerald-50/60 dark:hover:bg-emerald-950/20">
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300 sm:px-6">{{ $memo->line_code ?: 'Not recorded' }}</td>
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

    <div
        x-cloak
        x-show="lineModalOpen"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
        role="dialog"
        aria-modal="true"
        aria-labelledby="line-sdm-modal-title"
        @keydown.escape.window="closeLineModal()"
    >
        <button type="button" class="absolute inset-0 cursor-default bg-gray-950/60" aria-label="Close SDM list" @click="closeLineModal()"></button>

        <div class="relative flex max-h-[85vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-700 sm:px-6">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-400">Line-wise received SDMs</p>
                    <h2 id="line-sdm-modal-title" class="mt-1 text-xl font-black text-gray-950 dark:text-white">
                        <span x-text="selectedLineCode"></span> SDMs
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Select an SDM below to open its full details.</p>
                </div>
                <button x-ref="lineModalClose" type="button" class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl border border-gray-300 text-xl font-bold text-gray-600 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700" aria-label="Close SDM list" @click="closeLineModal()">&times;</button>
            </div>

            <div class="min-h-48 overflow-auto">
                <div x-show="lineModalLoading" class="flex min-h-48 items-center justify-center px-6 py-12 text-sm font-semibold text-gray-500 dark:text-gray-400" role="status">Loading SDMs…</div>

                <div x-show="!lineModalLoading && lineModalError" class="m-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200" role="alert" x-text="lineModalError"></div>

                <div x-show="!lineModalLoading && !lineModalError && lineMemos.length === 0" class="flex min-h-48 items-center justify-center px-6 py-12 text-sm text-gray-500 dark:text-gray-400">No SDMs match the selected dashboard filters.</div>

                <table x-show="!lineModalLoading && !lineModalError && lineMemos.length > 0" class="min-w-full divide-y divide-gray-200 text-left dark:divide-gray-700">
                    <thead class="sticky top-0 bg-gray-50 dark:bg-gray-900">
                        <tr class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            <th scope="col" class="px-5 py-3 sm:px-6">Action</th>
                            <th scope="col" class="px-5 py-3">Service</th>
                            <th scope="col" class="px-5 py-3">Station</th>
                            <th scope="col" class="px-5 py-3">Status</th>
                            <th scope="col" class="px-5 py-3 text-right sm:px-6">Received</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <template x-for="memo in lineMemos" :key="memo.id">
                            <tr class="transition hover:bg-emerald-50/60 dark:hover:bg-emerald-950/20">
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-black sm:px-6"><a :href="memo.url" class="text-emerald-700 hover:underline focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:text-emerald-400">Open SDM</a></td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300" x-text="memo.service"></td>
                                <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300" x-text="memo.station"></td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-gray-700 dark:text-gray-200" x-text="memo.status"></td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-600 dark:text-gray-300 sm:px-6" x-text="memo.submitted_at || 'Not recorded'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <p x-show="lineMemosTruncated" class="border-t border-amber-200 bg-amber-50 px-5 py-3 text-xs font-semibold text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200 sm:px-6">Showing the 100 most recent matching SDMs.</p>
        </div>
    </div>
</section>
