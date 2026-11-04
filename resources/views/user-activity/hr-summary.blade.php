<section aria-labelledby="hr-summary-title" class="grid min-w-0 gap-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="grid gap-2">
        <h3 id="hr-summary-title" class="text-lg font-bold text-gray-900 dark:text-white">HR activity summary</h3>
        <p class="text-sm text-gray-600 dark:text-gray-300">Employee contribution to SDM creation and movement through the review process.</p>
        <p class="text-xs text-gray-500 dark:text-gray-400">Scope: {{ ($filters['actor_id'] ?? null) ? ($actors->firstWhere('id', (int) $filters['actor_id'])?->name ?? 'Selected user') : 'All users' }} · {{ $filters['from'] ?? 'All dates' }} to {{ $filters['to'] ?? 'present' }}. The User and date filters apply here. Search activity and Action filter only the detailed history below.</p>
    </div>

    <dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-activity-summary-stat label="SDMs created" :value="$hrSummary['totals']['created']" description="Includes drafts and direct submissions" />
        <x-activity-summary-stat label="SDMs forwarded" :value="$hrSummary['totals']['forwarded']" description="Unique SDMs sent onward at least once" :highlight="true" />
        <x-activity-summary-stat label="Forwarding actions" :value="$hrSummary['totals']['forward_actions']" description="Every completed onward movement" />
        <x-activity-summary-stat label="SDMs approved" :value="$hrSummary['totals']['approved']" description="Unique SDMs with final DGM approval" />
    </dl>

    <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">Creation uses the original creation date; forwarding and approval use the date the action was completed, including actions on older SDMs. One SDM may pass through several levels or repeat a review round, so level and employee counts can overlap. Forwarding includes staff assignment, verification, and cancellation requests sent to the next level. Returns, cancellations, and pending steps are excluded. Counts use saved SDMs and workflow records, including archived SDMs; permanently deleted drafts are unavailable.</p>

    <details class="min-w-0 rounded-xl border border-gray-200 dark:border-gray-700">
        <summary class="cursor-pointer rounded-xl p-4 font-semibold text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:text-emerald-300">Forwarding by level</summary>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <caption class="px-4 pb-3 text-left text-xs text-gray-500 dark:text-gray-400">The level shown is where the action was completed. Select a User above to review their contribution at each level.</caption>
                <thead class="bg-gray-50 text-left text-xs text-gray-600 dark:bg-gray-900 dark:text-gray-300"><tr><th scope="col" class="px-4 py-3">Workflow level</th><th scope="col" class="px-4 py-3 text-right">SDMs forwarded</th><th scope="col" class="px-4 py-3 text-right">Forwarding actions</th><th scope="col" class="px-4 py-3 text-right">SDMs approved</th></tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($hrSummary['levels'] as $level)
                        <tr class="text-gray-700 dark:text-gray-300"><th scope="row" class="px-4 py-3 text-left font-medium">{{ $level['label'] }}</th><td class="px-4 py-3 text-right tabular-nums">{{ number_format($level['forwarded']) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ number_format($level['forward_actions']) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ number_format($level['approved']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>

    <details class="min-w-0 rounded-xl border border-gray-200 dark:border-gray-700">
        <summary class="cursor-pointer rounded-xl p-4 font-semibold text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:text-emerald-300">Employee contribution <span class="text-xs font-normal">({{ number_format($hrSummary['people']->count()) }} {{ Str::plural('user', $hrSummary['people']->count()) }})</span></summary>
        <div class="max-h-96 overflow-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <caption class="px-4 pb-3 text-left text-xs text-gray-500 dark:text-gray-400">Credit goes to the original creator or the person who completed the action. These counts describe recorded work volume.</caption>
                <thead class="bg-gray-50 text-left text-xs text-gray-600 dark:bg-gray-900 dark:text-gray-300"><tr><th scope="col" class="px-4 py-3">User / employee number</th><th scope="col" class="px-4 py-3 text-right">SDMs created</th><th scope="col" class="px-4 py-3 text-right">SDMs forwarded</th><th scope="col" class="px-4 py-3 text-right">Forwarding actions</th><th scope="col" class="px-4 py-3 text-right">SDMs approved</th></tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($hrSummary['people'] as $person)
                        <tr class="text-gray-700 dark:text-gray-300"><th scope="row" class="px-4 py-3 text-left font-medium">{{ $person['name'] }}<span class="mt-1 block text-xs font-normal text-gray-500 dark:text-gray-400">{{ $person['employee_number'] ?: ($person['id'] ? 'User #'.$person['id'] : 'User unavailable') }}</span></th><td class="px-4 py-3 text-right tabular-nums">{{ number_format($person['created']) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ number_format($person['forwarded']) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ number_format($person['forward_actions']) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ number_format($person['approved']) }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No SDM creation or completed forwarding activity matches the selected user and dates.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </details>
</section>
