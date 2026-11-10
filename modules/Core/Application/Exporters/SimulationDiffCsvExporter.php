<?php

declare(strict_types=1);

namespace Modules\Core\Application\Exporters;

use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\ValueObjects\Simulation\DivergencePoint;

final class SimulationDiffCsvExporter
{
    /**
     * @param  resource  $handle
     */
    public function export($handle, SimRun $runA, SimRun $runB, DivergencePoint $diff): void
    {
        fputcsv($handle, [
            'Properti',
            'Sesi A ('.$runA->name.')',
            'Sesi B ('.$runB->name.')',
            'Kesesuaian',
        ]);

        fputcsv($handle, ['Seed', $runA->seed, $runB->seed, $runA->seed === $runB->seed ? 'MATCH' : 'DIVERGED']);
        fputcsv($handle, ['Mode', $runA->mode, $runB->mode, $runA->mode === $runB->mode ? 'MATCH' : 'DIVERGED']);
        fputcsv($handle, ['Total Hari', $runA->total_days, $runB->total_days, $runA->total_days === $runB->total_days ? 'MATCH' : 'DIFFERENT']);
        fputcsv($handle, ['Fingerprint Rantai', $runA->chain_hash, $runB->chain_hash, $runA->chain_hash === $runB->chain_hash ? 'MATCH' : 'DIVERGED']);
        fputcsv($handle, ['Status Divergensi', $diff->hasDiverged ? 'DIVERGED' : 'IDENTIK', '', '']);
        fputcsv($handle, ['Hari Pertama Divergensi', $diff->divergenceDay ?? '-', '', '']);
        fputcsv($handle, ['Alasan', $diff->reason, '', '']);

        fputcsv($handle, []);
        fputcsv($handle, ['Handler Name', 'Output Hash A', 'Output Hash B', 'Identical']);

        foreach ($diff->handlerDiscrepancies as $handlerName => $info) {
            fputcsv($handle, [
                $handlerName,
                is_scalar($info['a']) ? (string) $info['a'] : json_encode($info['a']),
                is_scalar($info['b']) ? (string) $info['b'] : json_encode($info['b']),
                $info['identical'] ? 'MATCH' : 'DIFFERENT',
            ]);
        }
    }
}
