@props(['row' => [], 'chart' => false])

@foreach(\App\Support\CmcSdmTableRow::ASSESSMENT_COLUMNS as $field => $label)
    <td @class([
        'px-5 py-4 align-top text-sm text-gray-600 dark:text-gray-300',
        'whitespace-nowrap text-right tabular-nums' => $field !== 'proposed_action',
        'min-w-32 max-w-xs whitespace-pre-wrap break-words text-left' => $field === 'proposed_action',
    ]) @if($chart) x-text="memo.{{ $field }} ?? '—'" @endif>{{ $chart ? '' : ($row[$field] ?? '—') }}</td>
@endforeach
