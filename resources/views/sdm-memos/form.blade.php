@php
    $selected = old('deficiencies', $memo?->deficiencies->pluck('type')->map(fn ($type) => $type->value)->all() ?? []);
    $serviceType = old('service_type', $memo?->service_type?->value ?? '');
    $late = $memo?->deficiencies->first(fn ($item) => $item->type === \App\Enums\DeficiencyType::LateReporting)?->lateReporting;
    $lateTime = fn (string $field) => substr((string) old('late_reporting.'.$field, $late?->$field), 0, 5);
    $overchargeAmount = old('overcharge_amount', $memo?->deficiencies->first(fn ($item) => $item->type === \App\Enums\DeficiencyType::Overcharging)?->overcharge_amount);
    $shortfallCount = old('shortfall_count', $memo?->deficiencies->first(fn ($item) => $item->type === \App\Enums\DeficiencyType::ShortDeployment)?->shortfall_count);
    $incidentTime = substr((string) old('incident_time', $memo?->incident_time ?? now('Asia/Kolkata')->format('H:i')), 0, 5);
    $cashAmountType = old('cash_check.amount_type', $memo?->cashCheck?->amount_type);
    $inputClass = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100';
    $isDraft = ! $memo || $memo->status === \App\Enums\MemoStatus::Draft;
    $showUrgentOption = ! request()->user()->isStationController();
    $requiresForwarding = $forwardingRole !== null && $isDraft;
    $forwardingField = $forwardingRole === \App\Enums\UserRole::StationManager
        ? 'station_manager_employee_number'
        : 'line_manager_employee_number';
    $forwardingLabel = $forwardingRole === \App\Enums\UserRole::StationManager
        ? 'SM employee number'
        : 'Manager/Assistant Manager (O)-Line employee number';
    $confirmationPreview = [];
    $confirmationFields = [
        ...($issuanceStations->isEmpty() ? [['label' => 'Line', 'value' => collect([$station?->metroLine?->code, $station?->metroLine?->name])->filter()->join(' · ')]] : []),
        $issuanceStations->isNotEmpty()
            ? ['label' => 'Inspected station', 'name' => 'station_id']
            : ['label' => 'Station', 'value' => collect([$station?->code, $station?->name])->filter()->join(' · ')],
        ['label' => 'SDM type', 'name' => 'service_type'],
        ['label' => 'Deficiencies', 'name' => 'deficiencies[]'],
        ['label' => 'Number of staff shortfalls', 'name' => 'shortfall_count'],
        ['label' => 'Incident date', 'name' => 'incident_date'],
        ['label' => 'Incident time', 'name' => 'incident_time'],
        ...($showUrgentOption ? [['label' => 'Priority', 'name' => 'is_urgent', 'emptyValue' => 'Normal']] : []),
        ...($requiresForwarding ? [['label' => 'Forward to', 'name' => $forwardingField]] : []),
    ];
    $pageTitle = $memo ? ($isDraft ? 'Edit SDM draft' : 'Edit submitted SDM') : 'Create Service Deficiency Memo';
    $memoLocation = collect([$station?->metroLine?->code, $station?->name])->filter()->join(' · ');
    $checkingOfficialDefaults = [
        'checking_official_name' => request()->user()->name,
        'checking_official_designation' => request()->user()->designation,
        'checking_official_employee_number' => request()->user()->employee_number,
    ];
@endphp
<x-app-layout :title="$pageTitle">
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $pageTitle }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400"><span aria-hidden="true" class="font-bold !text-red-600 dark:!text-red-400">*</span> Mandatory when the memo is submitted.</p>
        </div>
    </x-slot>
    <div class="py-10">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <form method="POST" enctype="multipart/form-data" action="{{ $memo ? route('sdm-memos.update', $memo) : route('sdm-memos.store') }}" class="flex flex-col gap-6" x-data="memoForm" @submit="requestSdmConfirmation($event)" data-selected-deficiencies='@json($selected)' data-service-type="{{ $serviceType }}" data-deficiency-options='@json($deficiencyOptionsByType)' data-booked-shift-from="{{ $lateTime('booked_shift_from') }}" data-booked-shift-to="{{ $lateTime('booked_shift_to') }}" data-actual-reporting-time="{{ $lateTime('actual_reporting_time') }}" data-confirmation-show-action="false" data-confirmation-preview="{{ json_encode($confirmationPreview) }}" data-confirmation-fields="{{ json_encode($confirmationFields) }}"
                data-contractor-id="{{ old('contractor_id', $memo?->contractor_id) }}"
                data-contractor-ids-by-service-type="{{ $contractorIdsByServiceType?->toJson() }}"
                data-staff-lookup-url="{{ $memo ? route('sdm-memos.staff-lookup-for-edit', $memo) : route('sdm-memos.staff-lookup') }}"
                data-staff-identifier="{{ old('employee_number', $memo?->employee_number) }}"
                data-staff-name="{{ old('staff_name', $memo?->staff_name) }}"
                data-station-id="{{ old('station_id', $memo?->station_id ?? $station?->id) }}"
                data-contractors="{{ $contractors->map->only(['id', 'name'])->values()->toJson() }}"
                data-contractor-contracts="{{ $contractorContracts->toJson() }}"
                data-incident-date="{{ old('incident_date', $memo?->incident_date?->toDateString() ?? now('Asia/Kolkata')->toDateString()) }}">
                @csrf
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex items-baseline gap-2">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Memo details</h3>
                            @if(! $memo && $issuanceStations->isEmpty())
                                <p data-memo-location class="text-lg font-semibold text-gray-900 dark:text-white">[{{ $memoLocation }}]</p>
                            @endif
                        </div>
                        @if($showUrgentOption)
                            <div class="w-full sm:w-auto sm:max-w-sm">
                                <input type="hidden" name="is_urgent" value="0" data-preview-ignore>
                                <label for="is_urgent" class="flex cursor-pointer items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100">
                                    <input id="is_urgent" type="checkbox" name="is_urgent" value="1" data-preview-value="Urgent" @checked(old('is_urgent', $memo?->is_urgent)) class="mt-0.5 rounded border-red-300 text-red-600 focus:ring-red-500 dark:border-red-700 dark:bg-gray-900">
                                    <span><span class="block font-semibold">Mark as urgent</span><span class="mt-0.5 block text-xs text-red-700 dark:text-red-300">Highlights this SDM and alerts the assigned user at login.</span></span>
                                </label>
                            </div>
                        @endif
                    </div>
                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        @if($issuanceStations->isNotEmpty())
                            <div>
                                <x-input-label for="station_id" value="Inspected station" required/>
                                <select id="station_id" name="station_id" x-model="stationId" @change="syncContractor()" class="{{ $inputClass }}" required>
                                    <option value="">Select inspected station</option>
                                    @foreach($issuanceStations as $issuanceStation)
                                        <option value="{{ $issuanceStation->id }}">{{ $issuanceStation->code }} · {{ $issuanceStation->name }} ({{ $issuanceStation->metroLine?->name }})</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('station_id')" class="mt-2"/>
                            </div>
                        @endif
                        <div>
                            <x-input-label for="service_type" value="SDM type" required/>
                            <select id="service_type" name="service_type" x-model="serviceType" @change="syncSelectedDeficiencies(); syncContractor({ serviceTypeChanged: true })" class="{{ $inputClass }}" required>
                                <option value="">Select type</option>
                                @foreach($sdmTypes as $type)
                                    <option value="{{ $type->value }}">{{ $type->code() }} · {{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        {{-- @if($issuanceStations->isNotEmpty())
                            <div class="md:col-span-2">
                                <x-input-label for="station_id" value="Issuing station" required/>
                                <select id="station_id" name="station_id" class="{{ $inputClass }}" required>
                                    <option value="">Select assigned station</option>
                                    @foreach($issuanceStations as $issuanceStation)
                                        <option value="{{ $issuanceStation->id }}" @selected((string) old('station_id', $memo?->station_id) === (string) $issuanceStation->id)>{{ $issuanceStation->code }} · {{ $issuanceStation->name }} ({{ $issuanceStation->metroLine?->name }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <div>
                                <x-input-label value="Line"/>
                                <p class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">{{ collect([$station->metroLine?->code, $station->metroLine?->name])->filter()->join(', ') ?: 'Not assigned' }}</p>
                            </div>
                            <div>
                                <x-input-label value="Station"/><p class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">{{ $station->code }} · {{ $station->name }}</p>
                            </div>
                        @endif --}}
                        {{-- <div>
                            <x-input-label value="Line" required/>

                            <p class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                                {{ collect([
                                    $station?->metroLine?->code,
                                    $station?->metroLine?->name,
                                ])->filter()->join(' · ') ?: 'Not assigned' }}
                            </p>
                        </div> --}}

                        {{-- <div>
                            <x-input-label value="Station" required/>

                            <p class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                                {{ collect([
                                    $station?->code,
                                    $station?->name,
                                ])->filter()->join(' · ') ?: 'Not assigned' }}
                            </p>

                            <input
                                type="hidden"
                                name="station_id"
                                value="{{ $station->id }}"
                            /> --}}

                            {{-- <x-input-error :messages="$errors->get('station_id')" class="mt-2"/>
                        </div> --}}

                        <div>
                            <x-input-label for="contractor_id" value="Contractor" required/>
                            <select id="contractor_id" name="contractor_id" x-model="contractorId" @change="contractorManuallySelected = true" class="{{ $inputClass }}">
                                <option value="" x-text="!serviceType ? 'Select an SDM type first' : (availableContractors.length ? 'Select contractor' : '{{ $memo ? 'No matching published contract' : 'No contractors for this SDM type' }}')">Select an SDM type first</option>
                                <template x-for="contractor in availableContractors" :key="contractor.id">
                                    <option :value="String(contractor.id)" :selected="contractorId === String(contractor.id)" x-text="contractor.name"></option>
                                </template>
                            </select>
                        </div>
                        @if($issuanceStations->isEmpty())
                        <div>
                            <x-input-label value="Line" required/>
                            <p class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">{{ collect([$station->metroLine?->code, $station->metroLine?->name])->filter()->join(' · ') ?: 'Not assigned' }}</p>
                        </div>
                        <div>
                            <x-input-label value="Station" required/>
                            <p class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">{{ collect([$station->code, $station->name])->filter()->join(' · ') ?: 'Not assigned' }}</p>
                        </div>
                        @endif
                        <div>
                            <x-input-label for="incident_date" value="Date of incident" required/>
                            <x-text-input id="incident_date" type="date" name="incident_date" x-model="incidentDate" @change="syncContractor()" max="{{ now('Asia/Kolkata')->toDateString() }}" class="mt-1 block w-full" :value="old('incident_date', $memo?->incident_date?->toDateString() ?? now('Asia/Kolkata')->toDateString())"/>
                        </div>
                        <div>
                            <x-input-label for="incident_time" value="Time of incident (HH:MM, IST)" required/>
                            <x-text-input id="incident_time" type="time" name="incident_time" step="60" class="mt-1 block w-full" :value="$incidentTime" required/>
                        </div>
                        <div>
                            <x-input-label for="employee_number" value="Outsourced Staff- Employee number" required/>
                            <x-text-input id="employee_number" name="employee_number" class="mt-1 block w-full" x-model="staffIdentifier" @input="resetStaffLookup()" @input.debounce.400ms="lookupStaff()" maxlength="100" autocomplete="off" aria-describedby="staff_identifier_help staff_lookup_status" :value="old('employee_number',$memo?->employee_number)"/>
                            <p id="staff_identifier_help" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Enter EMP ID or Aadhaar number to fetch the employee name.</p>
                            <p id="staff_lookup_status" role="status" aria-live="polite" x-text="staffLookupMessage" class="mt-1 text-xs text-gray-600 dark:text-gray-300"></p>
                            <x-input-error :messages="$errors->get('employee_number')" class="mt-1"/>
                        </div>
                        <div>
                            <x-input-label for="staff_name" value="Outsourced Staff name" required/>
                            <x-text-input id="staff_name" name="staff_name" class="mt-1 block w-full read-only:bg-gray-50 dark:read-only:bg-gray-900" x-model="staffName" x-bind:readonly="staffNameReadOnly" maxlength="255" :value="old('staff_name',$memo?->staff_name)"/>
                            <x-input-error :messages="$errors->get('staff_name')" class="mt-1"/>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Deficiencies</h3>
                    <div class="relative mt-5" @click.outside="deficiencyOpen=false" @keydown.escape.prevent.stop="deficiencyOpen=false; $refs.deficienciesControl.focus()">
                        <x-input-label for="deficiencies-control" value="Select deficiencies" required/>
                        <div class="mt-1 flex min-h-[42px] flex-wrap items-center gap-2 rounded-md border border-gray-300 bg-white p-2 dark:border-gray-700 dark:bg-gray-900">
                            <template x-for="type in selected" :key="type">
                                <span class="inline-flex max-w-full items-start gap-1 rounded bg-emerald-50 py-1 pl-2 pr-1 text-xs font-medium text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
                                    <span class="min-w-0 break-words py-1" x-text="labelFor(type)"></span>
                                    <button type="button" @click.stop="removeDeficiency(type); $nextTick(() => $refs.deficienciesControl.focus())" :aria-label="`Remove deficiency: ${labelFor(type)}`" class="flex h-6 w-6 shrink-0 items-center justify-center rounded hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:hover:bg-emerald-800">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15" stroke-linecap="round"/></svg>
                                    </button>
                                </span>
                            </template>
                            <button id="deficiencies-control" x-ref="deficienciesControl" type="button" @click="deficiencyOpen=!deficiencyOpen" :disabled="! serviceType" :aria-expanded="deficiencyOpen.toString()" aria-controls="deficiencies-options" class="flex min-h-8 flex-1 items-center justify-between gap-3 rounded px-1 text-left text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:cursor-not-allowed disabled:text-gray-400 dark:text-gray-300">
                                <span x-text="! serviceType ? 'Select an SDM type first' : (selected.length ? 'Add deficiencies' : 'Select deficiencies')"></span>
                                <span class="shrink-0" aria-hidden="true">▾</span>
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Select as many deficiencies as needed. Use × to remove a selection.</p>
                        <div id="deficiencies-options" x-cloak x-show="deficiencyOpen && serviceType" class="absolute z-20 mt-1 max-h-80 w-full overflow-y-auto rounded-md border border-gray-200 bg-white p-2 shadow-xl dark:border-gray-700 dark:bg-gray-900">
                            <template x-for="option in availableDeficiencies" :key="option.value">
                                <label class="flex cursor-pointer items-start gap-3 rounded px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <input type="checkbox" name="deficiencies[]" :value="option.value" :data-preview-value="option.label" x-model="selected" class="mt-0.5 rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
                                    <span class="text-sm text-gray-700 dark:text-gray-200"><span x-show="option.serial_number" class="font-semibold" x-text="`${option.serial_number}. `"></span><span x-text="option.label"></span></span>
                                </label>
                            </template>
                        </div>
                    </div>
                    <div x-cloak x-show="has('late_reporting')" class="mt-6 rounded-xl bg-gray-50 p-4 dark:bg-gray-900">
                        <div class="grid gap-4 md:grid-cols-3">
                            <div><x-input-label for="booked_shift_from" value="Booked shift from" required/>
                                <input id="booked_shift_from" type="time" name="late_reporting[booked_shift_from]" value="{{ $lateTime('booked_shift_from') }}" x-model="bookedShiftFrom" class="{{ $inputClass }}">
                            </div>
                            <div><x-input-label for="booked_shift_to" value="Booked shift to" required/>
                                <input id="booked_shift_to" type="time" name="late_reporting[booked_shift_to]" value="{{ $lateTime('booked_shift_to') }}" x-model="bookedShiftTo" class="{{ $inputClass }}">
                            </div>
                            <div><x-input-label for="actual_reporting_time" value="Actual reporting time" required/>
                                <input id="actual_reporting_time" type="time" name="late_reporting[actual_reporting_time]" value="{{ $lateTime('actual_reporting_time') }}" x-model="actualReportingTime" class="{{ $inputClass }}">
                                <p x-cloak x-show="delayLabel" class="mt-2 text-sm font-semibold text-amber-700 dark:text-amber-300">Delay: <span x-text="delayLabel"></span></p>
                            </div>
                        </div>
                    </div>
                    <div x-cloak x-show="has('overcharging')" class="mt-6 flex flex-col gap-2 rounded-xl bg-gray-50 p-4 dark:bg-gray-900">
                        <x-input-label for="overcharge_amount" value="Overcharged amount (₹)"/>
                        <input id="overcharge_amount" type="text" name="overcharge_amount" inputmode="decimal" maxlength="13" value="{{ $overchargeAmount }}" :disabled="!has('overcharging')" aria-describedby="overcharge_amount_error" class="{{ $inputClass }}">
                        <x-input-error id="overcharge_amount_error" :messages="$errors->get('overcharge_amount')"/>
                    </div>
                    <div x-cloak x-show="has('short_deployment')" class="mt-6 flex flex-col gap-2 rounded-xl bg-gray-50 p-4 dark:bg-gray-900">
                        <x-input-label for="shortfall_count" value="Number of staff shortfalls" :required="$isDraft"/>
                        <input id="shortfall_count" name="shortfall_count" type="number" min="1" max="4294967295" step="1"
                            value="{{ is_scalar($shortfallCount) ? $shortfallCount : '' }}" :disabled="!has('short_deployment')"
                            @if($isDraft) :required="has('short_deployment')" @endif
                            @error('shortfall_count') aria-invalid="true" aria-describedby="shortfall_count_error" @enderror
                            class="{{ $inputClass }}">
                        <x-input-error id="shortfall_count_error" :messages="$errors->get('shortfall_count')"/>
                    </div>
                    <div class="mt-5">
                        <x-input-label for="station_record_brief_remarks">
                            Remarks <span @if($isDraft) x-text="has('{{ \App\Enums\DeficiencyType::Other->value }}') ? '(required for Other Discrepancy)' : '(optional)'" @endif>(optional)</span>
                        </x-input-label>
                        <textarea id="station_record_brief_remarks" name="station_record_brief_remarks" rows="4" @if($isDraft) :required="has('{{ \App\Enums\DeficiencyType::Other->value }}')" @endif @error('station_record_brief_remarks') aria-invalid="true" aria-describedby="station_record_brief_remarks_error" @enderror class="{{ $inputClass }}">{{ old('station_record_brief_remarks', $memo?->station_record_brief_remarks) }}</textarea>
                        <x-input-error id="station_record_brief_remarks_error" :messages="$errors->get('station_record_brief_remarks')"/>
                    </div>

                    <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                        <label for="outsourced_staff_acknowledged" class="flex cursor-pointer items-start gap-3">
                            <input
                                id="outsourced_staff_acknowledged"
                                name="outsourced_staff_acknowledged"
                                type="checkbox"
                                value="1"
                                required
                                aria-required="true"
                                @checked(old('outsourced_staff_acknowledged'))
                                class="mt-0.5 rounded border-gray-300 text-emerald-700 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800"
                            >
                            <span class="text-sm font-medium text-gray-800 dark:text-gray-100">
                                I have received acknowledgement from outsourced staff/supervisor.
                                <span class="text-red-600" aria-hidden="true">*</span>
                            </span>
                        </label>
                    </div>
                </section>

                <section x-cloak x-show="serviceType === '{{ \App\Enums\SdmType::Ticketing->value }}' && has('cash_check')" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">TOM / CCC inspection and cash checking</h3>
                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        @foreach(['tom_ccc_number'=>'TOM/CCC number','end_of_shift_receipt_number'=>'End-of-shift receipt number'] as $name=>$label)
                            <div>
                                <x-input-label :for="$name" :value="$label"/>
                                <x-text-input :id="$name" :name="'cash_check['.$name.']'" class="mt-1 block w-full" :value="old('cash_check.'.$name,$memo?->cashCheck?->$name)"/>
                            </div>
                        @endforeach
                        <div>
                            <x-input-label for="cash_check_date" value="Date"/>
                            <x-text-input id="cash_check_date" type="date" name="cash_check[check_date]" max="{{ now()->toDateString() }}" class="mt-1 block w-full" :value="old('cash_check.check_date', $memo?->cashCheck?->checked_at?->toDateString() ?? now()->toDateString())"/>
                        </div>
                        <div>
                            <x-input-label for="cash_check_time" value="Time of check (24 hour) in hh:mm format"/>
                            <input id="cash_check_time" type="time" name="cash_check[check_time]" value="{{ old('cash_check.check_time', $memo?->cashCheck?->checked_at?->format('H:i') ?? now('Asia/Kolkata')->format('H:i')) }}" class="{{ $inputClass }}">
                        </div>
                        <div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50 md:col-span-2">
                            <fieldset>
                                <legend class="sr-only">Cash mismatch type</legend>
                                <div class="flex flex-wrap gap-3">
                                    @foreach(['excess' => 'Excess amount', 'short' => 'Short amount'] as $value => $label)
                                        <label for="cash_check_{{ $value }}" class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:border-emerald-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-emerald-500">
                                            <input id="cash_check_{{ $value }}" type="radio" name="cash_check[amount_type]" value="{{ $value }}" @checked($cashAmountType === $value) class="border-gray-300 text-emerald-700 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900">
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="flex flex-col gap-1">
                                    <x-input-label for="cash_check_amount" value="Mismatch amount"/>
                                    <x-text-input id="cash_check_amount" type="number" min="0" step="0.01" name="cash_check[amount]" class="block w-full" :value="old('cash_check.amount', $memo?->cashCheck?->amount)"/>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <x-input-label for="cash_check_hidden_cash_amount" value="Hidden cash amount"/>
                                    <x-text-input id="cash_check_hidden_cash_amount" type="number" min="0" step="0.01" name="cash_check[hidden_cash_amount]" class="block w-full" :value="old('cash_check.hidden_cash_amount', $memo?->cashCheck?->hidden_cash_amount)"/>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Select Excess or Short when entering a mismatch amount. Hidden cash is recorded separately.</p>
                        </div>
                        @foreach(['checking_official_name'=>'Checking official name','checking_official_designation'=>'Checking official designation','checking_official_employee_number'=>'Checking official employee number'] as $name=>$label)
                            <div>
                                <x-input-label :for="$name" :value="$label"/>
                                <x-text-input :id="$name" :name="'cash_check['.$name.']'" class="mt-1 block w-full" :value="old('cash_check.'.$name, $memo?->cashCheck?->$name ?? $checkingOfficialDefaults[$name])"/>
                            </div>
                        @endforeach
                        <div class="md:col-span-2">
                            <x-input-label value="Cash-check remarks (if any)"/>
                            <textarea name="cash_check[remarks]" rows="3" class="{{ $inputClass }}">{{ old('cash_check.remarks',$memo?->cashCheck?->remarks) }}</textarea>
                        </div>
                    </div>
                </section>

                <section x-cloak x-show="has('{{ \App\Enums\DeficiencyType::PublicComplaint->value }}')" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Complaint and supporting documents</h3>
                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <div>
                            <x-input-label value="Public complaint number"/>
                            <x-text-input name="public_complaint[number]" class="mt-1 block w-full" :value="old('public_complaint.number',$memo?->publicComplaint?->complaint_serial_number)"/>
                        </div>
                        <div>
                            <x-input-label value="Public complaint station"/>
                            <select name="public_complaint[station_id]" class="{{ $inputClass }}">
                                <option value="">Select station</option>
                                @foreach($stations as $complaintStation)
                                    <option value="{{ $complaintStation->id }}" @selected((string)old('public_complaint.station_id',$memo?->publicComplaint?->station_id)===(string)$complaintStation->id)>{{ $complaintStation->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label value="Attachments (PDF/JPG/PNG, maximum 5 files, 10 MB each)"/>
                            <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="{{ $inputClass }} p-2">
                        </div>
                    </div>
                    @if($memo?->attachments->isNotEmpty())
                        <div class="mt-4 flex flex-col gap-2">
                            @foreach($memo->attachments as $attachment)
                                <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-gray-900">
                                    <a class="text-emerald-700 hover:underline" href="{{ route('sdm-memos.attachments.download',[$memo,$attachment]) }}">{{ $attachment->original_name }}</a>
                                    <button type="submit" form="delete-attachment-{{ $attachment->id }}" class="text-rose-700 hover:underline">Remove</button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                @if($requiresForwarding)
                    <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 shadow-sm dark:border-emerald-900 dark:bg-emerald-950/40">
                        <h3 class="text-lg font-semibold text-emerald-950 dark:text-emerald-100">Forwarding on submission</h3>
                        @if($forwardingRole === \App\Enums\UserRole::StationManager)
                            <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-200">Enter the employee number of the SM who should receive this SDM immediately after submission.</p>
                        @else
                            <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-200">Select the Manager/Assistant Manager (O)-Line who should receive this SDM immediately after submission.</p>
                        @endif
                        <div class="mt-5">
                            <x-employee-autocomplete
                                :name="$forwardingField"
                                :label="$forwardingLabel"
                                :candidates="$forwardingCandidates"
                                :value="old($forwardingField)"
                            />
                        </div>
                    </section>
                @endif

                <div class="flex flex-wrap justify-end gap-3">
                    <a href="{{ route('sdm-memos.index') }}" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-200">Cancel</a>
                    <button type="submit" @if($isDraft) formnovalidate @endif class="rounded-lg border border-emerald-700 px-5 py-2.5 text-sm font-semibold text-emerald-700">{{ $isDraft ? 'Save draft' : 'Save changes' }}</button>
                    @if($isDraft)
                        <button type="submit" formaction="{{ $memo ? route('sdm-memos.submit', $memo) : route('sdm-memos.store-and-submit') }}" data-sdm-confirm data-confirmation-action="Submit SDM" class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">Submit memo</button>
                    @endif
                </div>

                <x-sdm-confirmation-preview />
            
            </form>
            @if($memo)
                @foreach($memo->attachments as $attachment)
                    <form id="delete-attachment-{{ $attachment->id }}" method="POST" action="{{ route('sdm-memos.attachments.destroy',[$memo,$attachment]) }}">
                        @csrf 
                        @method('DELETE')
                    </form>
                @endforeach
                @can('delete', $memo)
                    <form method="POST" action="{{ route('sdm-memos.destroy', $memo) }}" class="mt-4 flex justify-end">
                        @csrf 
                        @method('DELETE')
                        <button type="submit" class="rounded-lg border border-rose-300 bg-rose-50 px-5 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-100 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-950 dark:focus:ring-offset-gray-900">Delete draft</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>
</x-app-layout>
