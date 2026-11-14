<x-app-layout title="My feedback">
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">My feedback</h2></x-slot>

    <div class="mx-auto flex max-w-3xl flex-col gap-5 px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-sm text-gray-600 dark:text-gray-300">View your feedback and follow up after an administrator replies.</p>

        @forelse ($feedback as $item)
            <article class="flex min-w-0 flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Your feedback</h3>
                    <time datetime="{{ $item->created_at->toIso8601String() }}" class="text-xs text-gray-500 dark:text-gray-400">{{ $item->created_at->format('d M Y, h:i A') }}</time>
                </div>
                <p class="whitespace-pre-wrap break-words text-sm leading-6 text-gray-800 dark:text-gray-200">{{ $item->message }}</p>
                <x-feedback-source :url="$item->page_url" />

                @if ($item->replies->isNotEmpty())
                    <ol aria-label="Feedback conversation" class="flex flex-col gap-3">
                        @foreach ($item->replies as $reply)
                            <x-feedback-reply :reply="$reply" :author="$reply->from_sender ? 'You' : 'Administrator'" :unread="! $reply->from_sender && $reply->read_at === null" />
                        @endforeach
                    </ol>
                    <a href="{{ route('feedback.thread', $item) }}" class="self-start rounded-lg border border-emerald-200 px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-950">Open conversation to reply</a>
                @else
                    <p class="border-t border-gray-100 pt-3 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No replies yet.</p>
                @endif
            </article>
        @empty
            <p class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">You haven’t submitted any feedback yet.</p>
        @endforelse

        {{ $feedback->links() }}
    </div>
</x-app-layout>
