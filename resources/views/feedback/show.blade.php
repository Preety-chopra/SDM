<x-app-layout title="Reply to feedback">
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Feedback details</h2></x-slot>

    <div class="mx-auto flex max-w-3xl flex-col gap-5 px-4 py-8 sm:px-6 lg:px-8">
        <a href="{{ route('feedback.index') }}" class="self-start rounded text-sm font-semibold text-emerald-700 underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:text-emerald-300">Back to user feedback</a>

        @if (session('status'))
            <p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>
        @endif

        <article class="flex min-w-0 flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <x-feedback-sender :feedback="$feedback" />
                <time datetime="{{ $feedback->created_at->toIso8601String() }}" class="text-xs text-gray-500 dark:text-gray-400">{{ $feedback->created_at->format('d M Y, h:i A') }}</time>
            </div>
            <p class="whitespace-pre-wrap break-words text-sm leading-6 text-gray-800 dark:text-gray-200">{{ $feedback->message }}</p>
            <x-feedback-source :url="$feedback->page_url" />
        </article>

        @if ($feedback->replies->isNotEmpty())
            <section aria-labelledby="previous-feedback-replies" class="flex flex-col gap-3">
                <h3 id="previous-feedback-replies" class="text-sm font-bold text-gray-900 dark:text-gray-100">Conversation</h3>
                <ol class="flex flex-col gap-3">
                    @foreach ($feedback->replies as $reply)
                        <x-feedback-reply :reply="$reply" :author="$reply->from_sender ? 'Sender · '.($reply->author?->name ?? 'Deleted user') : ($reply->author?->name ?? 'Administrator')" :unread="$reply->from_sender && $reply->read_at === null" />
                    @endforeach
                </ol>
            </section>
        @endif

        @can('reply', $feedback)
            <x-feedback-reply-form :action="route('feedback.reply', $feedback)" :label="'Reply to '.$feedback->user->name" help="The sender will see your reply in My feedback." />
        @else
            <p class="text-sm text-gray-600 dark:text-gray-300">The sender’s account has been deleted, so this feedback can no longer receive replies.</p>
        @endcan
    </div>
</x-app-layout>
