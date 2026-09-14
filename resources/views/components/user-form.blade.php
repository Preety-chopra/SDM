@props(['action', 'method' => 'POST', 'managedUser' => null, 'selectedRole', 'roles', 'lines', 'stations', 'directStationRoleNames', 'submitLabel'])

<form method="POST" action="{{ $action }}" class="flex flex-col gap-6" x-data="userForm" data-role="{{ old('role', $selectedRole->name) }}" data-direct-station-roles='@json($directStationRoleNames)' data-station-role="{{ \App\Enums\UserRole::Station->value }}">
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <x-input-label for="name" value="Username" required />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $managedUser?->name)" required autofocus autocomplete="username" />
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Use a person's name, station name, or role name such as administrator.</p>
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="email" value="Email address" required />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $managedUser?->email)" required />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="role" value="Role" required />
            <select id="role" name="role" x-model="role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" required>
                @foreach ($roles as $role)
                    <option value="{{ $role->name }}" @selected(old('role', $selectedRole->name) === $role->name)>{{ \App\Support\RoleLabel::singular($role->name) }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="line_id" value="Line" />
            <select id="line_id" name="line_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                <option value="">Select a line</option>
                @foreach ($lines as $line)
                    <option value="{{ $line->id }}" @selected((string) old('line_id', $managedUser?->station?->line_id) === (string) $line->id)>{{ $line->code }} · {{ $line->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('line_id')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="station_id" value="Station" />
            <select id="station_id" name="station_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                <option value="">Select a station</option>
                @foreach ($stations as $station)
                    <option value="{{ $station->id }}" @selected((string) old('station_id', $managedUser?->station_id) === (string) $station->id)>{{ $station->metroLine->code }} · {{ $station->code }} · {{ $station->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('station_id')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="employee_number">Employee number <span x-show="role !== stationRole" data-required-marker aria-hidden="true" class="font-bold !text-red-600 dark:!text-red-400">*</span><span x-show="role !== stationRole" class="sr-only"> (required)</span></x-input-label>
            <x-text-input id="employee_number" name="employee_number" type="text" class="mt-1 block w-full" :value="old('employee_number', $managedUser?->employee_number)" x-bind:required="role !== stationRole" />
            <x-input-error :messages="$errors->get('employee_number')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="phone" value="Phone" />
            <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $managedUser?->phone)" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="designation" value="Designation" />
            <x-text-input id="designation" name="designation" type="text" class="mt-1 block w-full" :value="old('designation', $managedUser?->designation)" />
            <x-input-error :messages="$errors->get('designation')" class="mt-2" />
        </div>
        <div class="flex items-center gap-3 self-end pb-2">
            <input name="is_enabled" type="hidden" value="0">
            <input id="is_enabled" name="is_enabled" type="checkbox" value="1" @checked(old('is_enabled', $managedUser?->is_enabled ?? true)) class="h-4 w-4 rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
            <label for="is_enabled" class="text-sm font-medium text-gray-700 dark:text-gray-300">User is enabled <span data-required-marker aria-hidden="true" class="font-bold !text-red-600 dark:!text-red-400">*</span><span class="sr-only"> (required)</span></label>
            <x-input-error :messages="$errors->get('is_enabled')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password" :value="$managedUser ? 'New password (optional)' : 'Password'" :required="! $managedUser" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" :required="! $managedUser" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password_confirmation" value="Confirm password" :required="! $managedUser" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" :required="! $managedUser" autocomplete="new-password" />
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-700">
        <a href="{{ route('users.index', $selectedRole->name) }}" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
        <button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">{{ $submitLabel }}</button>
    </div>
</form>
