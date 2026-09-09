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
                        <form id="line-{{ $line->id }}" method="POST" action="{{ route('lines.update',$line) }}" class="grid gap-4 md:grid-cols-[1fr_2fr_auto_auto]">@csrf @method('PUT')
                            <x-text-input name="code" :value="$line->code" required/>
                            <x-text-input name="name" :value="$line->name" required/>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked($line->is_active) class="rounded text-emerald-700">Active
                            </label>
                            <button class="rounded-lg border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-700">Save</button>
                        </form>
                        <form method="POST" action="{{ route('lines.destroy',$line) }}" class="mt-3 text-right">
                            @csrf 
                            @method('DELETE')
                            <button class="text-sm font-semibold text-rose-700">Delete</button>
                        </form>
                    </div>
                @endforeach
            </div>
            <div class="flex gap-4 text-sm">
                <a class="text-emerald-700" href="{{ route('stations.index') }}">Stations</a>
                <a class="text-emerald-700" href="{{ route('contractors.index') }}">Contractors</a>
            </div>
        </div>
    </div>
</x-app-layout>
