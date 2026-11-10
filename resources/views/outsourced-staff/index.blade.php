<x-app-layout title="Outsourced staff">
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-900 dark:text-white">Outsourced staff</h2></x-slot>
    <div class="py-8">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>
            @endif
            <x-input-error :messages="$errors->all()" />
            @if (session('import_result.warnings'))
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                    <p class="font-semibold">Aadhaar values requiring correction</p>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach (session('import_result.warnings') as $warning)<li>{{ $warning }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <div class="flex flex-wrap items-center justify-between gap-4">
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ number_format($staff->total()) }} {{ Str::plural('staff record', $staff->total()) }} matching your filters.</p>
                <a href="{{ route('outsourced-staff.create') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">Add staff member</a>
            </div>
            <form method="GET" action="{{ route('outsourced-staff.index') }}" class="grid gap-4 rounded-2xl border border-gray-200 bg-white p-5 sm:grid-cols-2 lg:grid-cols-5 dark:border-gray-700 dark:bg-gray-800">
                <div>
                    <x-input-label for="q" value="Search" />
                    <x-text-input id="q" name="q" :value="request('q')" placeholder="ID, name or permit" maxlength="100" class="mt-1 w-full" />
                </div>
                <div>
                    <x-input-label for="filter_contractor" value="Contractor agency" />
                    <select id="filter_contractor" name="contractor_id" class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">All agencies</option>
                        @foreach ($contractors as $contractor)<option value="{{ $contractor->id }}" @selected((string) request('contractor_id') === (string) $contractor->id)>{{ $contractor->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="filter_active" value="Staff status" />
                    <select id="filter_active" name="is_active" class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">All staff</option>
                        <option value="1" @selected(request('is_active') === '1')>Active</option>
                        <option value="0" @selected(request('is_active') === '0')>Inactive</option>
                    </select>
                </div>
                <div>
                    <x-input-label for="filter_verification" value="Verification status" />
                    <x-text-input id="filter_verification" name="verification_status" :value="request('verification_status')" list="verification_options" maxlength="50" class="mt-1 w-full" />
                    <datalist id="verification_options"><option value="YES"><option value="NO"><option value="SLIP"><option value="UNDER PROCESS"></datalist>
                </div>
                <div class="flex items-end gap-3">
                    <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Filter</button>
                    <a href="{{ route('outsourced-staff.index') }}" class="py-2 text-sm font-semibold text-gray-600 dark:text-gray-300">Reset</a>
                </div>
            </form>
            <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-700">
                    <caption class="sr-only">Outsourced staff and their contractor agencies</caption>
                    <thead class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-gray-900 dark:text-gray-300"><tr>
                        @foreach (['Employee', 'Contractor agency', 'Designation', 'Entry permit', 'Verification', 'Status', 'Action'] as $heading)<th scope="col" class="px-4 py-3">{{ $heading }}</th>@endforeach
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                        @forelse ($staff as $member)
                            <tr>
                                <td class="px-4 py-3"><span class="block font-semibold">{{ $member->name }}</span><span class="text-xs text-gray-500 dark:text-gray-400">{{ $member->employee_id }}</span></td>
                                <td class="px-4 py-3">{{ $member->contractor->name }}</td>
                                <td class="px-4 py-3">{{ $member->designation ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $member->entry_permit_number ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $member->verification_status ?? 'Not recorded' }}</td>
                                <td class="px-4 py-3"><span @class(['inline-flex rounded-full px-2 py-1 text-xs font-semibold', 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' => $member->is_active, 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => ! $member->is_active])>{{ $member->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="px-4 py-3"><a href="{{ route('outsourced-staff.edit', $member) }}" class="font-semibold text-emerald-700 hover:underline dark:text-emerald-300" aria-label="Edit {{ $member->name }}">Edit</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">No outsourced staff found. Add a staff member or import your Excel list.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $staff->links() }}
            <details class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800" @if ($errors->has('file') || $errors->has('agency_map')) open @endif>
                <summary class="cursor-pointer font-semibold text-gray-900 dark:text-white">Import outsourced staff from Excel</summary>
                <form method="POST" action="{{ route('outsourced-staff.import') }}" enctype="multipart/form-data" class="mt-5 flex flex-col gap-5">
                    @csrf
                    <p class="text-sm text-gray-600 dark:text-gray-300">Use the original 13-column staff list (.xlsx, up to 10 MB and 10,000 rows). DATE, MONTH and YEAR become one date of birth. Existing employee IDs are skipped. Source category, gender and verification labels are preserved.</p>
                    <div>
                        <x-input-label for="staff_file" value="Excel workbook" required />
                        <input id="staff_file" type="file" name="file" accept=".xlsx" required class="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:font-semibold file:text-emerald-800 dark:text-gray-300">
                    </div>
                    <fieldset>
                        <legend class="text-sm font-semibold text-gray-900 dark:text-white">Match spreadsheet agencies to contractors</legend>
                        <div class="mt-3 grid gap-4 md:grid-cols-3">
                            @foreach (['JMD', 'NCES', 'SKM'] as $agency)
                                <div>
                                    <x-input-label :for="'agency_'.$agency" :value="$agency" />
                                    <select id="agency_{{ $agency }}" name="agency_map[{{ $agency }}]" class="mt-1 block w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        <option value="">Select contractor</option>
                                        @foreach ($contractors as $contractor)<option value="{{ $contractor->id }}" @selected((string) old('agency_map.'.$agency) === (string) $contractor->id)>{{ $contractor->name }}</option>@endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                    <div>
                        <x-input-label for="invalid_aadhaar" value="Invalid Aadhaar values" />
                        <select id="invalid_aadhaar" name="invalid_aadhaar" class="mt-1 block w-full rounded-md border-gray-300 text-sm sm:max-w-lg dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <option value="reject" @selected(old('invalid_aadhaar', 'reject') === 'reject')>Stop the import so the source can be corrected</option>
                            <option value="blank" @selected(old('invalid_aadhaar') === 'blank')>Import staff, leave invalid Aadhaar blank and report rows</option>
                        </select>
                    </div>
                    <button type="submit" class="self-start rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Import staff</button>
                </form>
            </details>
        </div>
    </div>
</x-app-layout>
