<x-app-layout title="Contract station mapping">
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Contract station mapping</h1>
            <a href="{{ route('contracts.index') }}" class="text-sm font-semibold text-emerald-700 hover:underline dark:text-emerald-300">Service contracts</a>
        </div>
    </x-slot>

    <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>
        @endif
        <x-input-error :messages="$errors->get('mapping')" role="alert" />

        <nav aria-label="Contract status" class="overflow-x-auto">
            <div class="inline-flex min-w-full gap-1 rounded-xl border border-gray-200 bg-gray-100 p-1 sm:min-w-0 dark:border-gray-700 dark:bg-gray-900">
                @foreach ($statusTabs as $tabStatus => $label)
                    <a
                        href="{{ route('contract-station-mapping.index', ['status' => $tabStatus, 'service_type' => $serviceType?->value, 'station_search' => $stationSearch]) }}"
                        @if ($status === $tabStatus) aria-current="page" @endif
                        @class([
                            'flex-1 whitespace-nowrap rounded-lg px-4 py-2.5 text-center text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 sm:flex-none dark:focus:ring-offset-gray-900',
                            'bg-white text-emerald-800 shadow-sm dark:bg-gray-800 dark:text-emerald-300' => $status === $tabStatus,
                            'text-gray-600 hover:bg-white/70 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-gray-800/70 dark:hover:text-white' => $status !== $tabStatus,
                        ])
                    >{{ $label }}</a>
                @endforeach
            </div>
        </nav>

        <form method="GET" role="search" aria-label="Contract station mapping" action="{{ route('contract-station-mapping.index') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="flex w-full flex-col gap-1 sm:w-64">
                <x-input-label for="station_search" value="Station name" />
                <x-text-input id="station_search" name="station_search" type="search" :value="$stationSearch" placeholder="Search by station name" class="w-full text-sm" />
            </div>
            <div class="flex w-full flex-col gap-1 sm:w-64">
                <x-input-label for="service_type" value="Service" />
                <select id="service_type" name="service_type" class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                    <option value="">All services</option>
                    @foreach ($serviceTypes as $type)
                        <option value="{{ $type->value }}" @selected($serviceType === $type)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-primary-button>Search</x-primary-button>
            @if ($serviceType || $stationSearch !== null)
                <a href="{{ route('contract-station-mapping.index', ['status' => $status]) }}" class="px-2 py-2 text-sm font-semibold text-gray-600 hover:underline dark:text-gray-300">Clear filters</a>
            @endif
        </form>

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <table class="w-full text-left text-sm text-gray-700 dark:text-gray-200">
                <caption class="sr-only">Station contracts and contractor agencies</caption>
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-900 dark:text-gray-300">
                    <tr>
                        <th scope="col" class="px-4 py-3">Station</th>
                        <th scope="col" class="px-4 py-3">Service</th>
                        <th scope="col" class="px-4 py-3">Contractor agency</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">From</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">To</th>
                        @if ($status === 'published')
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        @endif
                    </tr>
                </thead>
                @forelse ($stationGroups as $lineStations)
                    @php($lineCode = $lineStations->first()->metroLine?->code)
                    <tbody>
                        <tr>
                            <th colspan="{{ $status === 'published' ? 6 : 5 }}" class="border-t border-gray-200 bg-gray-100 px-4 py-2 font-semibold text-gray-900 dark:border-gray-700 dark:bg-gray-900/70 dark:text-white">
                                {{ $lineCode === null ? 'Unassigned line' : (ctype_digit($lineCode) ? 'Line '.$lineCode : $lineCode) }}
                            </th>
                        </tr>
                    </tbody>
                    @foreach ($lineStations as $station)
                        @php($stationContracts = $contractsByStation->get($station->id, []))
                        <tbody class="border-t border-gray-200 dark:border-gray-700">
                            @forelse ($stationContracts as $contract)
                                <tr class="align-top">
                                    @if ($loop->first)
                                        <th scope="rowgroup" rowspan="{{ $loop->count }}" class="min-w-40 px-4 py-3 text-left font-semibold text-gray-900 dark:text-white">
                                            <span class="block">{{ $station->name }}</span>
                                            <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">{{ $station->code }}</span>
                                        </th>
                                    @endif
                                    <td class="min-w-24 px-4 py-3">{{ $contract->service_type->label() }}</td>
                                    <td class="min-w-48 px-4 py-3">
                                        <span class="block font-medium text-gray-900 dark:text-white">{{ $contract->contractor->name }}</span>
                                        <a href="{{ route('contracts.show', $contract) }}" class="text-xs text-emerald-700 hover:underline dark:text-emerald-300">{{ $contract->contract_number }} · v{{ $contract->version }}</a>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 tabular-nums">{{ $contract->effective_from->format('d-m-Y') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 tabular-nums">{{ $contract->effective_to->format('d-m-Y') }}</td>
                                    @if ($status === 'published')
                                        <td class="whitespace-nowrap px-4 py-3 text-right">
                                            @if ($station->is_active && $station->metroLine?->is_active)
                                                <a href="{{ route('contract-station-mapping.edit', ['station' => $station, 'contract' => $contract, 'service_type' => $serviceType?->value, 'station_search' => $stationSearch, 'page' => $stations->currentPage() > 1 ? $stations->currentPage() : null]) }}" aria-label="Change contractor for {{ $station->name }}, {{ $contract->contract_number }}" class="font-semibold text-emerald-700 hover:underline dark:text-emerald-300">Change contractor</a>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <th scope="row" class="px-4 py-3 text-left font-semibold text-gray-900 dark:text-white">
                                        <span class="block">{{ $station->name }}</span>
                                        <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">{{ $station->code }}</span>
                                    </th>
                                    <td colspan="{{ $status === 'published' ? 5 : 4 }}" class="px-4 py-3 text-gray-500 dark:text-gray-400">No {{ $status }} contract mapped{{ $serviceType ? ' for this service' : '' }}.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    @endforeach
                @empty
                    <tbody><tr><td colspan="{{ $status === 'published' ? 6 : 5 }}" class="p-8 text-center text-gray-500 dark:text-gray-400">{{ $stationSearch === null ? 'No stations found.' : 'No stations match your search.' }}</td></tr></tbody>
                @endforelse
            </table>
        </div>

        {{ $stations->links() }}
    </div>
</x-app-layout>
