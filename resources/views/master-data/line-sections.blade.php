<x-app-layout :title="'Sections · '.$line->code">
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Sections · {{ $line->code }} · {{ $line->name }}</h1>
    </x-slot>
    <div class="py-8">
        <div class="mx-auto flex max-w-6xl flex-col gap-5 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('lines.index') }}" class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">Back to lines</a>
            @if(session('status'))
                <p role="status" class="rounded-xl bg-emerald-50 p-4 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>
            @endif
            <x-input-error :messages="$errors->all()" />
            @if($line->is_active && !$line->merged_into_line_id)
                @php($currentSections = old('sections', $sections->whereNull('retired_at')->map->only(['code', 'name', 'station_ids'])->values()->all()))
                <form method="POST" action="{{ route('lines.sections.update', $line) }}" x-data="lineSectionsForm" data-stations="{{ $stations->map->only(['id', 'code', 'name', 'sequence_no', 'is_active'])->values()->toJson() }}" data-sections="{{ json_encode(is_array($currentSections) ? $currentSections : []) }}" class="flex flex-col gap-5">
                    @csrf @method('PUT')
                    <input type="hidden" name="sections" value="">
                    <div class="grid gap-3 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-sm text-gray-600 dark:text-gray-300">Add sections such as 6N / Line-6N and 6S / Line-6S. Assign every station to exactly one section, including inactive stations. Select an inclusive station range or choose stations individually. Changes apply when you save; existing SDMs keep their recorded line details.</p>
                        <p class="text-sm font-medium dark:text-gray-200" x-text="sections.length ? assignedCount() + ' of ' + stations.length + ' stations assigned' : 'Whole line · no sections'"></p>
                        <div class="flex flex-wrap gap-3">
                            <button type="button" @click="addSection()" class="rounded-lg border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-700 dark:text-emerald-400">Add section</button>
                            <button type="button" @click="sections = []" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold dark:border-gray-600 dark:text-gray-200">Merge all sections into whole line</button>
                        </div>
                    </div>
                    <template x-for="(section, index) in sections" :key="index">
                        <fieldset class="grid gap-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                            <legend class="px-2 font-semibold dark:text-white" x-text="section.name || 'New section'"></legend>
                            <input type="hidden" :name="'sections[' + index + '][selection_mode]'" value="stations">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="grid gap-1 text-sm font-medium dark:text-gray-200">Section code
                                    <input :name="'sections[' + index + '][code]'" x-model="section.code" maxlength="30" placeholder="6N" required class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                </label>
                                <label class="grid gap-1 text-sm font-medium dark:text-gray-200">Section name
                                    <input :name="'sections[' + index + '][name]'" x-model="section.name" maxlength="255" placeholder="Line-6N" required class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                </label>
                                <label class="grid gap-1 text-sm font-medium dark:text-gray-200">From station
                                    <select x-model="section.from_station_id" @change="selectRange(section)" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                        <option value="">Choose station</option>
                                        <template x-for="station in rangeStations()" :key="station.id"><option :value="String(station.id)" x-text="station.sequence_no + ' · ' + station.name"></option></template>
                                    </select>
                                </label>
                                <label class="grid gap-1 text-sm font-medium dark:text-gray-200">To station
                                    <select x-model="section.to_station_id" @change="selectRange(section)" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                        <option value="">Choose station</option>
                                        <template x-for="station in rangeStations()" :key="station.id"><option :value="String(station.id)" x-text="station.sequence_no + ' · ' + station.name"></option></template>
                                    </select>
                                </label>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Both ends are included. You can adjust the checkboxes after choosing a range. Stations without a sequence number must be selected individually.</p>
                            <div class="grid max-h-80 gap-2 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">
                                <template x-for="station in stations" :key="station.id">
                                    <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700 dark:text-gray-200">
                                        <input type="checkbox" :name="'sections[' + index + '][station_ids][]'" :value="String(station.id)" x-model="section.station_ids" class="rounded border-gray-300 text-emerald-700">
                                        <span x-text="station.code + ' · ' + station.name + (station.is_active ? '' : ' (inactive)')"></span>
                                    </label>
                                </template>
                            </div>
                            <button type="button" @click="removeSection(index)" class="justify-self-start text-sm font-semibold text-rose-700 dark:text-rose-400">Remove section</button>
                        </fieldset>
                    </template>
                    <button disabled :disabled="!ready" class="justify-self-start rounded-lg bg-emerald-700 px-5 py-2 font-semibold text-white disabled:opacity-50">Save section configuration</button>
                </form>
            @else
                <p class="text-sm text-gray-600 dark:text-gray-300">This line is inactive or merged. Previous section definitions are retained below.</p>
            @endif
            @if($sections->isNotEmpty())
                <section class="grid gap-3 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="text-lg font-semibold dark:text-white">Section history</h2>
                    @foreach($sections as $section)
                        <div class="grid gap-1 border-t border-gray-100 pt-3 text-sm dark:border-gray-700 dark:text-gray-200">
                            <p class="font-semibold">{{ $section->code }} · {{ $section->name }} · {{ count($section->station_ids) }} stations</p>
                            <p class="text-gray-500 dark:text-gray-400">{{ $section->effective_from->format('d-m-Y H:i') }} → {{ $section->retired_at?->format('d-m-Y H:i') ?? 'Current' }}</p>
                        </div>
                    @endforeach
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
