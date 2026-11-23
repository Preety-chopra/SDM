<x-app-layout title="Employee history">
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Employee history</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">SDMs and imported historical records for an employee.</p>
    </x-slot>

    <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
        @if($memo)
            <section class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <a href="{{ route('sdm-memos.show', $memo) }}" class="w-fit text-sm font-semibold text-emerald-700 hover:underline dark:text-emerald-300">Back to SDM</a>
                <h3 class="font-semibold text-gray-900 dark:text-white">{{ $memo->staff_name ?: 'Employee' }} · {{ $memo->employee_number ?: 'No employee number' }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $memo->contractor_name ?: 'Contractor' }} · Current SDM excluded from these results.</p>
                <a href="{{ route('employee-history.index', $filters) }}" class="w-fit text-sm font-semibold text-emerald-700 hover:underline dark:text-emerald-300">Search employee history</a>
            </section>
        @else
            <form method="GET" action="{{ route('employee-history.index') }}" class="grid gap-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm md:grid-cols-[1fr_1fr_auto] md:items-start dark:border-gray-700 dark:bg-gray-800">
                <div>
                    <x-input-label for="contractor_id" value="Contractor" required />
                    <select id="contractor_id" name="contractor_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">Select contractor</option>
                        @foreach($contractors as $contractor)
                            <option value="{{ $contractor->id }}" @selected((string) ($filters['contractor_id'] ?? '') === (string) $contractor->id)>{{ $contractor->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('contractor_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="employee_number" value="Employee number" required />
                    <x-text-input id="employee_number" name="employee_number" :value="$filters['employee_number'] ?? ''" maxlength="100" required class="mt-1 block w-full" aria-describedby="employee-number-help" />
                    <p id="employee-number-help" class="mt-2 text-xs text-gray-500 dark:text-gray-400">Enter the complete employee number, including leading zeros.</p>
                    <x-input-error :messages="$errors->get('employee_number')" class="mt-2" />
                </div>
                <div class="flex gap-3 md:pt-6">
                    <x-primary-button>Search history</x-primary-button>
                    <a href="{{ route('employee-history.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-gray-600 hover:underline dark:text-gray-300">Reset</a>
                </div>
            </form>
        @endif

        @if($memos === null)
            <p class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                {{ $memo ? 'This SDM has no contractor or employee number. Use the employee history search to find records.' : 'Select a contractor and enter an employee number to see their history.' }}
            </p>
        @else
            <p class="text-sm text-gray-600 dark:text-gray-300">Employee number: <strong>{{ $filters['employee_number'] }}</strong>. Records are matched within the selected contractor. Draft SDMs are excluded.</p>
            <section class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="font-semibold text-gray-900 dark:text-white">SDM records ({{ $memos->total() }})</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm text-gray-700 dark:text-gray-200">
                        <caption class="sr-only">SDM records, most recent incident first</caption>
                        <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <tr><th scope="col" class="px-3 py-3">SDM / Employee</th><th scope="col" class="px-3 py-3">Incident / Station</th><th scope="col" class="px-3 py-3">Deficiencies</th><th scope="col" class="px-3 py-3">Status</th><th scope="col" class="px-3 py-3">Assessment</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($memos as $record)
                                <tr>
                                    <td class="px-3 py-4 align-top">
                                        <a href="{{ route('sdm-memos.show', $record) }}" class="font-semibold text-emerald-700 hover:underline dark:text-emerald-300">{{ $record->serial_number ?: 'SDM #'.$record->id }}</a>
                                        <p class="mt-1">{{ $record->staff_name ?: '—' }}</p><p class="text-xs text-gray-500 dark:text-gray-400">{{ $record->service_type->label() }}</p>
                                    </td>
                                    <td class="px-3 py-4 align-top"><p class="whitespace-nowrap">{{ $record->incident_date?->format('d-m-Y') ?? '—' }}</p><p class="mt-1">{{ $record->station_name ?: '—' }}</p></td>
                                    <td class="px-3 py-4 align-top">{{ $record->deficiencyLabels()->join(', ') ?: '—' }}</td>
                                    <td class="px-3 py-4 align-top">{{ $record->status->label() }}</td>
                                    <td class="px-3 py-4 align-top">
                                        @if($assessment = $assessments->get($record->id))
                                            <p class="font-semibold">₹{{ number_format((float) $assessment->proposed_total, 2) }} · {{ str($assessment->status)->headline() }}</p>
                                            <p class="mt-1">{{ $assessment->negative_points ?? '—' }} negative points</p>
                                            <p>{{ filled($assessment->staff_action) ? str($assessment->staff_action)->headline() : '—' }}</p>
                                        @else
                                            <span class="text-gray-500 dark:text-gray-400">No assessment recorded</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-6 text-gray-500 dark:text-gray-400">No SDM records found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $memos->links() }}
            </section>

            <section class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div><h3 class="font-semibold text-gray-900 dark:text-white">Imported historical records ({{ $historicalRecords->total() }})</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Values retained from historical imports.</p></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm text-gray-700 dark:text-gray-200">
                        <caption class="sr-only">Imported employee records, most recent incident first</caption>
                        <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <tr><th scope="col" class="px-3 py-3">Memo / Employee</th><th scope="col" class="px-3 py-3">Incident / Station</th><th scope="col" class="px-3 py-3">Discrepancies / Action</th><th scope="col" class="px-3 py-3">Contractor penalty</th><th scope="col" class="px-3 py-3">Negative points</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($historicalRecords as $record)
                                <tr>
                                    <td class="px-3 py-4 align-top"><p class="font-semibold">{{ $record->memo_number ?: 'Historical #'.$record->id }}</p><p class="mt-1">{{ $record->employee_name ?: '—' }}</p>@if($record->cp_number)<p class="text-xs text-gray-500 dark:text-gray-400">CP: {{ $record->cp_number }}</p>@endif</td>
                                    <td class="px-3 py-4 align-top"><p class="whitespace-nowrap">{{ $record->incident_date?->format('d-m-Y') ?? $record->source_date ?? '—' }}</p><p class="mt-1">{{ $record->source_station ?: '—' }}</p><p class="text-xs text-gray-500 dark:text-gray-400">{{ $record->source_line }}</p></td>
                                    <td class="px-3 py-4 align-top"><p class="whitespace-pre-wrap break-words">{{ $record->discrepancies ?: '—' }}</p><p class="mt-2 whitespace-pre-wrap break-words text-gray-500 dark:text-gray-400">{{ $record->recommended_action ?: '—' }}</p></td>
                                    <td class="whitespace-nowrap px-3 py-4 align-top">{{ $record->contractor_penalty !== null ? '₹'.number_format((float) $record->contractor_penalty, 2) : '—' }}</td>
                                    <td class="px-3 py-4 align-top">{{ $record->negative_points ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-6 text-gray-500 dark:text-gray-400">No imported records found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $historicalRecords->links() }}
            </section>
        @endif
    </div>
</x-app-layout>
