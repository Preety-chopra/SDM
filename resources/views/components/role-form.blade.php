@props(['action', 'method' => 'POST', 'managedRole' => null, 'permissions', 'submitLabel'])

<form method="POST" action="{{ $action }}" class="flex flex-col gap-6">
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif

    <div>
        <x-input-label for="name" value="Role name" required />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $managedRole?->name)" required autofocus />
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Use lowercase letters, numbers, and underscores, for example quality_auditor.</p>
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <fieldset class="flex flex-col gap-3">
        <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">Existing permissions</legend>
        <p class="text-sm text-gray-500 dark:text-gray-400">Choose what users with this role are allowed to do.</p>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($permissions as $permission)
                <label class="flex items-start gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                    <input name="permissions[]" type="checkbox" value="{{ $permission->name }}" @checked(in_array($permission->name, old('permissions', $managedRole?->permissions->pluck('name')->all() ?? []), true)) class="mt-0.5 h-4 w-4 rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ \Illuminate\Support\Str::headline(str_replace('.', ' ', $permission->name)) }}</span>
                </label>
            @endforeach
        </div>
        <x-input-error :messages="$errors->get('permissions.*')" />
    </fieldset>

    <div>
        <x-input-label for="new_permissions" value="New permissions (optional)" />
        <textarea id="new_permissions" name="new_permissions" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="reports.view&#10;reports.export">{{ is_array(old('new_permissions')) ? implode("\n", old('new_permissions')) : old('new_permissions') }}</textarea>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Enter one permission per line using a stable name such as reports.view.</p>
        <x-input-error :messages="$errors->get('new_permissions.*')" class="mt-2" />
    </div>

    <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-700">
        <a href="{{ route('roles.index') }}" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
        <button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">{{ $submitLabel }}</button>
    </div>
</form>
