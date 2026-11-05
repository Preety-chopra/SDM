@props(['count'])

<x-modal name="new-feedback-summary" :show="true" maxWidth="lg" focusable>
    <section role="dialog" aria-modal="true" aria-labelledby="new-feedback-title" aria-describedby="new-feedback-description" class="p-6 sm:p-7">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-400">User feedback</p>
                <h2 id="new-feedback-title" class="mt-1 text-xl font-black text-gray-950 dark:text-white">New feedback received</h2>
                <p id="new-feedback-description" class="mt-1 text-sm text-gray-600 dark:text-gray-300">New submissions since your last visit to the feedback page.</p>
            </div>
            <button type="button" x-on:click="$dispatch('close')" aria-label="Close new feedback notification" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-300 text-xl font-bold text-gray-600 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">&times;</button>
        </div>

        <a href="{{ route('feedback.index') }}" class="mt-5 block rounded-xl border border-emerald-200 bg-emerald-50 p-5 transition hover:border-emerald-400 hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-emerald-900 dark:bg-emerald-950/40 dark:hover:border-emerald-700 dark:hover:bg-emerald-950 dark:focus:ring-offset-gray-800">
            <dl>
                <dt class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">New feedback</dt>
                <dd class="mt-2 text-3xl font-black text-emerald-950 dark:text-emerald-100">{{ $count }}</dd>
            </dl>
            <p class="mt-3 text-sm font-semibold text-emerald-700 dark:text-emerald-300">Open feedback page &rarr;</p>
        </a>

        <div class="mt-6 flex justify-end">
            <button type="button" x-on:click="$dispatch('close')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Dismiss</button>
        </div>
    </section>
</x-modal>
