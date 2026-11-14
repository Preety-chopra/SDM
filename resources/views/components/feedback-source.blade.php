@props(['url'])

<div class="flex min-w-0 flex-col gap-1">
    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Submitted from</span>
    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="break-all text-sm text-emerald-700 underline decoration-emerald-200 underline-offset-2 hover:text-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:text-emerald-300 dark:hover:text-emerald-100">{{ $url }}<span class="sr-only"> (opens in a new tab)</span></a>
</div>
