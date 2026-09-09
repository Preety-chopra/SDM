@props(['value', 'required' => false])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-gray-700 dark:text-gray-300']) }}>
    {{ $value ?? $slot }}
    @if($required)
        <span data-required-marker aria-hidden="true" class="font-bold !text-red-600 dark:!text-red-400">*</span>
        <span class="sr-only"> (required)</span>
    @endif
</label>
