<x-app-layout title="Change contractor">
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Change contractor</h1>
    </x-slot>

    <div class="mx-auto flex max-w-3xl flex-col gap-4 px-4 py-8 sm:px-6 lg:px-8">
        <a href="{{ route('contract-station-mapping.index', $listQuery) }}" class="self-start text-sm font-semibold text-emerald-700 hover:underline dark:text-emerald-300">Back to contract station mapping</a>

        <div class="flex flex-col gap-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $station->name }} <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ $station->code }})</span></h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $contract->service_type->label() }}</p>
            </div>

            <dl class="grid gap-4 rounded-lg bg-gray-50 p-4 text-sm sm:grid-cols-2 dark:bg-gray-900">
                <div>
                    <dt class="font-medium text-gray-600 dark:text-gray-400">Current contractor</dt>
                    <dd class="text-gray-900 dark:text-white">{{ $contract->contractor->name }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-600 dark:text-gray-400">Current contract</dt>
                    <dd class="text-gray-900 dark:text-white">{{ $contract->contract_number }} · v{{ $contract->version }} · {{ ucfirst($contract->status) }}</dd>
                    <dd class="text-gray-600 dark:text-gray-300">{{ $contract->effective_from->format('d-m-Y') }} – {{ $contract->effective_to->format('d-m-Y') }}</dd>
                </div>
            </dl>

            @if ($replacements->isNotEmpty())
                <form method="POST" action="{{ route('contract-station-mapping.update', ['station' => $station, 'contract' => $contract, ...$listQuery]) }}" class="flex flex-col gap-4">
                    @csrf
                    @method('PATCH')
                    <div class="flex flex-col gap-1">
                        <x-input-label for="replacement_contract_id" value="Replacement contractor and contract" required />
                        <select id="replacement_contract_id" name="replacement_contract_id" aria-describedby="replacement-help @error('replacement_contract_id') replacement-error @enderror" @error('replacement_contract_id') aria-invalid="true" @enderror required class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                            <option value="">Select a contract</option>
                            @foreach ($replacements as $replacement)
                                <option value="{{ $replacement->id }}" @selected(old('replacement_contract_id') == $replacement->id)>{{ $replacement->contractor->name }} · {{ $replacement->contract_number }} (v{{ $replacement->version }}) · {{ $replacement->effective_from->format('d-m-Y') }} – {{ $replacement->effective_to->format('d-m-Y') }}</option>
                            @endforeach
                        </select>
                        <p id="replacement-help" class="text-sm text-gray-600 dark:text-gray-300">Saving moves this station to the selected contract.</p>
                        <x-input-error id="replacement-error" :messages="$errors->get('replacement_contract_id')" />
                    </div>
                    <div>
                        <x-primary-button>Save mapping</x-primary-button>
                    </div>
                </form>
            @else
                <x-input-error :messages="$errors->get('replacement_contract_id')" />
                <p class="text-sm text-gray-600 dark:text-gray-300">No other {{ strtolower($contract->status) }} contracts are available for this service.</p>
            @endif
        </div>
    </div>
</x-app-layout>
