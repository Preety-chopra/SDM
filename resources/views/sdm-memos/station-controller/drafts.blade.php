<x-app-layout title="SDM Drafts">
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Drafts</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Open a draft to continue editing or submit it.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</div>
            @endif

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                @foreach (['S. No.', 'SDM type', 'Deficiency', 'Last saved', 'Actions'] as $heading)
                                    <th class="whitespace-nowrap px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($memos as $memo)
                                <tr>
                                    <td class="px-5 py-4 text-sm font-semibold text-gray-900 dark:text-white">{{ $memos->firstItem() + $loop->index }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-gray-200">{{ $memo->service_type === \App\Enums\SdmType::Marshall ? 'Marshal' : $memo->service_type->code() }}</td>
                                    <td class="min-w-64 px-5 py-4 text-sm text-gray-700 dark:text-gray-200">{{ $memo->deficiencies->map(fn ($deficiency) => $deficiency->type->label())->join(', ') ?: '—' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-gray-200">{{ $memo->updated_at?->format('d-m-Y H:i') ?? '—' }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center justify-end gap-4">
                                            <a href="{{ route('sdm-memos.edit', $memo) }}" class="font-semibold text-emerald-700 hover:underline dark:text-emerald-400">Open</a>
                                            <form method="POST" action="{{ route('sdm-memos.destroy', $memo) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="font-semibold text-rose-700 hover:underline dark:text-rose-400">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-12 text-center text-gray-500 dark:text-gray-400">You do not have any drafts.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($memos->hasPages())
                <div class="mt-5">{{ $memos->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
