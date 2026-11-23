@if($workflowRemarks !== [])
    <section aria-label="SDM remarks" class="rounded-2xl border border-amber-200 bg-amber-50 p-6 shadow-sm dark:border-amber-900 dark:bg-amber-950/40">
        <h3 class="font-semibold text-gray-900 dark:text-white">SDM remarks</h3>
        <ol class="mt-4 flex flex-col gap-3">
            @foreach($workflowRemarks as $remark)
                <li class="min-w-0 rounded-xl border border-amber-200 bg-white p-4 dark:border-amber-900 dark:bg-gray-800">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $remark['label'] }}</p>
                            <p class="mt-1 break-words text-sm text-gray-600 dark:text-gray-300">Remarks by <strong>{{ $remark['author'] }}</strong></p>
                        </div>
                        @if($remark['acted_at'])
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $remark['acted_at']->format('d-m-Y H:i') }}</p>
                        @endif
                    </div>
                    <p class="mt-3 whitespace-pre-wrap break-words text-sm text-gray-800 dark:text-gray-100">{{ $remark['remarks'] }}</p>
                </li>
            @endforeach
        </ol>
    </section>
@endif
