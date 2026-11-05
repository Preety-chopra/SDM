@props(['memo'])

@if($remarks = $memo->revertRemarks())
    <p class="mt-2 flex max-w-xs items-start gap-2 whitespace-normal break-words text-xs font-normal leading-5 text-amber-800 dark:text-amber-200">
        <span aria-label="Reverted SDM" title="This SDM was reverted" class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 7 4 12l5 5" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M5 12h9a5 5 0 0 1 5 5v1" stroke-linecap="round" />
            </svg>
        </span>
        <span><span class="font-semibold">Revert remarks:</span> {{ $remarks }}</span>
    </p>
@endif
