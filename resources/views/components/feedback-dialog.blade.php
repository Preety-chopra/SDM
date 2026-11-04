<div x-data="feedbackForm" data-submit-url="{{ route('feedback.store') }}" @open-feedback.window="open()">
    <dialog
        data-feedback-dialog
        x-ref="dialog"
        aria-labelledby="feedback-title"
        aria-describedby="feedback-description"
        @cancel.prevent="close()"
        class="m-auto max-h-[85vh] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-2xl border-0 bg-white p-0 text-gray-900 shadow-2xl backdrop:bg-gray-950/60 dark:bg-gray-800 dark:text-gray-100"
    >
        <section class="flex flex-col gap-5 p-6 sm:p-7">
            <div class="flex items-start justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <h2 id="feedback-title" class="text-xl font-bold">Send feedback</h2>
                    <p id="feedback-description" class="text-sm text-gray-600 dark:text-gray-300">Tell us what could be improved. Your current page URL is included automatically.</p>
                </div>
                <button type="button" @click="close()" :disabled="submitting" aria-label="Close feedback" class="shrink-0 rounded-lg px-3 py-1 text-xl hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-50 dark:hover:bg-gray-700">&times;</button>
            </div>
            <template x-if="success">
                <div class="flex flex-col gap-5">
                    <p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200" x-text="success"></p>
                    <button type="button" @click="close()" class="self-end rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">Done</button>
                </div>
            </template>
            <template x-if="!success">
                <form method="POST" action="{{ route('feedback.store') }}" @submit.prevent="submit()" novalidate>
                    @csrf
                    <fieldset :disabled="submitting" :aria-busy="submitting" class="flex min-w-0 flex-col gap-4">
                        <div class="flex flex-col gap-2">
                            <label for="feedback-message" class="text-sm font-semibold">Your feedback</label>
                            <textarea id="feedback-message" name="message" x-model="message" rows="5" maxlength="5000" required aria-describedby="feedback-limit feedback-errors" autofocus class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" placeholder="Describe your suggestion or the issue you encountered."></textarea>
                            <p id="feedback-limit" class="text-xs text-gray-500 dark:text-gray-400">Maximum 5,000 characters.</p>
                        </div>
                        <div class="min-w-0 rounded-lg bg-gray-50 p-3 text-xs text-gray-600 dark:bg-gray-900 dark:text-gray-300">
                            <span class="font-semibold">Page:</span> <span class="break-all" x-text="pageUrl"></span>
                        </div>
                        <ul id="feedback-errors" aria-live="assertive" class="flex flex-col gap-1 text-sm text-rose-700 dark:text-rose-300">
                            <template x-for="(error, index) in errors" :key="index"><li x-text="error"></li></template>
                        </ul>
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="close()" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:hover:bg-gray-700">Cancel</button>
                            <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-50" x-text="submitting ? 'Submitting…' : 'Submit feedback'">Submit feedback</button>
                        </div>
                    </fieldset>
                </form>
            </template>
        </section>
    </dialog>
</div>
