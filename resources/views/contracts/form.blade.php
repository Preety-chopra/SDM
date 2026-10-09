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
            <form method="POST" enctype="multipart/form-data" action="{{ $contract ? route('contracts.update', $contract) : route('contracts.store') }}" class="flex flex-col gap-6" x-data="contractForm" data-line-ids="{{ json_encode($selectedLineIds) }}" data-station-ids="{{ json_encode($selectedStationIds) }}" data-stations="{{ $stations->map->only(['id', 'line_id'])->values()->toJson() }}">
                @csrf
                @if($contract) @method('PUT') @endif
                <input type="hidden" name="service_type" value="{{ $serviceType->value }}">

                <section class="grid gap-5 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm md:grid-cols-2 dark:border-gray-700 dark:bg-gray-800">
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
                    <div>
                        <x-input-label for="line_ids" value="Lines" required/>
                        <select id="line_ids" name="line_ids[]" x-model="lineIds" @change="pruneStations" multiple size="5" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white" required>
                            @foreach($lines as $line)
                                <option value="{{ $line->id }}" @selected(in_array((string) $line->id, $selectedLineIds, true))>{{ $line->code }} · {{ $line->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Hold Ctrl (Windows) or Command (Mac) to select multiple lines.</p>
                        <x-input-error :messages="$errors->get('line_ids')" class="mt-2"/>
                        <x-input-error :messages="$errors->get('line_ids.*')" class="mt-2"/>
                    </div>
                    <div>
                        <x-input-label for="station_ids" value="Stations" required/>
                        <select id="station_ids" name="station_ids[]" x-model="stationIds" multiple size="8" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white" required>
                            @foreach($stations as $station)
                                <option x-cloak x-show="lineIds.includes('{{ $station->line_id }}')" :disabled="! lineIds.includes('{{ $station->line_id }}')" value="{{ $station->id }}" @selected(in_array((string) $station->id, $selectedStationIds, true))>{{ $station->metroLine->code }} · {{ $station->code }} · {{ $station->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Stations are limited to the selected lines. Hold Ctrl or Command to select several.</p>
                        <x-input-error :messages="$errors->get('station_ids')" class="mt-2"/>
                        <x-input-error :messages="$errors->get('station_ids.*')" class="mt-2"/>
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
