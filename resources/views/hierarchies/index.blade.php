<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">SDM hierarchy</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Map each approval role only to its immediate supervisor.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div class="overflow-hidden rounded-2xl border border-emerald-200 bg-emerald-50/70 dark:border-emerald-900 dark:bg-emerald-950/30">
                <div class="grid divide-y divide-emerald-200 text-center text-sm font-semibold text-emerald-950 lg:grid-cols-5 lg:divide-x lg:divide-y-0 dark:divide-emerald-900 dark:text-emerald-100">
                    @foreach(['Station', 'Station Manager', 'Assistant Manager', 'Manager (Operations)', 'DGM/O'] as $step)
                        @if(!$loop->last)
                            <div class="flex items-center justify-center gap-2 px-3 py-3">
                                <span>{{ $step }}</span>
                                <span aria-hidden="true" class="text-emerald-500">→</span>
                            </div>
                        @endif
                    @endforeach
                    <div class="px-3 py-3">DGM/O</div>
                </div>
            </div>

            <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Stations → Station Managers</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Station assignments are managed when creating or editing a Station Manager.</p>
                </div>
                <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach($hierarchies as $hierarchy)
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/60">
                            <p class="font-medium text-gray-900 dark:text-white">{{ $hierarchy->station->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $hierarchy->station->metroLine?->name }}</p>
                            <p class="mt-2 text-sm text-emerald-700 dark:text-emerald-300">{{ $hierarchy->stationManager?->name ?? 'Not assigned' }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="grid items-start gap-6 xl:grid-cols-3">
                @foreach($mappingLevels as $level)
                    @php
                        $title = match($level['subordinateRole']) {
                            \App\Enums\UserRole::StationManager => 'Station Managers → Assistant Managers',
                            \App\Enums\UserRole::AssistantManager => 'Assistant Managers → Managers (Operations)',
                            \App\Enums\UserRole::ManagerOperations => 'Managers (Operations) → DGM/O',
                            default => '',
                        };
                    @endphp
                    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Choose one immediate supervisor for each user.</p>
                        </div>

                        <div class="flex flex-col divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($level['subordinates'] as $subordinate)
                                @php($relationship = $reportingRelationships->get($subordinate->id))
                                <form method="POST" action="{{ route('hierarchies.update') }}" class="flex flex-col gap-3 p-4">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="subordinate_id" value="{{ $subordinate->id }}">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $subordinate->name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $subordinate->employee_number }}</p>
                                    </div>
                                    <div class="flex items-end gap-2">
                                        <div class="min-w-0 flex-1">
                                            <x-input-label :for="'supervisor-'.$subordinate->id" value="Reports to" />
                                            <select id="supervisor-{{ $subordinate->id }}" name="supervisor_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                                <option value="">Select supervisor</option>
                                                @foreach($level['supervisors'] as $supervisor)
                                                    <option value="{{ $supervisor->id }}" @selected((string) $relationship?->supervisor_id === (string) $supervisor->id)>{{ $supervisor->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">Save</button>
                                    </div>
                                </form>
                            @empty
                                <p class="p-5 text-sm text-gray-500 dark:text-gray-400">No enabled {{ $level['subordinateRole']->pluralLabel() }} found.</p>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
