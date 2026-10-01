@php
    $currentStep = $memo->currentWorkflowStep;
    $assignmentStage = $currentStep?->stage === \App\Enums\WorkflowStepType::CmcAssignment;
    $assessmentStage = in_array($currentStep?->stage, [\App\Enums\WorkflowStepType::LineReview, \App\Enums\WorkflowStepType::CmcAssessment], true);
    $cmcStage = $currentStep?->stage === \App\Enums\WorkflowStepType::CmcAssessment;
    $dgmStage = $currentStep?->stage === \App\Enums\WorkflowStepType::DgmApproval;
    $latestAssessment = $memo->assessments->last();
    $approvedAssessment = $memo->assessments->last(fn ($assessment) => $assessment->status === 'approved');
    $oldAssessmentItems = old('items');
    $prefillAssessmentItems = match (true) {
        is_array($oldAssessmentItems) => collect(),
        $cmcStage => $latestAssessment?->items->keyBy('contract_penalty_clause_id') ?? collect(),
        $currentStep?->stage === \App\Enums\WorkflowStepType::LineReview => $lineReviewPenaltyPrefillItems,
        default => collect(),
    };
    $selectedClauseIds = is_array($oldAssessmentItems)
        ? collect($oldAssessmentItems)->pluck('contract_penalty_clause_id')->filter()->map(fn ($id) => (string) $id)->values()
        : $prefillAssessmentItems->keys()->map(fn ($id) => (string) $id)->values();
    $inputClass = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100';
    $remarksLabel = match ($currentStep?->stage) {
        \App\Enums\WorkflowStepType::DgmApproval => 'Remarks (mandatory when returning or cancelling)',
        \App\Enums\WorkflowStepType::StationManager => 'Remarks / cancellation request to AM (optional)',
        \App\Enums\WorkflowStepType::LineReview => 'Remarks (mandatory for cancellation)',
        default => 'Remarks',
    };
    $isStationManagerConfirmation = $currentStep?->stage === \App\Enums\WorkflowStepType::StationManager;
    $confirmationPreview = $isStationManagerConfirmation
        ? $stationManagerConfirmationPreview
        : [
            ['label' => 'SDM type', 'value' => $memo->service_type->label()],
            ['label' => 'From', 'value' => collect([auth()->user()->name, auth()->user()->employee_number])->filter()->join(' · ')],
            ['label' => 'Staff', 'value' => collect([$memo->staff_name, $memo->employee_number])->filter()->join(' · ')],
            ['label' => 'Station', 'value' => $memo->station_name],
            ['label' => 'Incident date', 'value' => $memo->incident_date?->format('d-m-Y')],
            ['label' => 'Current stage', 'value' => $currentStep?->stage->label()],
        ];
    $confirmationFields = $isStationManagerConfirmation
        ? []
        : [
            ['label' => 'Forward to', 'name' => 'forward_to_employee_number'],
            ['label' => 'Negative points', 'name' => 'negative_points'],
            ['label' => 'Staff action', 'name' => 'staff_action'],
            ['label' => 'Bill month', 'name' => 'bill_month'],
            ['label' => 'Monthly bill value', 'name' => 'monthly_bill_value'],
            ['label' => 'Remarks', 'name' => 'remarks'],
        ];
@endphp

@if($showContractAssessment)
<section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h3 class="font-semibold text-gray-900 dark:text-white">Contract assessment</h3><p class="mt-1 text-sm text-gray-500">{{ $memo->serviceContract?->contract_number }} · {{ $memo->service_type->label() }}</p></div>
        @if($currentStep)<span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-200">Current: {{ $currentStep->stage->label() }}</span>@endif
    </div>
    @if($memo->assessments->isNotEmpty())
        <div class="mt-5 flex flex-col gap-4">
            @foreach($memo->assessments as $assessment)
                @php
                    $reviewLabel = match ($assessment->source_stage) {
                        \App\Enums\WorkflowStepType::LineReview->value => "AM/Manager (Line) Review {$assessment->round}",
                        \App\Enums\WorkflowStepType::CmcAssessment->value => "CMC Review {$assessment->round}",
                        default => "Review {$assessment->round}",
                    };
                @endphp
                <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex flex-wrap justify-between gap-2"><p class="font-semibold">{{ $reviewLabel }}</p><span class="text-sm font-semibold {{ $assessment->cap_warning ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-300' }}">₹{{ number_format((float) $assessment->proposed_total, 2) }} · {{ str($assessment->status)->headline() }}</span></div>
                    <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead class="text-left text-xs uppercase text-gray-500"><tr><th class="py-2 pr-4">Clause</th><th class="py-2 pr-4">Calculation</th><th class="py-2 text-right">Proposed</th></tr></thead><tbody>@foreach($assessment->items as $item)<tr class="border-t border-gray-100 dark:border-gray-700"><td class="py-2 pr-4">{{ $item->clause_serial_number }}. {{ $item->clause_description }}<p class="text-xs text-gray-500">{{ $item->justification }}</p></td><td class="py-2 pr-4">{{ $item->calculation_type->label() }}@if($item->suggested_amount !== null)<br><span class="text-xs text-gray-500">Calculated ₹{{ number_format((float) $item->suggested_amount, 2) }}</span>@endif</td><td class="py-2 text-right font-semibold">₹{{ number_format((float) $item->proposed_amount, 2) }}</td></tr>@endforeach</tbody></table></div>
                    @if($assessment->negative_points !== null)<p class="mt-3 text-sm"><strong>{{ $assessment->negative_points }} negative points</strong> · {{ str($assessment->staff_action)->headline() }}</p>@endif
                    @if($assessment->cap_warning)<p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">Advisory cap warning: applicable clauses total {{ number_format((float) $assessment->cap_percentage, 3) }}% of the monthly bill. DGM acknowledgement is required.</p>@endif
                    @if($assessment->administrative_review_flag)<p class="mt-2 rounded-lg bg-rose-50 p-3 text-sm font-semibold text-rose-900 dark:bg-rose-950/50 dark:text-rose-200">Administrative review flagged: the advisory threshold was reached in three consecutive bill months. No automatic termination has been applied.</p>@endif
                </article>
            @endforeach
        </div>
    @else<p class="mt-4 text-sm text-gray-500">No penalty proposal has been recorded yet.</p>@endif
    <div class="mt-5 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Memo CMC Staff</p>
        <p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $memo->cmcStaff?->name ?? 'Not assigned yet' }}</p>
        @if($memo->cmcStaff?->employee_number)<p class="text-sm text-gray-500">{{ $memo->cmcStaff->employee_number }}</p>@endif
        @can('assignCmcStaff', $memo)
            <form method="POST" action="{{ route('sdm-memos.cmc-assignment.update', $memo) }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                @csrf
                @method('PATCH')
                <div>
                    <x-input-label for="reassign_cmc_staff_id" value="Reassign CMC Staff" required/>
                    <select id="reassign_cmc_staff_id" name="cmc_staff_id" class="{{ $inputClass }}" required>
                        <option value="">Select CMC Staff</option>
                        @foreach($cmcStaffUsers as $cmcUser)
                            <option value="{{ $cmcUser->id }}" @selected((string) old('cmc_staff_id') === (string) $cmcUser->id)>{{ $cmcUser->name }}@if($cmcUser->employee_number) · {{ $cmcUser->employee_number }}@endif</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('cmc_staff_id')" class="mt-2"/>
                </div>
                <button class="rounded-lg bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">Reassign staff</button>
            </form>
        @endcan
    </div>
</section>
@endif

<section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <h3 class="font-semibold text-gray-900 dark:text-white">Case timeline</h3>
    <p class="text-sm text-gray-500 dark:text-gray-400">Follow the case through the approval levels available to you.</p>
    <div class="mt-6 flex flex-col gap-8">
        @foreach($timelineSteps->groupBy('round') as $round => $roundSteps)
            <div>
                @if(!$loop->first)
                    <div class="mb-5 flex items-center gap-3"><span class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></span><h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Reassessment cycle {{ $loop->iteration - 1 }}</h4><span class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></span></div>
                @endif
                <ol class="mx-auto flex max-w-3xl flex-col items-stretch">
                    @foreach($roundSteps as $step)
                        @php
                            $isCurrent = $step->id === $memo->current_workflow_step_id;
                            $timelineState = $isCurrent ? 'current' : match ($step->decision) {
                                'forwarded', 'approved' => 'forwarded',
                                'returned' => 'returned',
                                'cancelled' => 'cancelled',
                                default => 'waiting',
                            };
                            $tileClasses = match ($timelineState) {
                                'current' => 'border-amber-400 bg-amber-50 ring-2 ring-amber-100 dark:border-amber-600 dark:bg-amber-950/40 dark:ring-amber-900/40',
                                'forwarded' => 'border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/35',
                                'returned' => 'border-orange-300 bg-orange-50 dark:border-orange-800 dark:bg-orange-950/35',
                                'cancelled' => 'border-rose-300 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/35',
                                default => 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900',
                            };
                            $statusClasses = match ($timelineState) {
                                'current' => 'bg-amber-200 text-amber-900 dark:bg-amber-900 dark:text-amber-100',
                                'forwarded' => 'bg-emerald-200 text-emerald-900 dark:bg-emerald-900 dark:text-emerald-100',
                                'returned' => 'bg-orange-200 text-orange-900 dark:bg-orange-900 dark:text-orange-100',
                                'cancelled' => 'bg-rose-200 text-rose-900 dark:bg-rose-900 dark:text-rose-100',
                                default => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
                            };
                            $statusLabel = $isCurrent ? 'Current approval' : match ($step->decision) {
                                'forwarded' => 'Forwarded',
                                'approved' => 'Approved',
                                'returned' => 'Returned',
                                'cancelled' => 'Cancelled',
                                default => 'Waiting',
                            };
                        @endphp
                        @if(!$loop->first)
                            <li aria-hidden="true" class="flex h-10 flex-col items-center justify-center">
                                <span class="h-6 w-0.5 {{ $timelineState === 'waiting' ? 'bg-gray-200 dark:bg-gray-700' : 'bg-emerald-300 dark:bg-emerald-800' }}"></span>
                                <svg class="h-4 w-4 {{ $timelineState === 'waiting' ? 'text-gray-300 dark:text-gray-600' : 'text-emerald-500 dark:text-emerald-600' }}" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                            </li>
                        @endif
                        <li data-timeline-state="{{ $timelineState }}" class="rounded-2xl border p-4 shadow-sm sm:p-5 {{ $tileClasses }}">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $timelineState === 'forwarded' ? 'bg-emerald-600 text-white' : ($timelineState === 'current' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-300') }}">
                                        @if($timelineState === 'forwarded')<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.051l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.142Z" clip-rule="evenodd"/></svg>@elseif($timelineState === 'current')<span class="h-3 w-3 rounded-full bg-white"></span>@else<span class="text-sm font-bold">{{ str_pad((string) $step->sequence, 2, '0', STR_PAD_LEFT) }}</span>@endif
                                    </span>
                                    <div><h5 class="font-semibold text-gray-950 dark:text-white">{{ $step->stage->label() }}</h5><p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Assigned to <strong>{{ $step->assigned_name }}</strong>@if($step->assigned_employee_number) · {{ $step->assigned_employee_number }}@endif</p></div>
                                </div>
                                <span class="self-start rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide {{ $statusClasses }}">{{ $statusLabel }}</span>
                            </div>
                            @if($step->acted_at)<p class="mt-3 border-t border-black/5 pt-3 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">{{ $statusLabel }} by {{ $step->actor_name }} on {{ $step->acted_at->format('d-m-Y H:i') }}</p>@endif
                            @if($step->remarks)<p class="mt-2 whitespace-pre-line rounded-lg bg-white/60 p-3 text-sm text-gray-700 dark:bg-black/10 dark:text-gray-200">{{ $step->remarks }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </div>
        @endforeach
    </div>
</section>

@if($priorApprovedCases->isNotEmpty())
    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"><h3 class="font-semibold">Previous approved cases for this staff member</h3><div class="mt-3 flex flex-col gap-2">@foreach($priorApprovedCases as $prior)<a href="{{ route('sdm-memos.show', $prior) }}" class="rounded-lg bg-gray-50 p-3 text-sm hover:bg-emerald-50 dark:bg-gray-900 dark:hover:bg-emerald-950">{{ $prior->service_type->code() }} · {{ $prior->incident_date?->format('d-m-Y') }} · {{ $prior->assessments->first()?->negative_points ?? 0 }} points</a>@endforeach</div></section>
@endif

@can('act', $memo)
    <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 dark:border-emerald-900 dark:bg-emerald-950/40" @if($assessmentStage) x-data='{ selectedClauses: @json($selectedClauseIds) }' @endif>
        <h3 class="font-semibold text-emerald-950 dark:text-emerald-100">Action required: {{ $currentStep->stage->label() }}</h3>
        <form
            method="POST"
            action="{{ route('sdm-memos.workflow.store', $memo) }}"
            class="mt-4 flex flex-col gap-5"
            x-data="sdmActionForm"
            @submit="requestSdmConfirmation($event)"
            data-confirmation-show-action="{{ $isStationManagerConfirmation ? 'false' : 'true' }}"
            data-confirmation-preview="{{ json_encode($confirmationPreview) }}"
            data-confirmation-fields="{{ json_encode($confirmationFields) }}"
        >@csrf
            @if($assignmentStage)
                <div>
                    <x-input-label for="cmc_staff_id" value="Assign CMC Staff" required/>
                    <select id="cmc_staff_id" name="cmc_staff_id" class="{{ $inputClass }}" required>
                        <option value="">Select CMC Staff</option>
                        @foreach($cmcStaffUsers as $cmcUser)
                            <option value="{{ $cmcUser->id }}" @selected((string) old('cmc_staff_id') === (string) $cmcUser->id)>{{ $cmcUser->name }}@if($cmcUser->employee_number) · {{ $cmcUser->employee_number }}@endif</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('cmc_staff_id')" class="mt-2"/>
                </div>
            @endif
            @if($assessmentStage)
                <div class="flex flex-col gap-3">
                    <x-input-label value="Select and assess contractual clauses" required/>
                    @if($currentStep->stage === \App\Enums\WorkflowStepType::LineReview)
                        <p class="text-sm text-emerald-800 dark:text-emerald-200">Penalties matching the recorded deficiencies are pre-selected. You may add or remove clauses before forwarding.</p>
                    @endif
                    @foreach($memo->serviceContract->penaltyClauses as $index => $clause)
                        @php($prefillItem = $prefillAssessmentItems->get($clause->id))
                        <article class="rounded-xl border border-emerald-200 bg-white p-4 dark:border-emerald-900 dark:bg-gray-900">
                            <label class="flex items-start gap-3"><input type="checkbox" value="{{ $clause->id }}" x-model="selectedClauses" class="mt-1 rounded border-gray-300 text-emerald-700"><span><strong>{{ $clause->serial_number }}. {{ $clause->description }}</strong><span class="mt-1 block text-xs text-gray-500">{{ $clause->calculation_type->label() }}@if($clause->maximum_amount) · maximum ₹{{ number_format((float) $clause->maximum_amount, 2) }}@elseif($clause->base_amount) · base ₹{{ number_format((float) $clause->base_amount, 2) }}@endif</span></span></label>
                            <div class="mt-3 grid gap-3 md:grid-cols-3" x-show="selectedClauses.includes('{{ $clause->id }}')">
                                <input type="hidden" name="items[{{ $index }}][contract_penalty_clause_id]" value="{{ $clause->id }}" :disabled="!selectedClauses.includes('{{ $clause->id }}')">
                                @if($clause->calculation_type === \App\Enums\PenaltyCalculationType::PerDayPerWorker)
                                    <div><x-input-label value="Days"/><input name="items[{{ $index }}][calculation_inputs][days]" type="number" min="0" value="{{ old("items.$index.calculation_inputs.days", data_get($prefillItem, 'calculation_inputs.days')) }}" class="{{ $inputClass }}" :disabled="!selectedClauses.includes('{{ $clause->id }}')"></div>
                                    <div><x-input-label value="Workmen"/><input name="items[{{ $index }}][calculation_inputs][workers]" type="number" min="0" value="{{ old("items.$index.calculation_inputs.workers", data_get($prefillItem, 'calculation_inputs.workers')) }}" class="{{ $inputClass }}" :disabled="!selectedClauses.includes('{{ $clause->id }}')"></div>
                                @elseif($clause->calculation_type === \App\Enums\PenaltyCalculationType::FixedPlusCost)
                                    <div><x-input-label value="Loss/damage cost"/><input name="items[{{ $index }}][calculation_inputs][additional_cost]" type="number" min="0" step="0.01" value="{{ old("items.$index.calculation_inputs.additional_cost", data_get($prefillItem, 'calculation_inputs.additional_cost')) }}" class="{{ $inputClass }}" :disabled="!selectedClauses.includes('{{ $clause->id }}')"></div>
                                @endif
                                <div><x-input-label value="Proposed amount" required/><input name="items[{{ $index }}][proposed_amount]" type="number" min="0" step="0.01" value="{{ old("items.$index.proposed_amount", data_get($prefillItem, 'proposed_amount')) }}" class="{{ $inputClass }}" :disabled="!selectedClauses.includes('{{ $clause->id }}')" :required="selectedClauses.includes('{{ $clause->id }}')"></div>
                                <div class="md:col-span-3"><x-input-label value="Justification (optional)"/><textarea name="items[{{ $index }}][justification]" rows="2" class="{{ $inputClass }}" :disabled="!selectedClauses.includes('{{ $clause->id }}')">{{ old("items.$index.justification", data_get($prefillItem, 'justification')) }}</textarea></div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
            @if($cmcStage)
                <div class="grid gap-4 md:grid-cols-2"><div><x-input-label value="Negative points" required/><input name="negative_points" type="number" min="0" class="{{ $inputClass }}" required></div><div><x-input-label value="Staff action" required/><select name="staff_action" class="{{ $inputClass }}" required>@foreach(['none','warning','final_warning','recall'] as $action)<option value="{{ $action }}">{{ str($action)->headline() }}</option>@endforeach</select></div><div><x-input-label value="Bill month" required/><input name="bill_month" type="month" class="{{ $inputClass }}" required></div><div><x-input-label value="Monthly bill value" required/><input name="monthly_bill_value" type="number" min="0.01" step="0.01" class="{{ $inputClass }}" required></div></div>
            @endif
            @unless($assignmentStage)<div><x-input-label :value="$remarksLabel" :required="$dgmStage"/><textarea name="remarks" rows="3" class="{{ $inputClass }}"></textarea><x-input-error :messages="$errors->get('remarks')" class="mt-2"/></div>@endunless
            @if($currentStep->stage === \App\Enums\WorkflowStepType::StationManager)
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
            @if($dgmStage && $latestAssessment?->cap_warning)<label class="flex items-center gap-2 text-sm font-semibold text-amber-900 dark:text-amber-200"><input type="checkbox" name="cap_acknowledged" value="1" class="rounded border-gray-300 text-emerald-700"> <span>I acknowledge the advisory 10% monthly-bill warning. <span data-required-marker aria-hidden="true" class="font-bold !text-red-600 dark:!text-red-400">*</span><span class="sr-only"> (required for approval)</span></span></label>@endif
            <div class="flex flex-wrap justify-end gap-3">
                @if($assignmentStage)
                    <button name="action" value="assign" class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Assign for scrutiny</button>
                @elseif($dgmStage)
                    <button name="action" value="return" class="rounded-lg border border-amber-600 px-5 py-2.5 text-sm font-semibold text-amber-800 dark:text-amber-300">Return to CMC</button>
                    <button name="action" value="cancel" data-sdm-confirm data-confirmation-action="Cancel SDM" class="rounded-lg bg-rose-700 px-5 py-2.5 text-sm font-semibold text-white">Cancel</button>
                    <button name="action" value="approve" class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white">Approve assessment</button>
                @else
                    <button name="action" value="forward" data-sdm-confirm data-confirmation-action="Forward SDM" class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white">Verify and forward</button>
                    @if($currentStep->stage === \App\Enums\WorkflowStepType::StationManager)
                        <button name="action" value="request_cancellation" data-sdm-confirm data-confirmation-action="Request cancellation" class="rounded-lg border border-rose-600 px-5 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-500 dark:text-rose-300 dark:hover:bg-rose-950/40">Request for cancellation</button>
                    @elseif($currentStep->stage === \App\Enums\WorkflowStepType::LineReview)
                        <button name="action" value="cancel" data-sdm-confirm data-confirmation-action="Cancel SDM" class="rounded-lg bg-rose-700 px-5 py-2.5 text-sm font-semibold text-white">Cancel SDM</button>
                    @endif
                @endif
            </div>
            <x-sdm-confirmation-preview />
        </form>
    </section>
@endcan

@if($approvedAssessment)
    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <h3 class="font-semibold">Penalty letter, objection, and deduction</h3>
        @if($memo->penaltyLetters->isNotEmpty())
            <div class="mt-4 flex flex-col gap-2">
                @foreach($memo->penaltyLetters as $letter)
                    <a href="{{ route('sdm-memos.penalty-letter.download', [$memo, $letter]) }}" class="rounded-lg bg-gray-50 p-3 text-sm hover:bg-emerald-50 dark:bg-gray-900 dark:hover:bg-emerald-950">Letter revision {{ $letter->revision }} issued {{ $letter->issued_at->format('d-m-Y') }} · objection due {{ $letter->objection_due_at->format('d-m-Y') }} · {{ $letter->is_current ? 'Current' : 'Superseded' }}</a>
                @endforeach
            </div>
        @endif
        @if($memo->status === \App\Enums\MemoStatus::PendingPenaltyLetter && auth()->user()->can(\App\Enums\UserPermission::ManagePenaltyLetters->value) && auth()->user()->can('manageCmcLifecycle', $memo))
            <form method="POST" enctype="multipart/form-data" action="{{ route('sdm-memos.penalty-letter.store', $memo) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                @csrf
                <div>
                    <x-input-label value="Issue date" required/>
                    <input name="issued_at" type="date" class="{{ $inputClass }}" required>
                </div>
                <div>
                    <x-input-label value="Signed letter" required/>
                    <input name="letter" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="{{ $inputClass }} p-2" required>
                </div>
                <div class="md:col-span-2">
                    <x-input-label value="Delivery details" required/>
                    <textarea name="delivery_details" class="{{ $inputClass }}" required></textarea>
                </div>
                <button class="rounded-lg bg-emerald-700 px-5 py-2.5 font-semibold text-white md:col-span-2">Issue signed letter</button>
            </form>
        @endif
        @if($memo->status === \App\Enums\MemoStatus::ObjectionWindow && auth()->user()->can(\App\Enums\UserPermission::ManageObjections->value) && auth()->user()->can('manageCmcLifecycle', $memo))
            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                <form method="POST" enctype="multipart/form-data" action="{{ route('sdm-memos.objection.store', $memo) }}" class="flex flex-col gap-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    @csrf
                    <h4 class="font-semibold">Record objection</h4>
                    <div>
                        <x-input-label value="Objection received date" required/>
                        <input name="received_at" type="date" class="{{ $inputClass }}" required>
                    </div>
                    <div>
                        <x-input-label value="Objection remarks" required/>
                        <textarea name="remarks" rows="3" class="{{ $inputClass }}" required></textarea>
                    </div>
                    <div>
                        <x-input-label value="Supporting attachment (optional)"/>
                        <input name="attachment" type="file" class="{{ $inputClass }} p-2"></div>
                        <button class="rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white">Record and reassess</button>
                    </form>
                    <form method="POST" action="{{ route('sdm-memos.no-objection.store', $memo) }}" class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        @csrf
                        <h4 class="font-semibold">No objection received</h4>
                        <p class="mt-2 text-sm text-gray-500">Available after {{ $memo->currentPenaltyLetter?->objection_due_at?->format('d-m-Y') }}.</p>
                        <button class="mt-3 rounded-lg bg-emerald-700 px-4 py-2 font-semibold text-white">Confirm no objection</button>
                    </form>
                </div>
        @endif
        @if($memo->status === \App\Enums\MemoStatus::PendingDeduction && auth()->user()->can(\App\Enums\UserPermission::RecordDeductions->value) && auth()->user()->can('manageCmcLifecycle', $memo))
            <form method="POST" action="{{ route('sdm-memos.deduction.store', $memo) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                @csrf
                <div>
                    <x-input-label value="Bill month" required/>
                    <input name="bill_month" type="month" class="{{ $inputClass }}" required>
                </div>
                <div>
                    <x-input-label value="Bill/reference number" required/>
                    <input name="bill_reference" class="{{ $inputClass }}" required>
                </div>
                <div>
                    <x-input-label value="Deduction date" required/>
                    <input name="deducted_at" type="date" class="{{ $inputClass }}" required></div>
                <div>
                    <x-input-label value="Exact approved amount" required/>
                    <input name="amount" type="number" step="0.01" value="{{ $approvedAssessment->proposed_total }}" class="{{ $inputClass }}" required>
                </div>
                <button class="rounded-lg bg-emerald-700 px-5 py-2.5 font-semibold text-white md:col-span-2">Record deduction and close</button>
            </form>
        @endif
        @if($memo->deduction)
            <p class="mt-5 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">Deducted ₹{{ number_format((float) $memo->deduction->amount, 2) }} against {{ $memo->deduction->bill_reference }} on {{ $memo->deduction->deducted_at->format('d-m-Y') }}. Case closed.</p>
        @endif
    </section>
@endif
