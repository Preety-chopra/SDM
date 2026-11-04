<x-guest-layout>
    <div class="flex flex-col gap-4 py-4 text-gray-900 dark:text-gray-100">
        <h1 class="text-xl font-bold">SDM action could not be completed</h1>
        <p class="text-sm leading-6">{{ $message }}</p>
        <a href="{{ route('sdm-memos.index') }}" class="self-start rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">Return to SDMs</a>
    </div>
    <x-sdm-action-feedback :messages="[$message]" type="error" />
</x-guest-layout>
