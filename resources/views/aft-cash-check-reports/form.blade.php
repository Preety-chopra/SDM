@php
    $initialEntries = old('entries', $report->exists ? $report->entries->map(fn ($entry) => [
        ...$entry->only(['station_id', 'check_type', 'tom_number', 'operator_name', 'employee_number', 'employer', 'eos_number', 'cash_as_per_eos', 'actual_cash', 'hidden_amount', 'cash_deposit_at_station']),
        'check_date' => $entry->check_date?->toDateString(), 'check_time' => substr((string) $entry->check_time, 0, 5),
    ])->all() : []);
    $stationOptions = $stations->map(fn ($station) => ['id' => $station->id, 'line_code' => $station->metroLine?->code]);
@endphp
<x-app-layout title="AFT Cash Check Report">
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><p class="text-xs font-semibold uppercase tracking-widest text-emerald-700 dark:text-emerald-400">Anti Fraud Team</p><h1 class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ $report->exists ? 'Edit '.$report->report_number : 'AFT Cash Check Report' }}</h1></div>
            <a href="{{ route('aft-cash-check-reports.index') }}" class="text-sm font-semibold text-emerald-700 hover:underline dark:text-emerald-400">View reports</a>
        </div>
    </x-slot>
    <div class="py-8"><div class="mx-auto flex max-w-6xl flex-col gap-5 px-4 sm:px-6 lg:px-8">
        <x-auth-session-status :status="session('status')" />
        <x-input-error :messages="$errors->all()" />
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-100">
            Add one row for each cash check. Save an unfinished report as a draft, or complete the starred fields and submit it to DGM AFT. Remarks, cumulative negative points and penalty amounts will be completed by CMC Staff during review.
        </div>
        <form method="POST" action="{{ $report->exists ? route('aft-cash-check-reports.update', $report) : route('aft-cash-check-reports.store') }}" x-data="aftCashCheckForm" data-entries="{{ json_encode($initialEntries) }}" data-stations="{{ $stationOptions->toJson() }}" data-today="{{ now()->toDateString() }}" class="flex flex-col gap-5">
            @csrf
            @if($report->exists) @method('PUT') @endif
            <noscript><p class="rounded-lg border border-amber-300 p-4 text-sm text-amber-900 dark:text-amber-200">Enable JavaScript to add and edit cash-check rows.</p></noscript>
            <template x-for="(row, index) in entries" :key="row._key">
                <section class="flex flex-col gap-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800" :aria-labelledby="'cash-row-title-' + row._key">
                    <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-4 dark:border-gray-700">
                        <h2 :id="'cash-row-title-' + row._key" class="text-lg font-bold text-gray-900 dark:text-white">Cash check <span x-text="index + 1"></span></h2>
                        <button type="button" @click="remove(index)" :disabled="entries.length === 1" class="rounded-lg px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40 dark:text-rose-300 dark:hover:bg-rose-950" :aria-label="'Remove cash check ' + (index + 1)">Remove</button>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <x-aft-cash-check-input field="check_date" label="Date" type="date" />
                        <div>
                            <label :for="'cash-' + row._key + '-station'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Station <span aria-hidden="true" class="font-bold text-red-600 dark:text-red-400">*</span></label>
                            <select :id="'cash-' + row._key + '-station'" :name="'entries[' + index + '][station_id]'" x-model="row.station_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                                <option value="">Choose station</option>
                                @foreach($stations as $station)<option value="{{ $station->id }}">{{ $station->code }} · {{ $station->name }} ({{ $station->metroLine?->code }})</option>@endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Line: <span x-text="lineFor(row)"></span></p>
                        </div>
                        <x-aft-cash-check-input field="check_type" label="Type of check" />
                        <x-aft-cash-check-input field="tom_number" label="TOM / TVM No." />
                        <x-aft-cash-check-input field="check_time" label="Time (IST)" type="time" />
                        <x-aft-cash-check-input field="operator_name" label="Name of operator" :max-length="255" />
                        <x-aft-cash-check-input field="employee_number" label="Employee ID" />
                        <x-aft-cash-check-input field="employer" label="DMRC / Contractor" />
                        <x-aft-cash-check-input field="eos_number" label="EOS No." />
                    </div>
                    <div class="grid gap-5 border-t border-gray-100 pt-5 sm:grid-cols-2 lg:grid-cols-4 dark:border-gray-700">
                        <x-aft-cash-check-input field="cash_as_per_eos" label="Cash as per EOS" type="number" :money="true" />
                        <x-aft-cash-check-input field="actual_cash" label="Actual cash" type="number" :money="true" />
                        <x-aft-cash-check-input field="hidden_amount" label="Hidden amount" type="number" :money="true" />
                        <x-aft-cash-check-input field="cash_deposit_at_station" label="Cash deposit at station earnings" type="number" :money="true" />
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-gray-50 p-4 text-sm dark:bg-gray-900" aria-live="polite">
                        <p class="text-gray-600 dark:text-gray-300">Difference (actual cash − cash as per EOS): <strong class="text-gray-900 dark:text-white" x-text="cashSummary(row).amount"></strong></p>
                        <span class="rounded-full bg-white px-3 py-1 font-bold text-gray-800 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-700" x-text="cashSummary(row).status"></span>
                    </div>
                </section>
            </template>
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <button type="button" @click="add()" :disabled="entries.length >= 100" class="rounded-lg border border-emerald-600 px-4 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-50 disabled:opacity-40 dark:text-emerald-300 dark:hover:bg-emerald-950">Add cash check</button>
                <div class="flex flex-wrap gap-3">
                    <button name="action" value="save" formnovalidate class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Save draft</button>
                    <button name="action" value="submit" class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">Submit to DGM AFT</button>
                </div>
            </div>
        </form>
    </div></div>
</x-app-layout>
