@props(['formKey', 'value' => null])

<div class="md:col-span-2">
    <input type="hidden" name="contractor_form" value="{{ $formKey }}">
    <x-input-label for="contractor_address_{{ $formKey }}" value="Address (optional)" />
    <textarea id="contractor_address_{{ $formKey }}" name="address" rows="3" maxlength="2000" autocomplete="street-address" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600">{{ (string) old('contractor_form') === (string) $formKey ? old('address', $value) : $value }}</textarea>
    @if((string) old('contractor_form') === (string) $formKey)
        <x-input-error :messages="$errors->get('address')" class="mt-2" />
    @endif
</div>
