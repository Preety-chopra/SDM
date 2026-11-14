@props(['reply', 'author' => 'Administrator', 'unread' => false])

<li class="flex min-w-0 flex-col gap-2 rounded-xl bg-emerald-50 p-4 dark:bg-emerald-950/40">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <span class="break-all text-sm font-semibold text-emerald-900 dark:text-emerald-200">{{ $author }}</span>
            @if ($unread)
                <span class="rounded-full bg-emerald-700 px-2 py-0.5 text-xs font-semibold text-white">New</span>
            @endif
        </div>
        <time datetime="{{ $reply->created_at->toIso8601String() }}" class="text-xs text-gray-600 dark:text-gray-400">{{ $reply->created_at->format('d M Y, h:i A') }}</time>
    </div>
    <p class="whitespace-pre-wrap break-words text-sm leading-6 text-gray-800 dark:text-gray-200">{{ $reply->message }}</p>
</li>
