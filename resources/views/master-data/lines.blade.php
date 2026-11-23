<x-app-layout title="Lines">
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Lines</h2>
    </x-slot>
    <div class="py-10">
        <div class="mx-auto flex max-w-5xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>
            @endif
            <x-input-error :messages="$errors->all()" />
            <p class="text-sm text-gray-600 dark:text-gray-300">Divide lines into editable sections or merge lines below. Existing SDMs retain their recorded line details.</p>
            <form method="POST" action="{{ route('lines.store') }}" class="grid gap-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm md:grid-cols-[1fr_2fr_auto_auto] dark:border-gray-700 dark:bg-gray-800">
                @csrf
                <x-text-input name="code" placeholder="Line code" required/>
                <x-text-input name="name" placeholder="Line name" required/>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded text-emerald-700">Active
                </label>
                <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Add line</button>
            </form>
            <div class="grid gap-4">
                @foreach($lines as $line)
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        @if($line->merged_into_line_id)
                            <p class="font-semibold text-gray-900 dark:text-white">{{ $line->code }} · {{ $line->name }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Merged into {{ $lines->firstWhere('id', $line->merged_into_line_id)?->code }}. Retained for history.</p>
                        @else
                        <form id="line-{{ $line->id }}" method="POST" action="{{ route('lines.update',$line) }}" class="grid gap-4 md:grid-cols-[1fr_2fr_auto_auto]">@csrf @method('PUT')
                            <x-text-input name="code" :value="$line->code" required/>
                            <x-text-input name="name" :value="$line->name" required/>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked($line->is_active) class="rounded text-emerald-700">Active
                            </label>
                            <button class="rounded-lg border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-700">Save</button>
                        </form>
                        <a href="{{ route('lines.sections.edit', $line) }}" class="mt-3 inline-block text-sm font-semibold text-emerald-700 dark:text-emerald-400">Manage sections</a>
                        <form method="POST" action="{{ route('lines.destroy',$line) }}" class="mt-3 text-right">
                            @csrf 
                            @method('DELETE')
                            <button class="text-sm font-semibold text-rose-700">Delete</button>
                        </form>
                        @endif
                    </div>
                @endforeach
            </div>
            <form method="POST" action="{{ route('lines.merge') }}" class="grid gap-4 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                @csrf
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Merge lines</h3>
                <p class="text-sm text-gray-600 dark:text-gray-300">Select at least two active lines and choose the resulting line. All their stations will belong to that line. Current sections will end; you can define new sections after merging. Previous line records and contract coverage are preserved.</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach($lines->where('is_active', true)->whereNull('merged_into_line_id') as $line)
                        <label class="flex items-center gap-2 text-sm dark:text-gray-200"><input type="checkbox" name="line_ids[]" value="{{ $line->id }}" @checked(in_array($line->id, old('line_ids', []))) class="rounded border-gray-300 text-emerald-700">{{ $line->code }} · {{ $line->name }}</label>
                    @endforeach
                </div>
                <label class="grid gap-1 text-sm font-medium dark:text-gray-200">Resulting line
                    <select name="target_line_id" required class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                        <option value="">Choose one of the selected lines</option>
                        @foreach($lines->where('is_active', true)->whereNull('merged_into_line_id') as $line)
                            <option value="{{ $line->id }}" @selected((string) old('target_line_id') === (string) $line->id)>{{ $line->code }} · {{ $line->name }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="justify-self-start rounded-lg bg-emerald-700 px-4 py-2 font-semibold text-white">Merge selected lines</button>
            </form>
            <div class="flex gap-4 text-sm">
                <a class="text-emerald-700" href="{{ route('stations.index') }}">Stations</a>
                <a class="text-emerald-700" href="{{ route('contractors.index') }}">Contractors</a>
            </div>
        </div>
    </div>
</x-app-layout>
