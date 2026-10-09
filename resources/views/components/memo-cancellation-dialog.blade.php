@props(['memo'])

@php($dialogId = 'cancel-memo-'.$memo->getKey())

<div
    x-data="memoCancellationDialog"
    x-init="$watch('cancellationOpen', value => {
        document.body.classList.toggle('overflow-hidden', value)
        if (value) $nextTick(() => $refs.cancellationRemarks.focus())
    })"
    @keydown.escape.window="handleCancellationEscape"
>
    <button
        type="button"
        @click="openCancellation"
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
            :aria-labelledby="confirmationOpen ? '{{ $dialogId }}-confirmation-title' : '{{ $dialogId }}-remarks-title'"
            @click.self="closeCancellation"
        >
            <section
                x-show="cancellationOpen"
                x-transition
                @click.outside="closeCancellation"
                class="w-full max-w-md rounded-2xl border border-rose-200 bg-white p-6 shadow-xl dark:border-rose-900 dark:bg-gray-800"
            >
                <form
                    x-ref="cancellationForm"
                    method="POST"
                    action="{{ route('sdm-memos.workflow.store', $memo) }}"
                    @submit="if (!confirmationOpen) { $event.preventDefault(); reviewCancellation($refs.cancellationForm) }"
                >
                    @csrf
                    <div x-show="!confirmationOpen">
                        <h2 id="{{ $dialogId }}-remarks-title" class="text-lg font-semibold text-gray-950 dark:text-white">Cancellation remarks</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Enter the reason for cancelling this SDM.</p>

                        <div class="mt-5">
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

                        <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <button type="button" @click="closeCancellation" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Close</button>
                            <button type="button" @click="reviewCancellation($refs.cancellationForm)" class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">Review cancellation</button>
                        </div>
                    </div>

                    <div x-cloak x-show="confirmationOpen" x-effect="if (confirmationOpen) $nextTick(() => $refs.confirmCancellation.focus())">
                        <h2 id="{{ $dialogId }}-confirmation-title" class="text-lg font-semibold text-gray-950 dark:text-white">Confirm SDM cancellation?</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">This action will cancel the SDM and cannot be undone.</p>

                        <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-900 dark:bg-rose-950/40">
                            <p class="text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300">Cancellation remarks</p>
                            <p class="mt-1 whitespace-pre-wrap break-words text-sm text-gray-800 dark:text-gray-100" x-text="$refs.cancellationRemarks.value"></p>
                        </div>

                        <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <button type="button" @click="editCancellationRemarks" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back</button>
                            <button x-ref="confirmCancellation" name="action" value="cancel" class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">Confirm cancellation</button>
                        </div>
                    </div>
                </form>
            </section>
        </div>
    </template>
</div>
