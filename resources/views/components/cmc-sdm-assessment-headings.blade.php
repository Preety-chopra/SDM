@foreach(\App\Support\CmcSdmTableRow::ASSESSMENT_COLUMNS as $field => $label)
    <th scope="col" @class([
        'min-w-32 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400',
        'text-right' => $field !== 'proposed_action',
        'text-left' => $field === 'proposed_action',
    ])>{{ $label }}</th>
@endforeach
