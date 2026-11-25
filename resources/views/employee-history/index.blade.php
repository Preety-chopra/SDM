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
            @php($cmcSdmHeadings = ['SDM Serial Number', 'SDM Type', 'SDM Deficiency', 'Line', 'Station', 'Incident Date'])
            <p class="text-sm text-gray-600 dark:text-gray-300">Employee number: <strong>{{ $filters['employee_number'] }}</strong>. Records are matched within the selected contractor. Draft SDMs are excluded.</p>
            <section class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 id="live-history-heading" class="font-semibold text-gray-900 dark:text-white">SDM records ({{ $memos->total() }})</h3>
                <div class="overflow-x-auto focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-500" role="region" aria-labelledby="live-history-heading" tabindex="0">
                    <table class="min-w-full text-left text-sm text-gray-700 dark:text-gray-200">
                        <caption class="sr-only">SDM records, most recent incident first</caption>
                        <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <tr>
                                @foreach ($cmcSdmHeadings as $heading)
                                    <th scope="col" class="whitespace-nowrap px-3 py-3">{{ $heading }}</th>
                                @endforeach
                                <x-cmc-sdm-assessment-headings />
                                <th scope="col" class="whitespace-nowrap px-3 py-3">Employee</th>
                                <th scope="col" class="whitespace-nowrap px-3 py-3">Status</th>
                                <th scope="col" class="whitespace-nowrap px-3 py-3">Assessment status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($memos as $record)
                                @php($highlightedProposal = \App\Support\CmcSdmTableRow::highlightedProposal($record))
                                <tr @if($highlightedProposal) data-cmc-proposal="{{ $highlightedProposal }}" @endif>
                                    @php($row = \App\Support\CmcSdmTableRow::fromMemo($record))
                                    <td class="whitespace-nowrap px-3 py-4 align-top">
                                        <a href="{{ route('sdm-memos.show', $record) }}" class="font-semibold text-emerald-700 hover:underline dark:text-emerald-300">{{ $row['serial_number'] ?? 'SDM #'.$record->id }}</a>
                                        <x-cmc-proposal-label :proposal="$highlightedProposal" />
                                    </td>
                                    <td class="px-3 py-4 align-top">{{ $row['service'] ?? '—' }}</td>
                                    <td class="min-w-56 px-3 py-4 align-top">{{ $row['deficiency'] ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 align-top">{{ $row['line'] ?? '—' }}</td>
                                    <td class="px-3 py-4 align-top">{{ $row['station'] ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 align-top">{{ $row['incident_date'] ?? '—' }}</td>
                                    <x-cmc-sdm-assessment-cells :row="$row" />
                                    <td class="whitespace-nowrap px-3 py-4 align-top">{{ $record->staff_name ?: '—' }}</td>
                                    <td class="px-3 py-4 align-top">{{ $record->status->label() }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 align-top">
                                        {{ $record->latestAssessment ? str($record->latestAssessment->status)->headline() : 'No assessment recorded' }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="13" class="px-3 py-6 text-gray-500 dark:text-gray-400">No SDM records found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $memos->links() }}
            </section>

            <section class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div><h3 id="imported-history-heading" class="font-semibold text-gray-900 dark:text-white">Imported historical records ({{ $historicalRecords->total() }})</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Values retained from historical imports.</p></div>
                <div class="overflow-x-auto focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-500" role="region" aria-labelledby="imported-history-heading" tabindex="0">
                    <table class="min-w-full text-left text-sm text-gray-700 dark:text-gray-200">
                        <caption class="sr-only">Imported employee records, most recent incident first</caption>
                        <thead class="border-b border-gray-200 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <tr>
                                @foreach($cmcSdmHeadings as $heading)
                                    <th scope="col" class="whitespace-nowrap px-3 py-3">{{ $heading }}</th>
                                @endforeach
                                <x-cmc-sdm-assessment-headings />
                                <th scope="col" class="px-3 py-3">SNO</th>
                                <th scope="col" class="whitespace-nowrap px-3 py-3">ON DUTY STAFF</th>
                                <th scope="col" class="whitespace-nowrap px-3 py-3">FILE NO</th>
                                <th scope="col" class="whitespace-nowrap px-3 py-3">Aadhar Number</th>
                                <th scope="col" class="whitespace-nowrap px-3 py-3">C&amp;P No</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($historicalRecords as $record)
                                <tr>
                                    @php($row = \App\Support\CmcSdmTableRow::fromHistorical($record))
                                    <td class="whitespace-nowrap px-3 py-4 align-top font-semibold">{{ $row['serial_number'] ?? '—' }}</td>
                                    <td class="px-3 py-4 align-top">{{ $row['service'] ?? '—' }}</td>
                                    <td class="min-w-56 px-3 py-4 align-top"><p class="max-w-xs whitespace-pre-wrap break-words">{{ $row['deficiency'] ?? '—' }}</p></td>
                                    <td class="whitespace-nowrap px-3 py-4 align-top">{{ $row['line'] ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 align-top">{{ $row['station'] ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 align-top">{{ $row['incident_date'] ?? '—' }}</td>
                                    <x-cmc-sdm-assessment-cells :row="$row" />
                                    <td class="px-3 py-4 align-top tabular-nums">{{ $historicalRecords->firstItem() + $loop->index }}</td>
                                    <td class="px-3 py-4 align-top">{{ $record->employee_name ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 align-top">{{ $record->employee_number ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 align-top">{{ $record->masked_aadhaar ?? '—' }}</td>
                                    <td class="px-3 py-4 align-top">{{ $record->cp_number ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="15" class="px-3 py-6 text-gray-500 dark:text-gray-400">No imported records found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $historicalRecords->links() }}
            </section>
        @endif
    </div>
</x-app-layout>
