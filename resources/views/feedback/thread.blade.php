<x-app-layout title="My feedback conversation">
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">My feedback conversation</h2></x-slot>

    <div class="mx-auto flex max-w-3xl flex-col gap-5 px-4 py-8 sm:px-6 lg:px-8">
        <a href="{{ route('feedback.inbox') }}" class="self-start rounded text-sm font-semibold text-emerald-700 underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:text-emerald-300">Back to My feedback</a>

        @if (session('status'))
            <p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>
        @endif

        <article class="flex min-w-0 flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Your feedback</h3>
            <p class="whitespace-pre-wrap break-words text-sm leading-6 text-gray-800 dark:text-gray-200">{{ $feedback->message }}</p>
            <x-feedback-source :url="$feedback->page_url" />
        </article>

        @if ($feedback->replies->isNotEmpty())
            <ol aria-label="Feedback conversation" class="flex flex-col gap-3">
                @foreach ($feedback->replies as $reply)
                    <x-feedback-reply :reply="$reply" :author="$reply->from_sender ? 'You' : 'Administrator'" :unread="! $reply->from_sender && $reply->read_at === null" />
                @endforeach
            </ol>
            <x-feedback-reply-form :action="route('feedback.follow-up', $feedback)" label="Your reply" help="Administrators will see your reply with this feedback." />
        @else
            <p class="text-sm text-gray-600 dark:text-gray-300">You can reply here after an administrator responds.</p>
        @endif
    </div>
</x-app-layout>
