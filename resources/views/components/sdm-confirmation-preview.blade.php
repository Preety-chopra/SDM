@props(['name' => 'sdm-action-confirmation'])

<x-modal :name="$name" max-width="lg" focusable>
    <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="sdm-confirmation-title"
        aria-describedby="sdm-confirmation-question"
        class="p-6 sm:p-7"
    >
        <div class="flex items-start gap-4">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300" aria-hidden="true">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.84 2.7 17a2 2 0 0 0 1.73 3h15.14a2 2 0 0 0 1.73-3L13.7 3.84a2 2 0 0 0-3.4 0Z" />
                </svg>
            </span>
            <div>
                <h2 id="sdm-confirmation-title" class="text-xl font-semibold text-gray-950 dark:text-white">Review SDM action</h2>
                <p id="sdm-confirmation-question" class="mt-1 text-sm font-semibold text-gray-700 dark:text-gray-200">Are you sure you want to proceed?</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Check the details below before continuing.</p>
            </div>
        </div>

        <dl class="mt-6 grid gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 sm:grid-cols-2 dark:border-gray-700 dark:bg-gray-900/70">
            <template x-for="item in sdmPreview" :key="`${item.label}-${item.value}`">
                <div class="min-w-0">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" x-text="item.label"></dt>
                    <dd class="mt-1 break-words text-sm font-medium text-gray-950 dark:text-gray-100" x-text="item.value"></dd>
                </div>
            </template>
        </dl>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button type="button" @click="cancelSdmConfirmation" :disabled="isSubmitting" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:ring-offset-gray-800">Cancel</button>
            <button type="button" @click="confirmSdmAction" :disabled="isSubmitting" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:focus:ring-offset-gray-800">
                <svg x-cloak x-show="isSubmitting" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/></svg>
                <span x-text="isSubmitting ? 'Processing…' : 'Proceed'"></span>
            </button>
        </div>
    </section>
</x-modal>
