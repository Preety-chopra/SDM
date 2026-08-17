@props(['action', 'method' => 'POST', 'managedUser' => null, 'selectedRole', 'roles', 'stations', 'submitLabel'])

<form method="POST" action="{{ $action }}" class="flex flex-col gap-6">
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <x-input-label for="name" value="Username" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $managedUser?->name)" required autofocus autocomplete="username" />
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Use a person's name, station name, or role name such as administrator.</p>
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="email" value="Email address" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $managedUser?->email)" required />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="role" value="Role" />
            <select id="role" name="role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" required>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $selectedRole->value) === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="station_id" value="Station (required for Station roles)" />
            <select id="station_id" name="station_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                <option value="">Select a station</option>
                @foreach ($stations as $station)
                    <option value="{{ $station->id }}" @selected((string) old('station_id', $managedUser?->station_id) === (string) $station->id)>{{ $station->code }} · {{ $station->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('station_id')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="employee_number" value="Employee number (required for managers)" />
            <x-text-input id="employee_number" name="employee_number" type="text" class="mt-1 block w-full" :value="old('employee_number', $managedUser?->employee_number)" />
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
            <label for="is_enabled" class="text-sm font-medium text-gray-700 dark:text-gray-300">User is enabled</label>
            <x-input-error :messages="$errors->get('is_enabled')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password" :value="$managedUser ? 'New password (optional)' : 'Password'" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" :required="! $managedUser" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password_confirmation" value="Confirm password" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" :required="! $managedUser" autocomplete="new-password" />
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-700">
        <a href="{{ route('users.index', $selectedRole->value) }}" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
        <button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">{{ $submitLabel }}</button>
    </div>
</form>
