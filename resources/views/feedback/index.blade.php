<x-app-layout title="User feedback">
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">User feedback</h2></x-slot>

    <div class="py-8">
        <div class="mx-auto flex max-w-5xl flex-col gap-5 px-4 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-600 dark:text-gray-300">Feedback from users, with the page they were viewing. Most recent submissions appear first.</p>
            @forelse ($feedback as $item)
                <article class="flex min-w-0 flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-col justify-between gap-2 sm:flex-row">
                        <div class="min-w-0">
                            <h3 class="break-words text-sm font-bold text-gray-900 dark:text-gray-100">{{ $item->user?->name ?? 'Deleted user' }}</h3>
                            @if ($item->user?->employee_number)
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item->user->employee_number }}</p>
                            @endif
                        </div>
                        <time datetime="{{ $item->created_at->toIso8601String() }}" class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $item->created_at->format('d M Y, h:i A') }}</time>
                    </div>
                    <p class="whitespace-pre-wrap break-words text-sm leading-6 text-gray-800 dark:text-gray-200">{{ $item->message }}</p>
                    <div class="flex min-w-0 flex-col gap-1 border-t border-gray-100 pt-3 dark:border-gray-700">
                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Submitted from</span>
                        <a href="{{ $item->page_url }}" target="_blank" rel="noopener noreferrer" class="break-all text-sm text-emerald-700 underline decoration-emerald-200 underline-offset-2 hover:text-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:text-emerald-300 dark:hover:text-emerald-100">{{ $item->page_url }}<span class="sr-only"> (opens in a new tab)</span></a>
                    </div>
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">No feedback has been submitted yet.</p>
            @endforelse
            {{ $feedback->links() }}
        </div>
    </div>
</x-app-layout>
