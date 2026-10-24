<x-app-layout title="Designations">
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Designations</h2></x-slot>

    <div class="py-10">
        <div class="mx-auto grid max-w-5xl gap-6 px-4 sm:px-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:px-8">
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900"><tr><th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Designation</th><th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th><th class="px-5 py-3"></th></tr></thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($designations as $designation)
                            <tr>
                                <td class="px-5 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $designation->name }} ({{ $designation->code }})</td>
                                <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $designation->is_active ? 'Active' : 'Inactive' }}</td>
                                <td class="px-5 py-4 text-right"><form method="POST" action="{{ route('designations.destroy', $designation) }}">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-700 hover:underline">Delete</button></form></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add designation</h3>
                <form method="POST" action="{{ route('designations.store') }}" class="mt-5 grid gap-4">
                    @csrf
                    <div><x-input-label for="code" value="Code" required/><x-text-input id="code" name="code" class="mt-1 block w-full" :value="old('code')" required/><x-input-error :messages="$errors->get('code')" class="mt-2"/></div>
                    <div><x-input-label for="name" value="Name" required/><x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required/><x-input-error :messages="$errors->get('name')" class="mt-2"/></div>
                    <input type="hidden" name="is_active" value="1">
                    <button class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Add designation</button>
                </form>
                <x-input-error :messages="$errors->get('designation')" class="mt-4"/>
            </section>
        </div>
    </div>
</x-app-layout>
