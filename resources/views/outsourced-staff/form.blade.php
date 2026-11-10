<x-app-layout :title="$staff->exists ? 'Edit outsourced staff' : 'Add outsourced staff'">
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $staff->exists ? 'Edit outsourced staff' : 'Add outsourced staff' }}</h2>
    </x-slot>
    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ $staff->exists ? route('outsourced-staff.update', $staff) : route('outsourced-staff.store') }}" class="flex flex-col gap-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                @csrf
                @if ($staff->exists) @method('PUT') @endif
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-outsourced-staff-field name="employee_id" label="Employee ID" :value="$staff->employee_id" :required="true" maxlength="50" />
                    <div>
                        <x-input-label for="contractor_id" value="Contractor agency" required />
                        <select id="contractor_id" name="contractor_id" required class="mt-1 block w-full rounded-md border-gray-300 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <option value="">Select contractor</option>
                            @foreach ($contractors as $contractor)
                                <option value="{{ $contractor->id }}" @selected((string) old('contractor_id', $staff->contractor_id ?? request('contractor_id')) === (string) $contractor->id)>{{ $contractor->name }}{{ $contractor->is_active ? '' : ' (inactive)' }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('contractor_id')" class="mt-1" />
                    </div>
                    <x-outsourced-staff-field name="name" label="Employee name" :value="$staff->name" :required="true" maxlength="255" />
                    <x-outsourced-staff-field name="father_name" label="Father’s name" :value="$staff->father_name" maxlength="255" />
                    <x-outsourced-staff-field name="date_of_birth" label="Date of birth" type="date" :value="$staff->date_of_birth?->format('Y-m-d')" />
                    <div>
                        <x-input-label for="aadhaar_number" :value="$staff->exists ? 'Replace Aadhaar number (optional)' : 'Aadhaar number (optional)'" />
                        <x-text-input id="aadhaar_number" name="aadhaar_number" type="password" inputmode="numeric" autocomplete="off" pattern="[0-9]{12}" maxlength="12" aria-describedby="aadhaar_help" class="mt-1 block w-full" />
                        <p id="aadhaar_help" class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                            @if ($staff->masked_aadhaar)
                                Saved: {{ $staff->masked_aadhaar }}. Leave blank to keep it.
                            @else
                                Enter 12 digits. The saved number is shown masked.
                            @endif
                        </p>
                        <x-input-error :messages="$errors->get('aadhaar_number')" class="mt-1" />
                        @if ($staff->masked_aadhaar)
                            <label class="mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" name="clear_aadhaar" value="1" @checked(old('clear_aadhaar')) class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">Clear saved Aadhaar</label>
                        @endif
                    </div>
                    <x-outsourced-staff-field name="category" label="Category" :value="$staff->category" maxlength="50" />
                    <x-outsourced-staff-field name="gender" label="Gender" :value="$staff->gender" maxlength="50" />
                    <x-outsourced-staff-field name="designation" label="Designation" :value="$staff->designation" maxlength="255" />
                    <x-outsourced-staff-field name="entry_permit_number" label="Entry permit card number" :value="$staff->entry_permit_number" maxlength="100" />
                    <x-outsourced-staff-field name="verification_status" label="Verification status (YES, NO, SLIP, UNDER PROCESS)" :value="$staff->verification_status" maxlength="50" />
                    <div class="flex items-center gap-2 sm:pt-6">
                        <input type="hidden" name="is_active" value="0">
                        <input id="is_active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $staff->exists ? $staff->is_active : true)) class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
                        <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300">Active staff member</label>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-4 border-t border-gray-200 pt-5 dark:border-gray-700">
                    <button type="submit" class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">{{ $staff->exists ? 'Save changes' : 'Add staff member' }}</button>
                    <a href="{{ route('outsourced-staff.index') }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
