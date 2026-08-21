<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">Create role</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create a new user type and assign its permissions.</p>
        </div>
    </x-slot>
    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-8">
                <x-role-form :action="route('roles.store')" :permissions="$permissions" submit-label="Create role" />
            </div>
        </div>
    </div>
</x-app-layout>
