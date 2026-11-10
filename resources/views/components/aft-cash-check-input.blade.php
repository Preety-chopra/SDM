@props(['field', 'label', 'type' => 'text', 'money' => false, 'maxLength' => 100])
<div>
    <label :for="'cash-' + row._key + '-{{ $field }}'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }} <span aria-hidden="true" class="font-bold text-red-600 dark:text-red-400">*</span><span class="sr-only"> (required on submission)</span></label>
    <input
        :id="'cash-' + row._key + '-{{ $field }}'"
        :name="'entries[' + index + '][{{ $field }}]'"
        x-model="row.{{ $field }}"
        type="{{ $type }}"
        @if($money) min="0" step="0.01" inputmode="decimal" max="999999999999.99" @endif
        @if($type === 'text') maxlength="{{ $maxLength }}" @endif
        @if($type === 'date') max="{{ now()->toDateString() }}" @endif
        @if($type === 'time') step="60" @endif
        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
    >
</div>
