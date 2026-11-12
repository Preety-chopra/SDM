<?php

return [
    // Laravel's proxy middleware reads this list at request time.
    'proxies' => array_values(array_filter(array_map(
        'trim', explode(',', (string) env('TRUSTED_PROXIES', '')),
    ))),
];
