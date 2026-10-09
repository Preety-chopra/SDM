@props([
    'modalName',
    'title',
    'description',
    'action',
    'fieldName',
    'fieldLabel',
    'candidates',
    'showTrigger' => true,
])

@if($showTrigger)
    <div class="flex justify-end">
        <button
            type="button"
            x-on:click="$dispatch('open-modal', '{{ $modalName }}')"
            aria-haspopup="dialog"
            aria-controls="{{ $modalName }}"
            class="rounded-lg bg-amber-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
        >Handover SDM</button>
    </div>
@endif

<x-modal :name="$modalName" :show="$errors->has($fieldName)" max-width="lg" focusable>
    <section
        id="{{ $modalName }}"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $modalName }}-title"
        aria-describedby="{{ $modalName }}-description"
        class="p-6 sm:p-7"
    >
        <h2 id="{{ $modalName }}-title" class="text-lg font-semibold text-gray-950 dark:text-white">{{ $title }}</h2>
        <p id="{{ $modalName }}-description" class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $description }}</p>

        <form method="POST" action="{{ $action }}" class="mt-5 flex flex-col gap-4">
            @csrf
            @method('PATCH')
            <x-employee-autocomplete
                :name="$fieldName"
                :label="$fieldLabel"
                :candidates="$candidates"
                :value="old($fieldName)"
            />
            <x-input-error :messages="$errors->get($fieldName)"/>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    x-on:click="$dispatch('close-modal', '{{ $modalName }}')"
                    class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:ring-offset-gray-800"
                >Close</button>
                <button type="submit" class="rounded-lg bg-amber-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">Handover SDM</button>
            </div>
        </form>
    </section>
</x-modal>
