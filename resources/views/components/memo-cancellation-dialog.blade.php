@props(['memo'])

@php($dialogId = 'cancel-memo-'.$memo->getKey())

<div
    x-data="{ cancellationOpen: false }"
    x-init="$watch('cancellationOpen', value => {
        document.body.classList.toggle('overflow-hidden', value)
        if (value) $nextTick(() => $refs.cancellationRemarks.focus())
    })"
    @keydown.escape.window="cancellationOpen = false"
>
    <button
        type="button"
        @click="cancellationOpen = true"
        :aria-expanded="cancellationOpen.toString()"
        aria-controls="{{ $dialogId }}"
        class="font-semibold text-rose-700 hover:underline focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 dark:text-rose-400 dark:focus:ring-offset-gray-800"
    >Cancel</button>

    <template x-teleport="body">
        <div
            x-cloak
            x-show="cancellationOpen"
            x-transition.opacity
            id="{{ $dialogId }}"
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-gray-950/60 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $dialogId }}-title"
            @click.self="cancellationOpen = false"
        >
            <section
                x-show="cancellationOpen"
                x-transition
                @click.outside="cancellationOpen = false"
                class="w-full max-w-md rounded-2xl border border-rose-200 bg-white p-6 shadow-xl dark:border-rose-900 dark:bg-gray-800"
            >
                <h2 id="{{ $dialogId }}-title" class="text-lg font-semibold text-gray-950 dark:text-white">Cancel SDM?</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Enter the reason for cancelling this SDM.</p>

                <form method="POST" action="{{ route('sdm-memos.workflow.store', $memo) }}" class="mt-5 flex flex-col gap-5">
                    @csrf
                    <div>
                        <label for="{{ $dialogId }}-remarks" class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Cancellation remarks</label>
                        <textarea
                            x-ref="cancellationRemarks"
                            id="{{ $dialogId }}-remarks"
                            name="remarks"
                            rows="4"
                            required
                            class="mt-2 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                        ></textarea>
                    </div>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" @click="cancellationOpen = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Close</button>
                        <button name="action" value="cancel" class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">Confirm cancellation</button>
                    </div>
                </form>
            </section>
        </div>
    </template>
</div>
