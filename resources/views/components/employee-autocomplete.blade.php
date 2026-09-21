@props([
    'name',
    'label',
    'candidates' => collect(),
    'value' => '',
])

@php
    $options = collect($candidates)->map(fn ($user) => [
        'employee_number' => $user->employee_number,
        'name' => $user->name,
    ])->values();
@endphp

<div
    data-employee-autocomplete
    data-candidates="{{ $options->toJson() }}"
    data-initial-value="{{ $value }}"
    x-data="employeeAutocomplete"
    @click.outside="close"
    class="relative"
>
    <x-input-label :for="$name" :value="$label" required/>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="text"
        x-model="query"
        @input="open = true"
        @focus="open = true"
        @keydown.escape="close"
        autocomplete="off"
        role="combobox"
        aria-autocomplete="list"
        :aria-expanded="open"
        aria-controls="{{ $name }}-suggestions"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
    />
    <div
        id="{{ $name }}-suggestions"
        x-cloak
        x-show="open"
        class="absolute z-30 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-gray-200 bg-white py-1 shadow-xl dark:border-gray-700 dark:bg-gray-900"
        role="listbox"
    >
        @foreach($options as $option)
            <button
                type="button"
                data-employee-number="{{ $option['employee_number'] }}"
                data-employee-name="{{ $option['name'] }}"
                x-show="matches($el.dataset.employeeNumber, $el.dataset.employeeName)"
                @click="select($el.dataset.employeeNumber)"
                class="block w-full px-3 py-2 text-left text-sm text-gray-800 hover:bg-emerald-50 focus:bg-emerald-50 focus:outline-none dark:text-gray-100 dark:hover:bg-emerald-950 dark:focus:bg-emerald-950"
                role="option"
            >{{ $option['employee_number'] }} - {{ $option['name'] }}</button>
        @endforeach
        <p x-show="filteredCandidates.length === 0" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">No eligible active employee found.</p>
    </div>
</div>
