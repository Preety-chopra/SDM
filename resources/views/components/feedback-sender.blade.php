@props(['feedback'])

<div class="min-w-0">
    <h3 class="break-words text-sm font-bold text-gray-900 dark:text-gray-100">{{ $feedback->user?->name ?? 'Deleted user' }}</h3>
    @if ($feedback->user?->employee_number)
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $feedback->user->employee_number }}</p>
    @endif
    @if ($feedback->station_name !== null)
        <p class="break-words text-xs text-gray-600 dark:text-gray-300"><span class="font-semibold">Sign-in station:</span> {{ $feedback->station_name }}</p>
    @endif
</div>
