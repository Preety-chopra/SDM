@props(['memo'])

<details class="mt-5 rounded-xl border border-amber-300 bg-white p-4 dark:border-amber-800 dark:bg-gray-900" @if(old('action') === 'return') open @endif>
    <summary class="cursor-pointer text-sm font-semibold text-amber-800 dark:text-amber-300">Revert to SM</summary>
    <form method="POST" action="{{ route('sdm-memos.workflow.store', $memo) }}" class="mt-4 flex flex-col gap-3">
        @csrf
        <input type="hidden" name="action" value="return">
        <p class="text-sm text-gray-600 dark:text-gray-300">Return this SDM to the Station Manager who sent it for correction.</p>
        <div>
            <x-input-label for="revert_remarks" value="Revert remark (mandatory)" required />
            <textarea id="revert_remarks" name="remarks" rows="3" maxlength="5000" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">{{ old('action') === 'return' ? old('remarks') : '' }}</textarea>
            <x-input-error :messages="old('action') === 'return' ? $errors->get('remarks') : []" class="mt-2" />
        </div>
        <button class="self-end rounded-lg bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">Revert to SM</button>
    </form>
</details>
