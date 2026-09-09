<x-app-layout title="Service contracts">
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Service contracts</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Versioned CFA and TVS penalty catalogs.</p>
            </div>
            <a href="{{ route('contracts.create') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-emerald-800">Create contract</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>
            @endif
            @forelse($contracts as $contract)
                <a href="{{ route('contracts.show', $contract) }}" class="grid gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 md:grid-cols-8 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-700">
                    <div class="md:col-span-2">
                        <p class="font-semibold text-gray-950 dark:text-white">{{ $contract->contract_number }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $contract->title }}</p>
                    </div>
                    <div><p class="text-xs uppercase text-gray-500">Service</p><p class="font-medium dark:text-white">{{ $contract->service_type->code() }}</p></div>
                    <div><p class="text-xs uppercase text-gray-500">Contractor</p><p class="font-medium dark:text-white">{{ $contract->contractor->name }}</p></div>
                    <div class="md:col-span-2"><p class="text-xs uppercase text-gray-500">Contract Tenure</p><p class="whitespace-nowrap font-medium dark:text-white">{{ $contract->effective_from->format('d-m-Y') }} to {{ $contract->effective_to->format('d-m-Y') }}</p></div>
                    <div><p class="text-xs uppercase text-gray-500">Version</p><p class="font-medium dark:text-white">{{ $contract->version }}</p></div>
                    <div><span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $contract->status === 'published' ? 'bg-emerald-100 text-emerald-800' : ($contract->status === 'retired' ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-800') }}">{{ str($contract->status)->headline() }}</span></div>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-gray-500 dark:border-gray-700 dark:bg-gray-800">No service contracts have been created.</div>
            @endforelse
            {{ $contracts->links() }}
        </div>
    </div>
</x-app-layout>
