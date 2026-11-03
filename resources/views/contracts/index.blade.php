<x-app-layout title="Service contracts">
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Service contracts</h1>
                {{-- <p class="text-sm text-gray-500 dark:text-gray-400">Versioned CFA and TVS penalty catalogs.</p> --}}
            </div>
            <a href="{{ route('contracts.create') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-emerald-800">Create contract</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>
            @endif
            <nav aria-label="Contract status" class="overflow-x-auto">
                <div class="inline-flex min-w-full gap-1 rounded-xl border border-gray-200 bg-gray-100 p-1 sm:min-w-0 dark:border-gray-700 dark:bg-gray-900">
                    @foreach ($statusTabs as $tabStatus => $label)
                        <a
                            href="{{ route('contracts.index', ['status' => $tabStatus, 'service_type' => $serviceType?->value]) }}"
                            @if($status === $tabStatus) aria-current="page" @endif
                            @class([
                                'flex-1 whitespace-nowrap rounded-lg px-4 py-2.5 text-center text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 sm:flex-none dark:focus:ring-offset-gray-900',
                                'bg-white text-emerald-800 shadow-sm dark:bg-gray-800 dark:text-emerald-300' => $status === $tabStatus,
                                'text-gray-600 hover:bg-white/70 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-gray-800/70 dark:hover:text-white' => $status !== $tabStatus,
                            ])
                        >{{ $label }}</a>
                    @endforeach
                </div>
            </nav>
            <form method="GET" action="{{ route('contracts.index') }}" class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:flex-row sm:items-end dark:border-gray-700 dark:bg-gray-800">
                <input type="hidden" name="status" value="{{ $status }}">
                <div class="w-full sm:max-w-md">
                    <label for="service_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Service</label>
                    <select id="service_type" name="service_type" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">All services</option>
                        @foreach ($serviceTypes as $type)
                            <option value="{{ $type->value }}" @selected($serviceType === $type)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">Apply filter</button>
                @if ($serviceType)
                    <a href="{{ route('contracts.index', ['status' => $status]) }}" class="rounded-lg px-4 py-2 text-center text-sm font-semibold text-gray-600 hover:text-gray-950 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:text-gray-300 dark:hover:text-white dark:focus:ring-offset-gray-800">Clear filter</a>
                @endif
            </form>
            @forelse($contracts as $contract)
                <a href="{{ route('contracts.show', $contract) }}" class="grid gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 md:grid-cols-8 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-700">
                    <div class="md:col-span-2">
                        <p class="font-semibold text-gray-950 dark:text-white">{{ $contract->contract_number }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $contract->title }}</p>
                    </div>
                    <div><p class="text-xs uppercase text-gray-500">Service</p><p class="font-medium dark:text-white">{{ $contract->service_type->code() }}</p></div>
                    <div><p class="text-xs uppercase text-gray-500">Contractor</p><p class="font-medium dark:text-white">{{ $contract->contractor->name }}</p></div>
                    <div class="md:col-span-2"><p class="text-xs uppercase text-gray-500">Contract Tenure</p><p class="whitespace-nowrap font-medium dark:text-white">{{ $contract->effective_from->format('d-m-Y') }} – {{ $contract->effective_to->format('d-m-Y') }}</p></div>
                    <div><p class="text-xs uppercase text-gray-500">Version</p><p class="font-medium dark:text-white">{{ $contract->version }}</p></div>
                    <div><span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $contract->status === 'published' ? 'bg-emerald-100 text-emerald-800' : ($contract->status === 'retired' ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-800') }}">{{ str($contract->status)->headline() }}</span></div>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-gray-500 dark:border-gray-700 dark:bg-gray-800">No {{ $status }} contracts found{{ $serviceType ? ' for '.$serviceType->label() : '' }}.</div>
            @endforelse
            {{ $contracts->links() }}
        </div>
    </div>
</x-app-layout>
