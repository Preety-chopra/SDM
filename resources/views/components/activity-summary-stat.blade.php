@props(['label', 'value', 'description', 'highlight' => false])

<div @class(['rounded-xl p-4', 'bg-emerald-50 dark:bg-emerald-950' => $highlight, 'bg-gray-50 dark:bg-gray-900' => ! $highlight])>
    <dt @class(['text-sm font-semibold', 'text-emerald-800 dark:text-emerald-200' => $highlight, 'text-gray-600 dark:text-gray-300' => ! $highlight])>{{ $label }}</dt>
    <dd @class(['mt-2 text-3xl font-bold tabular-nums', 'text-emerald-900 dark:text-emerald-100' => $highlight, 'text-gray-900 dark:text-white' => ! $highlight])>{{ number_format($value) }}</dd>
    <dd @class(['mt-1 text-xs', 'text-emerald-700 dark:text-emerald-300' => $highlight, 'text-gray-500 dark:text-gray-400' => ! $highlight])>{{ $description }}</dd>
</div>
