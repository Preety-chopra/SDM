@props(['name', 'label', 'value' => null, 'type' => 'text', 'required' => false, 'maxlength' => null])

<div>
    <x-input-label :for="$name" :value="$label" :required="$required" />
    <x-text-input :id="$name" :name="$name" :type="$type" :value="old($name, $value)" :required="$required" :maxlength="$maxlength" class="mt-1 block w-full" />
    <x-input-error :messages="$errors->get($name)" class="mt-1" />
</div>
