<x-app-layout :title="$contract->contract_number.' · v'.$contract->version">
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $contract->contract_number }} · v{{ $contract->version }}</h1><p class="text-sm text-gray-500 dark:text-gray-400">{{ $contract->title }}</p></div>
            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $contract->status === 'published' ? 'bg-emerald-100 text-emerald-800' : ($contract->status === 'retired' ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-800') }}">{{ str($contract->status)->headline() }}</span>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
            @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>@endif
            @if($errors->any())
                <div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
                    <p class="font-semibold">The contract could not be published:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <section class="grid gap-5 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm md:grid-cols-3 dark:border-gray-700 dark:bg-gray-800">
                @foreach(['Service'=>$contract->service_type->label(),'Contractor'=>$contract->contractor->name,'Lines'=>$contract->lines->map(fn ($line) => $line->code.' · '.$line->name)->join(', ') ?: 'Not mapped','Stations'=>$contract->stations->map(fn ($station) => $station->code.' · '.$station->name)->join(', ') ?: 'Not mapped','Effective from'=>$contract->effective_from->format('d-m-Y'),'Effective to'=>$contract->effective_to->format('d-m-Y'),'Referenced SDMs'=>$contract->memos_count] as $label=>$value)<div><p class="text-xs font-semibold uppercase text-gray-500">{{ $label }}</p><p class="mt-1 text-gray-950 dark:text-white">{{ $value }}</p></div>@endforeach
                @if($contract->source_document_path)<a href="{{ route('contracts.source', $contract) }}" class="text-sm font-semibold text-emerald-700 hover:underline dark:text-emerald-400">Download private source contract PDF</a>@endif
            </section>
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 p-5 dark:border-gray-700"><h2 class="font-semibold text-gray-950 dark:text-white">Penalty clause catalog</h2></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"><thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900"><tr><th class="px-4 py-3">S/N</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">Basis</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Cap</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-700">@foreach($contract->penaltyClauses as $clause)<tr><td class="px-4 py-3 font-semibold dark:text-white">{{ $clause->serial_number }}</td><td class="px-4 py-3 dark:text-gray-200">{{ $clause->description }}</td><td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $clause->calculation_type->label() }}</td><td class="px-4 py-3 dark:text-white">{{ $clause->maximum_amount ?? $clause->base_amount ?? 'Authority decision' }}</td><td class="px-4 py-3">{{ $clause->cap_applicable ? 'Yes' : 'No' }}</td></tr>@endforeach</tbody></table></div>
            </section>
            <div class="flex flex-wrap justify-end gap-3">
                @if($contract->status === 'draft')<a href="{{ route('contracts.edit', $contract) }}" class="rounded-lg border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-700">Edit draft</a><form method="POST" action="{{ route('contracts.publish', $contract) }}">@csrf<button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Publish version</button></form>@if($contract->memos_count === 0)<form method="POST" action="{{ route('contracts.destroy', $contract) }}">@csrf @method('DELETE')<button class="rounded-lg border border-rose-700 px-4 py-2 text-sm font-semibold text-rose-700">Delete draft</button></form>@endif @else<form method="POST" action="{{ route('contracts.revise', $contract) }}">@csrf<button class="rounded-lg border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-700">Create revision</button></form>@endif
                @if($contract->status === 'published')<form method="POST" action="{{ route('contracts.retire', $contract) }}">@csrf<button class="rounded-lg border border-rose-700 px-4 py-2 text-sm font-semibold text-rose-700">Retire</button></form>@endif
            </div>
        </div>
    </div>
</x-app-layout>
