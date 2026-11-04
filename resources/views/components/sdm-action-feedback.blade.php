@props(['messages' => null, 'type' => null])

@php
    if ($messages === null) {
        $messages = $errors->all();

        if (session('error')) {
            $messages[] = session('error');
        }

        $type = $messages ? 'error' : 'success';

        if (! $messages && (session('status') || session('success'))) {
            $messages[] = session('status') ?: session('success');
        }
    }

    $feedbackTitle = $type === 'error' ? 'SDM action could not be completed' : 'SDM action successful';
@endphp

<div
    data-sdm-action-feedback
    data-messages="{{ json_encode(array_values($messages)) }}"
    data-feedback-type="{{ $type ?? 'success' }}"
    x-data="sdmActionFeedback"
    @invalid.capture.window="showValidationError($event)"
>
    <dialog
        x-ref="dialog"
        aria-labelledby="sdm-action-feedback-title"
        aria-describedby="sdm-action-feedback-messages"
        @cancel.prevent="dismiss()"
        class="m-auto max-h-[85vh] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-2xl border-0 bg-white p-0 text-gray-900 shadow-2xl backdrop:bg-gray-950/60 dark:bg-gray-800 dark:text-gray-100"
    >
        <section class="flex flex-col gap-5 p-6 sm:p-7">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full" :class="type === 'error' ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'" aria-hidden="true">
                    <svg x-show="type === 'success'" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 4 4L19 6" /></svg>
                    <svg x-show="type === 'error'" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M10.3 3.9 2.1 18a2 2 0 0 0 1.7 3h16.4a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /></svg>
                </span>
                <h2 id="sdm-action-feedback-title" class="pt-1 text-xl font-bold" x-text="title">{{ $feedbackTitle }}</h2>
            </div>
            <ul id="sdm-action-feedback-messages" class="flex list-inside list-disc flex-col gap-2 break-words text-sm leading-6 text-gray-700 dark:text-gray-200" aria-live="polite">
                <template x-for="(message, index) in messages" :key="index">
                    <li x-text="message"></li>
                </template>
            </ul>
            <div class="flex justify-end">
                <button type="button" autofocus @click="dismiss()" class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">OK</button>
            </div>
        </section>
    </dialog>
    @if ($messages)
        <noscript>
            <div role="status" class="m-4 rounded-lg border border-gray-300 bg-white p-4 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <h2 class="font-bold">{{ $feedbackTitle }}</h2>
                <ul class="list-inside list-disc">
                    @foreach ($messages as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        </noscript>
    @endif
</div>
