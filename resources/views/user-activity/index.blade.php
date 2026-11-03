<x-app-layout title="User activity">
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">User activity</h2></x-slot>

    <div class="py-8">
        <div class="mx-auto grid max-w-screen-2xl gap-6 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">Activity history</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Review user actions, sign-ins, page visits, downloads, and recorded changes. Newest activity appears first.</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">All times are in {{ config('app.timezone') }}. Page visits are recorded from the time activity tracking was enabled.</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Locations are approximate and based on the client IP visible to the application. Local/private networks have no geographic location. Location capture applies to new activity.</p>
                </div>
                <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ number_format($activities->total()) }} {{ Str::plural('activity', $activities->total()) }}</span>
            </div>

            <form method="GET" action="{{ route('user-activity.index') }}" class="grid gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:grid-cols-2 xl:grid-cols-4 dark:border-gray-700 dark:bg-gray-800">
                <div>
                    <x-input-label for="search" value="Search activity" />
                    <x-text-input id="search" name="search" :value="$filters['search'] ?? ''" class="mt-1 w-full" placeholder="Name, employee no., action, record ID" />
                    <x-input-error :messages="$errors->get('search')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="actor_id" value="User" />
                    <select id="actor_id" name="actor_id" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">All users</option>
                        @foreach ($actors as $actor)
                            <option value="{{ $actor->id }}" @selected(($filters['actor_id'] ?? '') == $actor->id)>{{ $actor->name }}{{ $actor->employee_number ? ' · '.$actor->employee_number : '' }} (#{{ $actor->id }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('actor_id')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="event" value="Action" />
                    <select id="event" name="event" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">All actions</option>
                        @foreach ($events as $event)
                            <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ $event }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('event')" class="mt-1" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><x-input-label for="from" value="From date" /><x-text-input id="from" type="date" name="from" :value="$filters['from'] ?? ''" class="mt-1 w-full text-sm" /><x-input-error :messages="$errors->get('from')" class="mt-1" /></div>
                    <div><x-input-label for="to" value="To date" /><x-text-input id="to" type="date" name="to" :value="$filters['to'] ?? ''" class="mt-1 w-full text-sm" /><x-input-error :messages="$errors->get('to')" class="mt-1" /></div>
                </div>
                <div class="flex items-center gap-4 sm:col-span-2 xl:col-span-4">
                    <x-primary-button>Apply filters</x-primary-button>
                    <a href="{{ route('user-activity.index') }}" class="rounded text-sm font-semibold text-gray-600 hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:text-gray-300 dark:hover:text-emerald-300">Reset filters</a>
                </div>
            </form>

            <section aria-label="Recorded user activity" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                            <tr><th scope="col" class="px-5 py-4">Time</th><th scope="col" class="px-5 py-4">User</th><th scope="col" class="px-5 py-4">Action</th><th scope="col" class="px-5 py-4">Record / page</th><th scope="col" class="px-5 py-4">IP address / location</th><th scope="col" class="px-5 py-4">Details</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($activities as $activity)
                                <tr class="align-top text-gray-700 dark:text-gray-300">
                                    <td class="whitespace-nowrap px-5 py-4"><time datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->format('d M Y, H:i:s') }}</time><span class="mt-1 block text-xs text-gray-400">Entry #{{ $activity->id }}</span></td>
                                    <td class="px-5 py-4"><span class="font-semibold text-gray-900 dark:text-gray-100">{{ $activity->actor?->name ?? 'System / deleted user' }}</span>@if ($activity->actor)<span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $activity->actor->employee_number ?: 'User #'.$activity->actor_id }}</span>@endif</td>
                                    <td class="px-5 py-4">
                                        <span class="font-semibold">
                                            @if ($activity->event === 'activity.request')
                                                {{ ($activity->after_values['status'] ?? 200) >= 400 ? 'Request failed' : (($activity->after_values['method'] ?? '') === 'GET' ? 'Viewed / downloaded' : 'Performed action') }}
                                                <span class="mt-1 block text-xs font-normal">{{ Str::headline(str_replace('.', ' ', $activity->after_values['route'] ?? $activity->after_values['resource'] ?? '')) }}</span>
                                            @else
                                                {{ Str::headline(str_replace('.', ' ', $activity->event)) }}
                                            @endif
                                        </span>
                                        <span class="mt-1 block font-mono text-xs text-gray-500 dark:text-gray-400">{{ $activity->event }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        @if ($activity->event === 'activity.request')
                                            <span class="font-mono text-xs">{{ $activity->after_values['method'] ?? '' }} {{ $activity->after_values['resource'] ?? '' }}</span>
                                            <span class="mt-1 block text-xs">HTTP {{ $activity->after_values['status'] ?? '' }} · {{ $activity->after_values['route'] ?? '' }}</span>
                                        @else
                                            {{ Str::headline(class_basename($activity->auditable_type)) }} #{{ $activity->auditable_id }}
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="block break-all font-mono text-xs">{{ $activity->ip_address ?? 'Not recorded' }}</span>
                                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $activity->location ?? 'Location unavailable' }}</span>
                                    </td>
                                    <td class="min-w-64 px-5 py-4">
                                        <details>
                                            <summary class="cursor-pointer rounded font-semibold text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:text-emerald-300">View details</summary>
                                            <div class="mt-3 grid gap-3 text-xs">
                                                @foreach (['Before' => $activity->before_values, 'After' => $activity->after_values] as $label => $values)
                                                    @if ($values !== null)
                                                        <div><p class="font-bold">{{ $label }}</p><pre class="mt-1 max-h-72 max-w-xl overflow-auto whitespace-pre-wrap break-words rounded-lg bg-gray-50 p-3 font-mono dark:bg-gray-900">{{ json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></div>
                                                    @endif
                                                @endforeach
                                                <dl class="grid gap-2"><div><dt class="font-bold">IP address</dt><dd>{{ $activity->ip_address ?? '—' }}</dd></div><div><dt class="font-bold">Browser / device</dt><dd class="max-w-xl break-words">{{ $activity->user_agent ?? '—' }}</dd></div><div><dt class="font-bold">Request ID</dt><dd>{{ $activity->request_id ?? '—' }}</dd></div></dl>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-14 text-center text-gray-500 dark:text-gray-400">No activity matches these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($activities->hasPages())<div class="border-t border-gray-200 p-5 dark:border-gray-700">{{ $activities->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
