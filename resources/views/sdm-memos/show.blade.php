<x-app-layout :title="'SDM'">
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">SDM</h2>
            @if($memo->submitted_at && filled($memo->serial_number))
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">SDM Serial Number: {{ $memo->serial_number }}</p>
            @endif
            <p class="text-sm text-gray-500">{{ $memo->service_type->code() }} · {{ $memo->cancellationLabel() ?? str($memo->status->value)->headline() }}</p>
        </div>
    </x-slot>
    <div class="py-10">
        @php
            $isStationManagerViewer = auth()->user()->hasRole(\App\Enums\UserRole::StationManager->value);
        @endphp
        <div class="mx-auto flex max-w-5xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>
            @endif
            @if($memo->cancellationLabel())
                <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 font-semibold text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">{{ $memo->cancellationLabel() }}</div>
            @endif
            @can('update', $memo)
                @unless($isStationManagerViewer)
                    <div class="flex justify-end"><a href="{{ route('sdm-memos.edit', $memo) }}" class="rounded-lg border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-700 dark:text-emerald-300">Edit SDM</a></div>
                @endunless
            @endcan
            @can('reassignStationManager', $memo)
                @unless($isStationManagerViewer)
                    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 shadow-sm dark:border-amber-900 dark:bg-amber-950/40">
                        <h3 class="font-semibold text-amber-950 dark:text-amber-100">Change forwarded SM</h3>
                        <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">You can change the Station Manager until {{ $memo->submitted_at->addDays(2)->format('d-m-Y H:i') }}, provided the current SM has not acted.</p>
                        <form method="POST" action="{{ route('sdm-memos.station-manager.update', $memo) }}" class="mt-5 flex flex-col gap-4">
                            @csrf
                            @method('PATCH')
                            <x-employee-autocomplete
                                name="station_manager_employee_number"
                                label="New SM employee number"
                                :candidates="$stationManagerCandidates"
                                :value="old('station_manager_employee_number')"
                            />
                            <x-input-error :messages="$errors->get('station_manager_employee_number')"/>
                            <div class="flex justify-end">
                                <button type="submit" class="rounded-lg bg-amber-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">Change SM</button>
                            </div>
                        </form>
                    </section>
                @endunless
            @endcan
            @can('handoverLineManager', $memo)
                <x-memo-handover-dialog
                    :modal-name="'handover-line-manager-'.$memo->id"
                    title="Handover pending SDM to another AM/Manager (Line)"
                    description="Select another active AM/Manager (Line) to take over this pending SDM."
                    :action="route('sdm-memos.line-manager.update', $memo)"
                    field-name="line_manager_employee_number"
                    field-label="Handover to AM/Manager (Line) employee number"
                    :candidates="$lineManagerHandoverCandidates"
                />
            @endcan
            @can('reassignCmcTeamLead', $memo)
                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 shadow-sm dark:border-amber-900 dark:bg-amber-950/40">
                    <h3 class="font-semibold text-amber-950 dark:text-amber-100">Re-send to another SM/CMC</h3>
                    <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">The SDM is still pending with {{ $memo->currentWorkflowStep->assigned_name }}. Select another active SM/CMC to receive it.</p>
                    <form method="POST" action="{{ route('sdm-memos.cmc-team-lead.update', $memo) }}" class="mt-5 grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
                        @csrf
                        @method('PATCH')
                        <div>
                            <x-input-label for="cmc_team_lead_id" value="New SM/CMC" required/>
                            <select id="cmc_team_lead_id" name="cmc_team_lead_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" required>
                                <option value="">Select SM/CMC</option>
                                @foreach($cmcTeamLeadCandidates as $cmcTeamLead)
                                    <option value="{{ $cmcTeamLead->id }}" @selected((string) old('cmc_team_lead_id') === (string) $cmcTeamLead->id)>{{ $cmcTeamLead->name }}@if($cmcTeamLead->employee_number) · {{ $cmcTeamLead->employee_number }}@endif</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('cmc_team_lead_id')" class="mt-2"/>
                        </div>
                        <button type="submit" class="rounded-lg bg-amber-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">Re-send SDM</button>
                    </form>
                </section>
            @endcan
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <dl class="grid gap-5 md:grid-cols-3">
                    @foreach([
                        'Type'=>$memo->service_type->label(),
                        'Station'=>$memo->station_name,
                        'Line'=>collect([$memo->line_code, $memo->line_name])->filter()->join(', '),
                        'Contractor'=>$memo->contractor_name,
                        'Contract'=>$memo->serviceContract?->contract_number,
                        'Incident date'=>$memo->incident_date?->format('d-m-Y'),
                        'Incident time (IST)'=>filled($memo->incident_time) ? substr($memo->incident_time, 0, 5) : null,
                        'Staff'=>$memo->staff_name,
                        'Employee number'=>$memo->employee_number,
                        'Remarks'=>$memo->station_record_brief_remarks,
                        // 'Staff mobile'=>$memo->staffAcknowledgement?->mobile_number,
                        'Created by'=>$memo->creator->name,
                        'Submitted At'=>$memo->submitted_at?->format('d-m-Y H:i')
                        ] as $label=>$value)
                        <div>
                            <dt class="text-xs font-semibold uppercase text-gray-500">{{ $label }}</dt>
                            <dd class="mt-1 text-gray-900 dark:text-white">{{ $value ?: '—' }}</dd>
                        </div>@endforeach</dl>
            </section>
            @if($memo->staffAcknowledgement?->proof_path)
                <a href="{{ route('sdm-memos.staff-signature.download', $memo) }}" class="text-sm font-semibold text-emerald-700 hover:underline dark:text-emerald-400">View private staff signature proof</a>
            @endif
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="font-semibold text-gray-900 dark:text-white">Deficiencies</h3>
                <div class="mt-4 flex flex-col gap-4">
                    @foreach($memo->deficiencies as $deficiency)
                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
                            <strong>{{ $deficiency->type->label() }}</strong>
                            @if($deficiency->remarks)
                                <p class="mt-2 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $deficiency->remarks }}</p>
                            @endif
                            @if($deficiency->lateReporting)
                                <p class="mt-2 text-sm text-gray-600">Booked {{ $deficiency->lateReporting->booked_shift_from }}–{{ $deficiency->lateReporting->booked_shift_to }}, actual {{ $deficiency->lateReporting->actual_reporting_time }}</p>
                                <p class="mt-1 text-sm font-semibold text-amber-700 dark:text-amber-300">Delay: {{ $deficiency->lateReporting->delayLabel() }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
            @if($memo->publicComplaint)
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="font-semibold">Public complaint</h3>
                    <p class="mt-2">{{ $memo->publicComplaint->complaint_serial_number }} · {{ $memo->publicComplaint->station_name }}</p>
                </section>
            @endif
            @if($memo->cashCheck)
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="font-semibold">TOM / CCC cash check</h3>
                    <dl class="mt-4 grid gap-4 md:grid-cols-2">
                        @foreach([
                            'TOM/CCC number'=>$memo->cashCheck->tom_ccc_number,
                            'End-of-shift receipt number'=>$memo->cashCheck->end_of_shift_receipt_number,
                            'Date'=>$memo->cashCheck->checked_at?->format('d-m-Y'),
                            'Time of check'=>$memo->cashCheck->checked_at?->format('H:i'),
                            'Excess (+) / short (-) amount'=>$memo->cashCheck->difference_amount,
                            'Hidden Cash Found'=>$memo->cashCheck->hidden_cash_amount,
                            'Checking official name'=>$memo->cashCheck->checking_official_name,
                            'Checking official designation'=>$memo->cashCheck->checking_official_designation,
                            'Checking official employee number'=>$memo->cashCheck->checking_official_employee_number,
                            'Cash-check remarks'=>$memo->cashCheck->remarks] as $label=>$value)
                            <div>
                                <dt class="text-xs uppercase text-gray-500">{{ $label }}</dt>
                                <dd>{{ $value ?: '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif
            @if($memo->attachments->isNotEmpty())
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="font-semibold">Attachments</h3>
                    <div class="mt-3 flex flex-col gap-2">
                        @foreach($memo->attachments as $attachment)
                            <a class="text-emerald-700 hover:underline" href="{{ route('sdm-memos.attachments.download',[$memo,$attachment]) }}">{{ $attachment->original_name }}</a>
                        @endforeach
                    </div>
                </section>
            @endif
            @if($memo->workflow_version >= 2)
                @include('sdm-memos.partials.penalty-workflow')
            @elseif($memo->submitted_at)
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Approval history</h3>
                    <ol class="mt-4 flex flex-col gap-4">
                        <li class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
                            <p class="font-semibold">Submitted by {{ $memo->creator->name }}</p>
                            <p class="text-sm text-gray-500">{{ $memo->submitted_at->format('d-m-Y H:i') }}</p>
                        </li>
                        @foreach($timelineApprovals as $approval)
                            <li class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $approval->role->label() }}</p>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $approval->decision === 'rejected' ? 'bg-rose-100 text-rose-800' : ($approval->decision === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800') }}">{{ $approval->decision === 'approved' ? ($approval->role === \App\Enums\WorkflowStage::DeputyGeneralManager ? 'Approved' : 'Forwarded') : str($approval->decision)->headline() }}</span>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Assigned to {{ $approval->assigned_name }}
                                    @if($approval->assigned_employee_number) · {{ $approval->assigned_employee_number }}@endif
                                </p>
                                @if($approval->acted_at)
                                    <p class="mt-1 text-sm text-gray-500">Acted by {{ $approval->signer_name }} on {{ $approval->acted_at->format('d-m-Y H:i') }}</p>
                                @endif
                                @if($approval->remarks)
                                    <p class="mt-2 whitespace-pre-line text-sm">{{ $approval->remarks }}</p>
                                @endif
                                @if($approval->attachments->isNotEmpty())
                                    <div class="mt-2 flex flex-col gap-1">
                                        @foreach($approval->attachments as $evidence)
                                            <a class="text-sm font-semibold text-emerald-700 hover:underline" href="{{ route('sdm-memos.workflow-evidence.download', [$memo, $evidence]) }}">{{ $evidence->original_name }}</a>
                                        @endforeach
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif
            @can('act', $memo)
                @if($memo->workflow_version < 2)
                <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 dark:border-emerald-900 dark:bg-emerald-950">
                    @php
                        $isLegacyStationManagerConfirmation = $memo->current_stage === \App\Enums\WorkflowStage::StationManager;
                        $legacyConfirmationPreview = $isLegacyStationManagerConfirmation
                            ? $stationManagerConfirmationPreview
                            : [
                                ['label' => 'SDM type', 'value' => $memo->service_type->label()],
                                ['label' => 'From', 'value' => collect([auth()->user()->name, auth()->user()->employee_number])->filter()->join(' · ')],
                                ['label' => 'Staff', 'value' => collect([$memo->staff_name, $memo->employee_number])->filter()->join(' · ')],
                                ['label' => 'Station', 'value' => $memo->station_name],
                                ['label' => 'Incident date', 'value' => $memo->incident_date?->format('d-m-Y')],
                                ['label' => 'Current stage', 'value' => $memo->current_stage->label()],
                            ];
                        $legacyConfirmationFields = $isLegacyStationManagerConfirmation
                            ? []
                            : [
                                ['label' => 'Forward to', 'name' => 'forward_to_employee_number'],
                                ['label' => 'Remarks', 'name' => 'remarks'],
                            ];
                    @endphp
                    <h3 class="font-semibold text-emerald-950 dark:text-emerald-100">Action required: {{ $memo->current_stage->label() }}</h3>
                    <form
                        method="POST"
                        enctype="multipart/form-data"
                        action="{{ route('sdm-memos.workflow.store', $memo) }}"
                        class="mt-4 flex flex-col gap-4"
                        x-data="sdmActionForm"
                        @submit="requestSdmConfirmation($event)"
                        data-confirmation-show-action="{{ $isLegacyStationManagerConfirmation ? 'false' : 'true' }}"
                        data-confirmation-preview="{{ json_encode($legacyConfirmationPreview) }}"
                        data-confirmation-fields="{{ json_encode($legacyConfirmationFields) }}"
                    >
                        @csrf
                        <div>
                            <x-input-label
                                for="remarks"
                                :value="match ($memo->current_stage) {
                                    \App\Enums\WorkflowStage::StationManager => 'Remarks / cancellation request to AM (optional)',
                                    \App\Enums\WorkflowStage::LineManager => 'Counselling remarks (mandatory for cancellation)',
                                    default => 'Remarks (mandatory for cancellation)',
                                }"
                                :required="$memo->current_stage !== \App\Enums\WorkflowStage::StationManager"
                            />
                            <textarea id="remarks" name="remarks" rows="4" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white">{{ old('remarks') }}</textarea>
                            <x-input-error :messages="$errors->get('remarks')" class="mt-2"/>
                        </div>
                        @if($memo->current_stage === \App\Enums\WorkflowStage::StationManager)
                            <div>
                                <x-employee-autocomplete
                                    name="forward_to_employee_number"
                                    label="Forward to AM/Line (employee number)"
                                    :candidates="$lineManagerCandidates"
                                    :value="old('forward_to_employee_number')"
                                />
                                <x-input-error :messages="$errors->get('forward_to_employee_number')" class="mt-2"/>
                            </div>
                        @endif
                        <div>
                            <x-input-label for="evidence" value="Supporting evidence (optional)"/>
                            <input id="evidence" name="evidence[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-1 block w-full text-sm">
                            <x-input-error :messages="$errors->get('evidence')" class="mt-2"/>
                        </div>
                        <div class="flex flex-wrap justify-end gap-3">
                            @if($memo->current_stage === \App\Enums\WorkflowStage::DeputyGeneralManager)
                                <button name="action" value="approve" class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white">Approve SDM</button>
                            @else
                                <button name="action" value="forward" data-sdm-confirm data-confirmation-action="Forward SDM" class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white">Verify and forward</button>
                                @if($memo->current_stage === \App\Enums\WorkflowStage::StationManager)
                                    <button name="action" value="request_cancellation" data-sdm-confirm data-confirmation-action="Request cancellation" class="rounded-lg border border-rose-600 px-5 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-500 dark:text-rose-300 dark:hover:bg-rose-950/40">Request for cancellation</button>
                                    <x-memo-station-manager-actions :memo="$memo" />
                                @endif
                            @endif
                            @if($memo->current_stage === \App\Enums\WorkflowStage::LineManager)
                                <button name="action" value="cancel" data-sdm-confirm data-confirmation-action="Cancel SDM" class="rounded-lg border border-rose-700 bg-white px-5 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50 dark:bg-gray-900 dark:text-rose-300 dark:hover:bg-rose-950/40">Cancel SDM</button>
                                <button name="action" value="cancel_after_counselling" data-sdm-confirm data-confirmation-action="Cancel after counselling" class="rounded-lg bg-rose-700 px-5 py-2.5 text-sm font-semibold text-white">Cancel after counselling</button>
                            @elseif($memo->current_stage !== \App\Enums\WorkflowStage::StationManager)
                                <button name="action" value="cancel" data-sdm-confirm data-confirmation-action="Cancel SDM" class="rounded-lg bg-rose-700 px-5 py-2.5 text-sm font-semibold text-white">Cancel SDM</button>
                            @endif
                        </div>
                        <x-sdm-confirmation-preview />
                    </form>
                    @if($isLegacyStationManagerConfirmation)
                        @can('reassignStationManager', $memo)
                            <x-memo-handover-dialog
                                :modal-name="'handover-station-manager-'.$memo->id"
                                title="Handover pending SDM"
                                description="Select another active Station Manager to take over this pending SDM."
                                :action="route('sdm-memos.station-manager.update', $memo)"
                                field-name="station_manager_employee_number"
                                field-label="Handover to SM employee number"
                                :candidates="$stationManagerCandidates"
                                :show-trigger="false"
                            />
                        @endcan
                    @endif
                </section>
                @endif
            @endcan
        </div>
    </div>
</x-app-layout>
