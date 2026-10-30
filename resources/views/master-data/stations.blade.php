<x-app-layout title="Stations">
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Stations</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                    {{ session('status') }}
                </div>
            @endif

            <x-input-error :messages="$errors->all()" />

            <form method="POST" action="{{ route('stations.store') }}" class="grid items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-[1fr_2fr_2fr_auto_auto] dark:border-gray-700 dark:bg-gray-800">
                @csrf
                <x-text-input name="code" placeholder="Station code" aria-label="New station code" class="min-w-0 px-2 py-1.5 text-sm" required />
                <x-text-input name="name" placeholder="Station name" aria-label="New station name" class="min-w-0 px-2 py-1.5 text-sm" required />
                <select name="line_id" aria-label="New station line" required class="min-w-0 rounded-md border-gray-300 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    <option value="">Select line</option>
                    @foreach ($lines as $line)
                        <option value="{{ $line->id }}">{{ $line->name }} ({{ $line->code }})</option>
                    @endforeach
                </select>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded text-emerald-700">
                    Active
                </label>
                <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Add</button>
            </form>

            <div class="flex flex-col gap-4">
                @forelse ($stationGroups as $stations)
                    @php($line = $stations->first()->metroLine)
                    <section data-line-code="{{ $line->code }}" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-900/50">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 id="station-line-{{ $line->id }}" class="text-sm font-semibold text-gray-950 dark:text-white">{{ $line->name }}</h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Line code: {{ $line->code }}</span>
                            </div>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                                {{ $stations->count() }} {{ Str::plural('station', $stations->count()) }}
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table aria-labelledby="station-line-{{ $line->id }}" class="w-full min-w-[48rem] table-fixed text-left text-sm">
                                <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-gray-900/30 dark:text-gray-400">
                                    <tr>
                                        <th scope="col" class="w-12 px-3 py-2">Seq.</th>
                                        <th scope="col" class="w-24 px-2 py-2">Code</th>
                                        <th scope="col" class="px-2 py-2">Station name</th>
                                        <th scope="col" class="w-44 px-2 py-2">Line</th>
                                        <th scope="col" class="w-16 px-2 py-2 text-center">Active</th>
                                        <th scope="col" class="w-32 px-3 py-2 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($stations as $station)
                                        <tr data-station-id="{{ $station->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                            <td class="px-3 py-2 tabular-nums text-gray-500 dark:text-gray-400">{{ $station->sequence_no ?? '—' }}</td>
                                            <td class="px-2 py-2">
                                                <x-text-input form="station-update-{{ $station->id }}" name="code" :value="$station->code" aria-label="Station code for {{ $station->name }}" class="w-full px-2 py-1 text-sm" required />
                                            </td>
                                            <td class="px-2 py-2">
                                                <x-text-input form="station-update-{{ $station->id }}" name="name" :value="$station->name" aria-label="Station name for {{ $station->code }}" class="w-full px-2 py-1 text-sm" required />
                                            </td>
                                            <td class="px-2 py-2">
                                                <select form="station-update-{{ $station->id }}" name="line_id" aria-label="Line for {{ $station->name }}" required class="w-full rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                                    @foreach ($lines as $selectableLine)
                                                        <option value="{{ $selectableLine->id }}" @selected($station->line_id === $selectableLine->id)>
                                                            {{ $selectableLine->name }} ({{ $selectableLine->code }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="px-2 py-2 text-center">
                                                <input form="station-update-{{ $station->id }}" type="hidden" name="is_active" value="0">
                                                <input form="station-update-{{ $station->id }}" type="checkbox" name="is_active" value="1" aria-label="Active status for {{ $station->name }}" @checked($station->is_active) class="rounded text-emerald-700 focus:ring-emerald-500">
                                            </td>
                                            <td class="px-3 py-2">
                                                <div class="flex items-center justify-end gap-3">
                                                    <form id="station-update-{{ $station->id }}" method="POST" action="{{ route('stations.update', $station) }}">
                                                        @csrf
                                                        @method('PUT')
                                                        <button aria-label="Save {{ $station->name }}" class="rounded-md border border-emerald-700 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:text-emerald-300 dark:hover:bg-emerald-950">Save</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('stations.destroy', $station) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button aria-label="Delete {{ $station->name }}" class="rounded text-xs font-semibold text-rose-700 hover:text-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-500 dark:text-rose-400 dark:hover:text-rose-300">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                        No stations have been added yet.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
