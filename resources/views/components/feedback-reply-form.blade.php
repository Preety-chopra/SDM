@props(['action', 'label', 'help'])

<form method="POST" action="{{ $action }}" class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
    @csrf
    <label for="feedback-reply" class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $label }}</label>
    <p id="feedback-reply-help" class="text-sm text-gray-600 dark:text-gray-300">{{ $help }}</p>
    <textarea id="feedback-reply" name="reply" rows="5" maxlength="5000" required aria-describedby="feedback-reply-help{{ $errors->has('reply') ? ' feedback-reply-error' : '' }}" @error('reply') aria-invalid="true" @enderror class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">{{ is_string(old('reply')) ? old('reply') : '' }}</textarea>
    <x-input-error id="feedback-reply-error" :messages="$errors->get('reply')" />
    <button type="submit" class="self-end rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">Send reply</button>
</form>
