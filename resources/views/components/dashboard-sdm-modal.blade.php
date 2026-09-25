<div
    x-id="['chart-sdm-modal-title']"
    x-cloak
    x-show="chartModalOpen"
    x-transition.opacity
    class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
    role="dialog"
    aria-modal="true"
    :aria-labelledby="$id('chart-sdm-modal-title')"
    @keydown.escape.window="closeChartModal()"
>
    <button type="button" class="absolute inset-0 cursor-default bg-gray-950/60" aria-label="Close SDM list" @click="closeChartModal()"></button>

    <div class="relative flex max-h-[85vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-700 sm:px-6">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-400">Selected chart bar</p>
                <h2 :id="$id('chart-sdm-modal-title')" class="mt-1 text-xl font-black text-gray-950 dark:text-white" x-text="chartModalTitle"></h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Select an SDM below to open its full details.</p>
            </div>
            <button x-ref="chartModalClose" type="button" class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl border border-gray-300 text-xl font-bold text-gray-600 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700" aria-label="Close SDM list" @click="closeChartModal()">&times;</button>
        </div>

        <div class="min-h-48 overflow-auto">
            <div x-show="chartModalLoading" class="flex min-h-48 items-center justify-center px-6 py-12 text-sm font-semibold text-gray-500 dark:text-gray-400" role="status">Loading SDMs…</div>

            <div x-show="!chartModalLoading && chartModalError" class="m-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200" role="alert" x-text="chartModalError"></div>

            <div x-show="!chartModalLoading && !chartModalError && chartMemos.length === 0" class="flex min-h-48 items-center justify-center px-6 py-12 text-sm text-gray-500 dark:text-gray-400">No SDMs match the selected chart bar.</div>

            <table x-show="!chartModalLoading && !chartModalError && chartMemos.length > 0" class="min-w-full divide-y divide-gray-200 text-left dark:divide-gray-700">
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
                    <template x-for="memo in chartMemos" :key="memo.id">
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

        <p x-show="chartMemosTruncated" class="border-t border-amber-200 bg-amber-50 px-5 py-3 text-xs font-semibold text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200 sm:px-6">Showing the 100 most recent matching SDMs.</p>
    </div>
</div>
