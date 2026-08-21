<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">{{ $generalCreate ? 'Create user' : 'Create '.\App\Support\RoleLabel::singular($selectedRole->name) }}</h2></x-slot>
    <div class="py-10"><div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8"><div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8 dark:border-gray-700 dark:bg-gray-800"><x-user-form :action="route('users.store')" :selected-role="$selectedRole" :roles="$roles" :lines="$lines" :stations="$stations" :managed-station-ids="[]" submit-label="Create user" /></div></div></div>
</x-app-layout>
