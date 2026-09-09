@php
    $selected = old('deficiencies', $memo?->deficiencies->pluck('type')->map(fn ($type) => $type->value)->all() ?? []);
    $serviceType = old('service_type', $memo?->service_type?->value ?? '');
    $labels = collect($deficiencyTypes)->mapWithKeys(fn ($type) => [$type->value => $type->label()]);
    $late = $memo?->deficiencies->first(fn ($item) => $item->type === \App\Enums\DeficiencyType::LateReporting)?->lateReporting;
    $lateTime = fn (string $field) => substr((string) old('late_reporting.'.$field, $late?->$field), 0, 5);
    $incidentTime = substr((string) old('incident_time', $memo?->incident_time ?? now('Asia/Kolkata')->format('H:i')), 0, 5);
    $inputClass = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100';
    $isDraft = ! $memo || $memo->status === \App\Enums\MemoStatus::Draft;
    $pageTitle = $memo ? ($isDraft ? 'Edit SDM draft' : 'Edit submitted SDM') : 'Create Service Deficiency Memo';
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
            @if(session('status'))
                <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
                    <p class="font-semibold">Please correct the following before continuing:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form method="POST" enctype="multipart/form-data" action="{{ $memo ? route('sdm-memos.update', $memo) : route('sdm-memos.store') }}" class="flex flex-col gap-6" x-data="memoForm" data-selected-deficiencies='@json($selected)' data-service-type="{{ $serviceType }}" data-deficiency-labels='@json($labels)' data-booked-shift-from="{{ $lateTime('booked_shift_from') }}" data-booked-shift-to="{{ $lateTime('booked_shift_to') }}" data-actual-reporting-time="{{ $lateTime('actual_reporting_time') }}">
                @csrf
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Memo details</h3>
                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <div>
                            <x-input-label for="serial_number" value="Serial number" required/>
                            <x-text-input id="serial_number" name="serial_number" class="mt-1 block w-full" :value="old('serial_number', $memo?->serial_number)" required/>
                            <x-input-error :messages="$errors->get('serial_number')" class="mt-2"/>
                        </div>
                        <div>
                            <x-input-label for="service_type" value="SDM type" required/>
                            <select id="service_type" name="service_type" x-model="serviceType" class="{{ $inputClass }}" required>
                                <option value="">Select type</option>
                                @foreach($sdmTypes as $type)
                                    <option value="{{ $type->value }}">{{ $type->code() }} · {{ $type->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('service_type')" class="mt-2"/>
                        </div>
                        @if($issuanceStations->isNotEmpty())
                            <div class="md:col-span-2">
                                <x-input-label for="station_id" value="Issuing station" required/>
                                <select id="station_id" name="station_id" class="{{ $inputClass }}" required>
                                    <option value="">Select assigned station</option>
                                    @foreach($issuanceStations as $issuanceStation)
                                        <option value="{{ $issuanceStation->id }}" @selected((string) old('station_id', $memo?->station_id) === (string) $issuanceStation->id)>{{ $issuanceStation->code }} · {{ $issuanceStation->name }} ({{ $issuanceStation->metroLine?->name }})</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('station_id')" class="mt-2"/>
                            </div>
                        @else
                            <div>
                                <x-input-label value="Line"/>
                                <p class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">{{ collect([$station->metroLine?->code, $station->metroLine?->name])->filter()->join(', ') ?: 'Not assigned' }}</p>
                            </div>
                            <div>
                                <x-input-label value="Station"/><p class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">{{ $station->code }} · {{ $station->name }}</p>
                            </div>
                        @endif
                        <div>
                            <x-input-label for="contractor_id" value="Contractor" required/>
                            <select id="contractor_id" name="contractor_id" class="{{ $inputClass }}">
                                <option value="">Select contractor</option>
                                @foreach($contractors as $contractor)
                                    <option value="{{ $contractor->id }}" @selected((string)old('contractor_id',$memo?->contractor_id)===(string)$contractor->id)>{{ $contractor->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('contractor_id')" class="mt-2"/>
                        </div>
                        <div>
                            <x-input-label for="incident_date" value="Date of incident" required/>
                            <x-text-input id="incident_date" type="date" name="incident_date" max="{{ now('Asia/Kolkata')->toDateString() }}" class="mt-1 block w-full" :value="old('incident_date', $memo?->incident_date?->toDateString() ?? now('Asia/Kolkata')->toDateString())"/>
                            <x-input-error :messages="$errors->get('incident_date')" class="mt-2"/>
                        </div>
                        <div>
                            <x-input-label for="incident_time" value="Time of incident (HH:MM, IST)" required/>
                            <x-text-input id="incident_time" type="time" name="incident_time" step="60" class="mt-1 block w-full" :value="$incidentTime" required/>
                            <x-input-error :messages="$errors->get('incident_time')" class="mt-2"/>
                        </div>
                        <div>
                            <x-input-label for="employee_number" value="Employee number" required/>
                            <x-text-input id="employee_number" name="employee_number" class="mt-1 block w-full" :value="old('employee_number',$memo?->employee_number)"/>
                            <x-input-error :messages="$errors->get('employee_number')" class="mt-2"/>
                        </div>
                        <div>
                            <x-input-label for="staff_name" value="Staff name" required/>
                            <x-text-input id="staff_name" name="staff_name" class="mt-1 block w-full" :value="old('staff_name',$memo?->staff_name)"/>
                            <x-input-error :messages="$errors->get('staff_name')" class="mt-2"/>
                        </div>
                        @if(auth()->user()->hasRole(\App\Enums\UserRole::Station->value) && $isDraft)
                            <div>
                                <x-input-label for="station_manager_employee_number" value="Forward to Station Manager (employee number)" required/>
                                <x-text-input id="station_manager_employee_number" name="station_manager_employee_number" class="mt-1 block w-full" :value="old('station_manager_employee_number')"/>
                                <x-input-error :messages="$errors->get('station_manager_employee_number')" class="mt-2"/>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"><h3 class="text-lg font-semibold text-gray-900 dark:text-white">Deficiencies</h3><div class="relative mt-5"><x-input-label for="deficiencies-control" value="Select deficiencies" required/><button id="deficiencies-control" type="button" @click="deficiencyOpen=!deficiencyOpen" class="mt-1 flex min-h-[42px] w-full items-center justify-between gap-3 rounded-md border border-gray-300 bg-white px-3 py-2 text-left text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"><span class="flex min-w-0 flex-1 flex-wrap gap-1.5"><span x-show="selected.length === 0" class="text-gray-500 dark:text-gray-400">Select deficiencies</span><template x-for="type in selected" :key="type"><span class="max-w-full rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200" x-text="labelFor(type)"></span></template></span><span class="shrink-0" aria-hidden="true">▾</span></button><div x-cloak x-show="deficiencyOpen" @click.outside="deficiencyOpen=false" class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white p-2 shadow-xl dark:border-gray-700 dark:bg-gray-900">@foreach($deficiencyTypes as $type)<label class="flex cursor-pointer items-start gap-3 rounded px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800" x-show="'{{ $type->value }}' !== 'cash_check' || serviceType === '{{ \App\Enums\SdmType::Ticketing->value }}'"><input type="checkbox" name="deficiencies[]" value="{{ $type->value }}" x-model="selected" class="mt-0.5 rounded border-gray-300 text-emerald-700 focus:ring-emerald-500"><span class="text-sm text-gray-700 dark:text-gray-200">{{ $type->label() }}</span></label>@endforeach</div></div><x-input-error :messages="$errors->get('deficiencies')" class="mt-2"/>
                    <div x-cloak x-show="has('late_reporting')" class="mt-6 rounded-xl bg-gray-50 p-4 dark:bg-gray-900">
                        <div class="grid gap-4 md:grid-cols-3">
                            <div><x-input-label for="booked_shift_from" value="Booked shift from" required/>
                                <input id="booked_shift_from" type="time" name="late_reporting[booked_shift_from]" value="{{ $lateTime('booked_shift_from') }}" x-model="bookedShiftFrom" class="{{ $inputClass }}">
                                <x-input-error :messages="$errors->get('late_reporting.booked_shift_from')" class="mt-2"/>
                            </div>
                            <div><x-input-label for="booked_shift_to" value="Booked shift to" required/>
                                <input id="booked_shift_to" type="time" name="late_reporting[booked_shift_to]" value="{{ $lateTime('booked_shift_to') }}" x-model="bookedShiftTo" class="{{ $inputClass }}">
                                <x-input-error :messages="$errors->get('late_reporting.booked_shift_to')" class="mt-2"/>
                            </div>
                            <div><x-input-label for="actual_reporting_time" value="Actual reporting time" required/>
                                <input id="actual_reporting_time" type="time" name="late_reporting[actual_reporting_time]" value="{{ $lateTime('actual_reporting_time') }}" x-model="actualReportingTime" class="{{ $inputClass }}">
                                <x-input-error :messages="$errors->get('late_reporting.actual_reporting_time')" class="mt-2"/>
                                <p x-cloak x-show="delayLabel" class="mt-2 text-sm font-semibold text-amber-700 dark:text-amber-300">Delay: <span x-text="delayLabel"></span></p>
                            </div>
                        </div>
                    </div>
                    @foreach([\App\Enums\DeficiencyType::DressCode,\App\Enums\DeficiencyType::Misbehavior,\App\Enums\DeficiencyType::Other] as $type)<div x-cloak x-show="has('{{ $type->value }}')" class="mt-5"><x-input-label :value="$type->label().' remarks'" required/><textarea name="deficiency_remarks[{{ $type->value }}]" rows="3" class="{{ $inputClass }}">{{ old('deficiency_remarks.'.$type->value,$memo?->deficiencies->first(fn($item)=>$item->type===$type)?->remarks) }}</textarea><x-input-error :messages="$errors->get('deficiency_remarks.'.$type->value)" class="mt-2"/></div>@endforeach
                </section>

                <section x-cloak x-show="serviceType === '{{ \App\Enums\SdmType::Ticketing->value }}' && has('cash_check')" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">TOM / CCC inspection and cash checking</h3>
                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        @foreach(['tom_ccc_number'=>'TOM/CCC number','end_of_shift_receipt_number'=>'End-of-shift receipt number'] as $name=>$label)
                            <div>
                                <x-input-label :for="$name" :value="$label"/>
                                <x-text-input :id="$name" :name="'cash_check['.$name.']'" class="mt-1 block w-full" :value="old('cash_check.'.$name,$memo?->cashCheck?->$name)"/>
                                <x-input-error :messages="$errors->get('cash_check.'.$name)" class="mt-2"/>
                            </div>
                        @endforeach
                        <div>
                            <x-input-label for="cash_check_date" value="Date"/>
                            <x-text-input id="cash_check_date" type="date" name="cash_check[check_date]" max="{{ now()->toDateString() }}" class="mt-1 block w-full" :value="old('cash_check.check_date', $memo?->cashCheck?->checked_at?->toDateString() ?? now()->toDateString())"/>
                            <x-input-error :messages="$errors->get('cash_check.check_date')" class="mt-2"/>
                        </div>
                        <div>
                            <x-input-label for="cash_check_time" value="Time of check (24 hour) in hh:mm format"/>
                            <input id="cash_check_time" type="time" name="cash_check[check_time]" value="{{ old('cash_check.check_time',$memo?->cashCheck?->checked_at?->format('H:i')) }}" class="{{ $inputClass }}">
                            <x-input-error :messages="$errors->get('cash_check.check_time')" class="mt-2"/>
                        </div>
                        <div>
                            <x-input-label value="Excess (+) / short (-) amount"/>
                            <x-text-input type="number" step="0.01" name="cash_check[difference_amount]" class="mt-1 block w-full" :value="old('cash_check.difference_amount',$memo?->cashCheck?->difference_amount)"/>
                            <x-input-error :messages="$errors->get('cash_check.difference_amount')" class="mt-2"/>
                        </div>
                        <div>
                            <x-input-label value="Hidden Cash Found"/>
                            <x-text-input type="number" min="0" step="0.01" name="cash_check[hidden_cash_amount]" class="mt-1 block w-full" :value="old('cash_check.hidden_cash_amount',$memo?->cashCheck?->hidden_cash_amount)"/>
                            <x-input-error :messages="$errors->get('cash_check.hidden_cash_amount')" class="mt-2"/>
                        </div>
                        @foreach(['checking_official_name'=>'Checking official name','checking_official_designation'=>'Checking official designation','checking_official_employee_number'=>'Checking official employee number'] as $name=>$label)
                            <div>
                                <x-input-label :for="$name" :value="$label"/>
                                <x-text-input :id="$name" :name="'cash_check['.$name.']'" class="mt-1 block w-full" :value="old('cash_check.'.$name,$memo?->cashCheck?->$name)"/>
                                <x-input-error :messages="$errors->get('cash_check.'.$name)" class="mt-2"/>
                            </div>
                        @endforeach
                        <div class="md:col-span-2">
                            <x-input-label value="Cash-check remarks (if any)"/>
                            <textarea name="cash_check[remarks]" rows="3" class="{{ $inputClass }}">{{ old('cash_check.remarks',$memo?->cashCheck?->remarks) }}</textarea>
                            <x-input-error :messages="$errors->get('cash_check.remarks')" class="mt-2"/>
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('cash_check')" class="mt-2"/>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
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
                            <x-input-error :messages="$errors->get('public_complaint.station_id')" class="mt-2"/>
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label value="Attachments (PDF/JPG/PNG, maximum 5 files, 10 MB each)"/>
                            <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="{{ $inputClass }} p-2">
                            <x-input-error :messages="$errors->get('attachments')" class="mt-2"/>
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

                <div class="flex flex-wrap justify-end gap-3">
                    <a href="{{ route('sdm-memos.index') }}" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-200">Cancel</a>
                    <button type="submit" class="rounded-lg border border-emerald-700 px-5 py-2.5 text-sm font-semibold text-emerald-700">{{ $isDraft ? 'Save draft' : 'Save changes' }}</button>
                    @if($isDraft)
                        <button type="submit" formaction="{{ $memo ? route('sdm-memos.submit', $memo) : route('sdm-memos.store-and-submit') }}" class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Submit memo</button>
                    @endif
                </div>
            
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
