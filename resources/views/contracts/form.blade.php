<x-app-layout :title="$contract ? 'Edit contract draft' : 'Create service contract'">
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $contract ? 'Edit contract draft' : 'Create service contract' }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Published versions are immutable and must be revised.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
            @if(!$contract)
                <nav class="flex gap-2" aria-label="Contract service type">
                    @foreach($serviceTypes as $type)
                        <a href="{{ route('contracts.create', ['service_type' => $type->value]) }}" class="rounded-full px-4 py-2 text-sm font-semibold {{ $type === $serviceType ? 'bg-emerald-700 text-white' : 'border border-gray-300 bg-white text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">{{ $type->code() }}</a>
                    @endforeach
                </nav>
            @endif

            @php
                $selectedLineIds = array_map('strval', old('line_ids', $contract?->lines->pluck('id')->all() ?? []));
                $selectedStationIds = array_map('strval', old('station_ids', $contract?->stations->pluck('id')->all() ?? []));
            @endphp
            <form method="POST" enctype="multipart/form-data" action="{{ $contract ? route('contracts.update', $contract) : route('contracts.store') }}" class="flex flex-col gap-6" x-data="contractForm" data-line-ids="{{ json_encode($selectedLineIds) }}" data-station-ids="{{ json_encode($selectedStationIds) }}" data-stations="{{ $stations->map->only(['id', 'line_id', 'code', 'name'])->values()->toJson() }}">
                @csrf
                @if($contract) @method('PUT') @endif
                <input type="hidden" name="service_type" value="{{ $serviceType->value }}">

                <section class="grid gap-5 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm md:grid-cols-2 dark:border-gray-700 dark:bg-gray-800">
                    <fieldset class="flex flex-col gap-5 md:col-span-2" data-contract-location-selector>
                        <legend class="text-base font-semibold text-gray-950 dark:text-white">Contract coverage <span aria-hidden="true" class="font-bold !text-red-600">*</span></legend>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Choose the lines covered by this contract, then select the applicable stations within each line.</p>

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($lines as $line)
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition hover:border-emerald-400 hover:bg-emerald-50/50 dark:hover:border-emerald-600 dark:hover:bg-emerald-950/20" :class="lineIds.includes('{{ $line->id }}') ? 'border-emerald-600 bg-emerald-50 ring-1 ring-emerald-600 dark:border-emerald-500 dark:bg-emerald-950/30 dark:ring-emerald-500' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900'">
                                    <input type="checkbox" name="line_ids[]" value="{{ $line->id }}" x-model="lineIds" @change="pruneStations" class="mt-0.5 rounded border-gray-300 text-emerald-700 focus:ring-emerald-600 dark:border-gray-600 dark:bg-gray-800">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ $line->code }}</span>
                                        <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $line->name }}</span>
                                        <span class="mt-2 block text-xs font-medium text-emerald-700 dark:text-emerald-400" x-show="lineIds.includes('{{ $line->id }}')"><span x-text="stationCountForLine('{{ $line->id }}')"></span> selected</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <div class="grid gap-1">
                            <x-input-error :messages="$errors->get('line_ids')"/>
                            <x-input-error :messages="$errors->get('line_ids.*')"/>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-700 dark:bg-gray-900/50">
                            <div class="flex flex-col gap-3 border-b border-gray-200 pb-4 sm:flex-row sm:items-end sm:justify-between dark:border-gray-700">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Stations</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><span x-text="stationIds.length"></span> selected across <span x-text="lineIds.length"></span> lines</p>
                                </div>
                                <div class="w-full sm:max-w-xs">
                                    <label for="station_search" class="sr-only">Search stations</label>
                                    <input id="station_search" type="search" x-model.debounce.150ms="stationSearch" placeholder="Search by station code or name" class="block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder-gray-400">
                                </div>
                            </div>

                            <div x-cloak x-show="lineIds.length === 0" class="py-8 text-center">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Choose one or more lines to see their stations.</p>
                            </div>

                            <div class="flex flex-col gap-4 pt-4" x-show="lineIds.length > 0">
                                @foreach($lines as $line)
                                    @php($lineStations = $stations->where('line_id', $line->id))
                                    <section x-cloak x-show="lineIds.includes('{{ $line->id }}')" class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                                        <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700">
                                            <div>
                                                <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $line->code }} · {{ $line->name }}</h4>
                                                <p class="text-xs text-gray-500 dark:text-gray-400"><span x-text="stationCountForLine('{{ $line->id }}')"></span> of {{ $lineStations->count() }} stations selected</p>
                                            </div>
                                            <div class="flex gap-2">
                                                <button type="button" @click="selectLineStations('{{ $line->id }}')" :disabled="allLineStationsSelected('{{ $line->id }}') || {{ $lineStations->isEmpty() ? 'true' : 'false' }}" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-950">Select all stations</button>
                                                <button type="button" @click="clearLineStations('{{ $line->id }}')" :disabled="stationCountForLine('{{ $line->id }}') === 0" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Clear stations</button>
                                            </div>
                                        </div>

                                        @if($lineStations->isEmpty())
                                            <p class="px-4 py-5 text-sm text-amber-700 dark:text-amber-300">No active stations are available on this line.</p>
                                        @else
                                            <div class="grid gap-2 p-3 sm:grid-cols-2 lg:grid-cols-3">
                                                @foreach($lineStations as $station)
                                                    <label x-show="stationMatchesSearch('{{ $station->id }}')" class="flex cursor-pointer items-start gap-3 rounded-lg border border-transparent p-3 transition hover:border-gray-200 hover:bg-gray-50 dark:hover:border-gray-700 dark:hover:bg-gray-800">
                                                        <input type="checkbox" name="station_ids[]" value="{{ $station->id }}" x-model="stationIds" class="mt-0.5 rounded border-gray-300 text-emerald-700 focus:ring-emerald-600 dark:border-gray-600 dark:bg-gray-800">
                                                        <span class="min-w-0">
                                                            <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $station->name }}</span>
                                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $station->code }}</span>
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            <p x-cloak x-show="visibleStationCountForLine('{{ $line->id }}') === 0" class="px-4 py-5 text-sm text-gray-500 dark:text-gray-400">No stations match your search.</p>
                                        @endif
                                    </section>
                                @endforeach
                            </div>
                        </div>
                        <div class="grid gap-1">
                            <x-input-error :messages="$errors->get('station_ids')"/>
                            <x-input-error :messages="$errors->get('station_ids.*')"/>
                        </div>
                    </fieldset>
                    <div>
                        <x-input-label for="contractor_id" value="Contractor" required/>
                        <select id="contractor_id" name="contractor_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white" required>
                            <option value="">Select contractor</option>
                            @foreach($contractors as $contractorOption)
                                <option value="{{ $contractorOption->id }}" @selected((string)old('contractor_id', $contract?->contractor_id) === (string)$contractorOption->id)>{{ $contractorOption->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('contractor_id')" class="mt-2"/>
                    </div>
                    <div><x-input-label for="contract_number" value="Contract number" required/><x-text-input id="contract_number" name="contract_number" :value="old('contract_number', $contract?->contract_number)" class="mt-1 block w-full" required/><x-input-error :messages="$errors->get('contract_number')" class="mt-2"/></div>
                    <div><x-input-label for="title" value="Contract title" required/><x-text-input id="title" name="title" :value="old('title', $contract?->title)" class="mt-1 block w-full" required/><x-input-error :messages="$errors->get('title')" class="mt-2"/></div>
                    <div><x-input-label for="effective_from" value="Effective from" required/><x-text-input id="effective_from" type="date" name="effective_from" :value="old('effective_from', $contract?->effective_from?->toDateString())" class="mt-1 block w-full" required/><x-input-error :messages="$errors->get('effective_from')" class="mt-2"/></div>
                    <div><x-input-label for="effective_to" value="Effective to" required/><x-text-input id="effective_to" type="date" name="effective_to" :value="old('effective_to', $contract?->effective_to?->toDateString())" class="mt-1 block w-full" required/><x-input-error :messages="$errors->get('effective_to')" class="mt-2"/></div>
                    <div class="md:col-span-2"><x-input-label for="source_document" value="Source contract PDF (optional, 10 MB maximum)"/><input id="source_document" type="file" name="source_document" accept="application/pdf,.pdf" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"><x-input-error :messages="$errors->get('source_document')" class="mt-2"/></div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="border-b border-gray-200 p-6 dark:border-gray-700">
                        <h2 class="font-semibold text-gray-950 dark:text-white">{{ $serviceType->code() }} penalty clauses</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Review the cloned SSC catalog before publishing.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900"><tr><th class="px-4 py-3">S/N <span aria-hidden="true" class="font-bold !text-red-600">*</span></th><th class="px-4 py-3">Description <span aria-hidden="true" class="font-bold !text-red-600">*</span></th><th class="px-4 py-3">Calculation <span aria-hidden="true" class="font-bold !text-red-600">*</span></th><th class="px-4 py-3">Base</th><th class="px-4 py-3">Maximum</th><th class="px-4 py-3">10% cap <span aria-hidden="true" class="font-bold !text-red-600">*</span></th></tr></thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($clauses as $index => $clause)
                                    <tr>
                                        <td class="px-4 py-3"><input type="number" name="clauses[{{ $index }}][serial_number]" value="{{ old("clauses.$index.serial_number", $clause['serial_number']) }}" class="w-20 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><input type="hidden" name="clauses[{{ $index }}][sort_order]" value="{{ $clause['sort_order'] }}"></td>
                                        <td class="min-w-80 px-4 py-3"><textarea name="clauses[{{ $index }}][description]" rows="2" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white">{{ old("clauses.$index.description", $clause['description']) }}</textarea></td>
                                        <td class="px-4 py-3"><select name="clauses[{{ $index }}][calculation_type]" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white">@foreach(\App\Enums\PenaltyCalculationType::cases() as $calculation)<option value="{{ $calculation->value }}" @selected(old("clauses.$index.calculation_type", $clause['calculation_type'] instanceof \App\Enums\PenaltyCalculationType ? $clause['calculation_type']->value : $clause['calculation_type']) === $calculation->value)>{{ $calculation->label() }}</option>@endforeach</select></td>
                                        <td class="px-4 py-3"><input type="number" step="0.01" min="0" name="clauses[{{ $index }}][base_amount]" value="{{ old("clauses.$index.base_amount", $clause['base_amount']) }}" class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></td>
                                        <td class="px-4 py-3"><input type="number" step="0.01" min="0" name="clauses[{{ $index }}][maximum_amount]" value="{{ old("clauses.$index.maximum_amount", $clause['maximum_amount']) }}" class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></td>
                                        <td class="px-4 py-3"><input type="hidden" name="clauses[{{ $index }}][cap_applicable]" value="0"><input type="checkbox" name="clauses[{{ $index }}][cap_applicable]" value="1" @checked(old("clauses.$index.cap_applicable", $clause['cap_applicable'])) class="rounded text-emerald-700"></td>
                                    </tr>
                                    @if($serviceType === \App\Enums\SdmType::Ticketing && (int) $clause['serial_number'] === 10)
                                        @php($cashMismatchAmounts = old("clauses.$index.parameters.cash_mismatch_penalty_amounts", data_get($clause, 'parameters.cash_mismatch_penalty_amounts', \App\Support\CashMismatchPenalty::defaultAmounts())))
                                        <tr class="bg-emerald-50/60 dark:bg-emerald-950/20">
                                            <td colspan="6" class="px-4 py-4">
                                                <fieldset>
                                                    <legend class="font-semibold text-gray-900 dark:text-white">Cash mismatch penalty slabs</legend>
                                                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">These contractual defaults are prefilled and may be edited.</p>
                                                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                                        @foreach($cashMismatchBands as $bandIndex => $band)
                                                            <label class="flex items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-white p-3 text-xs text-gray-700 dark:border-emerald-900 dark:bg-gray-900 dark:text-gray-200">
                                                                <span>{{ $band['label'] }}</span>
                                                                <span class="flex items-center gap-1"><span aria-hidden="true">₹</span><input type="number" min="0" step="0.01" name="clauses[{{ $index }}][parameters][cash_mismatch_penalty_amounts][{{ $bandIndex }}]" value="{{ $cashMismatchAmounts[$bandIndex] ?? '' }}" aria-label="Penalty amount for {{ $band['label'] }}" class="w-24 rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" required></span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                    <x-input-error :messages="$errors->get(\"clauses.$index.parameters.cash_mismatch_penalty_amounts\")" class="mt-2"/>
                                                </fieldset>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="flex justify-end gap-3"><a href="{{ $contract ? route('contracts.show', $contract) : route('contracts.index') }}" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold dark:border-gray-700 dark:text-gray-200">Cancel</a><button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Save draft</button></div>
            </form>
        </div>
    </div>
</x-app-layout>
