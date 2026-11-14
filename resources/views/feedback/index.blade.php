<x-app-layout title="User feedback">
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">User feedback</h2></x-slot>

    <div class="py-8">
        <div class="mx-auto flex max-w-5xl flex-col gap-5 px-4 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-600 dark:text-gray-300">Feedback from users, with the page they were viewing. Most recent submissions appear first.</p>
            @forelse ($feedback as $item)
                <article class="flex min-w-0 flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-col justify-between gap-2 sm:flex-row">
                        <div class="flex min-w-0 flex-wrap items-start gap-2">
                            <x-feedback-sender :feedback="$item" />
                            @if ($item->has_unread_follow_ups)
                                <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">New follow-up</span>
                            @endif
                        </div>
                        <time datetime="{{ $item->created_at->toIso8601String() }}" class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $item->created_at->format('d M Y, h:i A') }}</time>
                    </div>
                    <p class="whitespace-pre-wrap break-words text-sm leading-6 text-gray-800 dark:text-gray-200">{{ $item->message }}</p>
                    <div class="flex min-w-0 flex-wrap items-end justify-between gap-3 border-t border-gray-100 pt-3 dark:border-gray-700">
                        <x-feedback-source :url="$item->page_url" />
                        <a href="{{ route('feedback.show', $item) }}" class="rounded-lg border border-emerald-200 px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-950">
                            {{ $item->replies_count ? trans_choice('View :count reply|View :count replies', $item->replies_count) : ($item->user ? 'Reply' : 'View feedback') }}
                        </a>
                    </div>
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">No feedback has been submitted yet.</p>
            @endforelse
            {{ $feedback->links() }}
        </div>
    </div>
</x-app-layout>
