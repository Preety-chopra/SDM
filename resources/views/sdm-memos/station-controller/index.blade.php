<x-app-layout title="View All SDMs">
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">View All SDMs</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Your 10 most recently submitted SDMs.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</div>
            @endif

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                @foreach (['S. No.', 'SDM serial number', 'SDM type', 'Deficiency', 'Submitted on', 'Submitted to', 'Status', ''] as $heading)
                                    <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($memos as $memo)
                                @php
                                    $submittedTo = $memo->workflow_version >= 2
                                        ? $memo->stationManagerWorkflowStep
                                        : $memo->stationManagerApproval;
                                    $typeLabel = $memo->service_type === \App\Enums\SdmType::Marshall
                                        ? 'Marshal'
                                        : $memo->service_type->code();
                                    $isPending = $memo->stationControllerStatusLabel() === 'Pending';
                                @endphp
                                <tr>
                                    <td class="px-4 py-4 text-sm font-semibold text-gray-900 dark:text-white">{{ $loop->iteration }}</td>
                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-700 dark:text-gray-200"><span class="inline-flex items-center gap-2"><span>{{ $memo->serial_number ?: '—' }}</span><x-urgent-flag :memo="$memo" /></span></td>
                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-700 dark:text-gray-200">{{ $typeLabel }}</td>
                                    <td class="min-w-64 px-4 py-4 text-sm text-gray-700 dark:text-gray-200">
                                        {{ $memo->deficiencies->map(fn ($deficiency) => $deficiency->type->label())->join(', ') ?: '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-700 dark:text-gray-200">{{ $memo->submitted_at?->format('d-m-Y H:i') ?? '—' }}</td>
                                    <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-200">
                                        <span class="block font-medium text-gray-900 dark:text-white">{{ $submittedTo?->assigned_name ?: '—' }}</span>
                                        @if ($submittedTo?->assigned_employee_number)
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $submittedTo->assigned_employee_number }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4">
                                        <span @class([
                                            'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                            'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200' => $isPending,
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' => ! $isPending,
                                        ])>{{ $memo->stationControllerStatusLabel() }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <a href="{{ route('sdm-memos.show', $memo) }}" class="font-semibold text-emerald-700 hover:underline dark:text-emerald-400">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-12 text-center text-gray-500 dark:text-gray-400">You have not submitted any SDMs yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
