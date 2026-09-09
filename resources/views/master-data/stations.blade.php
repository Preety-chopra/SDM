<x-app-layout title="Stations">
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Stations</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                    {{ session('status') }}
                </div>
            @endif

            <x-input-error :messages="$errors->all()" />

            <form method="POST" action="{{ route('stations.store') }}" class="grid gap-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm md:grid-cols-[1fr_2fr_2fr_auto_auto] dark:border-gray-700 dark:bg-gray-800">
                @csrf
                <x-text-input name="code" placeholder="Station code" required />
                <x-text-input name="name" placeholder="Station name" required />
                <select name="line_id" required class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
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

            <div class="flex flex-col gap-6">
                @forelse ($stationGroups as $stations)
                    @php($line = $stations->first()->metroLine)
                    <section data-line-code="{{ $line->code }}" class="overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 shadow-sm dark:border-gray-700 dark:bg-gray-950/40">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-white px-5 py-4 dark:border-gray-700 dark:bg-gray-800">
                            <div>
                                <h3 class="font-semibold text-gray-950 dark:text-white">{{ $line->name }}</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Line code: {{ $line->code }}</p>
                            </div>
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                                {{ $stations->count() }} {{ Str::plural('station', $stations->count()) }}
                            </span>
                        </div>

                        <div class="grid gap-4 p-4">
                            @foreach ($stations as $station)
                                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                                    <form method="POST" action="{{ route('stations.update', $station) }}" class="grid gap-4 md:grid-cols-[1fr_2fr_2fr_auto_auto]">
                                        @csrf
                                        @method('PUT')
                                        <x-text-input name="code" :value="$station->code" required />
                                        <x-text-input name="name" :value="$station->name" required />
                                        <select name="line_id" required class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                            @foreach ($lines as $selectableLine)
                                                <option value="{{ $selectableLine->id }}" @selected($station->line_id === $selectableLine->id)>
                                                    {{ $selectableLine->name }} ({{ $selectableLine->code }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input type="hidden" name="is_active" value="0">
                                            <input type="checkbox" name="is_active" value="1" @checked($station->is_active) class="rounded text-emerald-700">
                                            Active
                                        </label>
                                        <button class="rounded-lg border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950">Save</button>
                                    </form>
                                    <form method="POST" action="{{ route('stations.destroy', $station) }}" class="flex justify-end pt-3">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-sm font-semibold text-rose-700 hover:text-rose-800 dark:text-rose-400 dark:hover:text-rose-300">Delete</button>
                                    </form>
                                </div>
                            @endforeach
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
