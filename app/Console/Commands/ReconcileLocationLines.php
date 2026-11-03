<?php

namespace App\Console\Commands;

use App\Services\LocationLineReconciliationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('locations:reconcile-lines {--apply : Apply the previewed mappings and audit all changes}')]
#[Description('Preview or reconcile legacy line references using matching station codes from the seed snapshot')]
class ReconcileLocationLines extends Command
{
    public function handle(LocationLineReconciliationService $reconciliation): int
    {
        try {
            $result = $reconciliation->reconcile((bool) $this->option('apply'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Server code', 'Server name', 'Snapshot code', 'Snapshot name', 'Stations'], $result['mappings']);
        if ($result['unmapped'] !== []) {
            $this->warn('No matching snapshot stations; lines left unchanged: '.implode(', ', $result['unmapped']));
        }

        $this->info("Affected: {$result['stations']} stations, {$result['contracts']} contracts.");
        if (! $this->option('apply')) {
            $this->info('Preview only. No writes made. Review the mappings, then run with --apply during a maintenance window.');
        } else {
            $this->info('Reconciliation complete. Existing station and contract IDs preserved; legacy line rows retained.');
        }

        return self::SUCCESS;
    }
}
