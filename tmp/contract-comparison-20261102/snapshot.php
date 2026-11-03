<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
DB::statement('START TRANSACTION READ ONLY');
try {
    $snapshot = ['captured_at' => now()->toIso8601String()];
    foreach (['service_contracts', 'service_contract_station', 'service_contract_line', 'stations', 'lines'] as $table) {
        $snapshot[$table] = DB::table($table)->get()->all();
    }
    $snapshot['contractors'] = DB::table('contractors')->get(['id', 'name', 'code', 'is_active'])->all();
    file_put_contents(__DIR__.'/database.json', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(array_map(fn ($rows) => is_array($rows) ? count($rows) : $rows, $snapshot), JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    DB::rollBack();
}
