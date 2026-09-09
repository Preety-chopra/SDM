<x-app-layout :title="'Edit '.\App\Support\RoleLabel::singular($managedRole->name)">
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">Edit {{ \App\Support\RoleLabel::singular($managedRole->name) }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update the role name and assigned permissions.</p>
        </div>
    </x-slot>
    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-8">
                <x-role-form :action="route('roles.update', $managedRole)" method="PUT" :managed-role="$managedRole" :permissions="$permissions" submit-label="Save role" />
            </div>
        </div>
    </div>
</x-app-layout>
