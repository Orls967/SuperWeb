<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Pipeline;

final class ProductionReadinessReviewer
{
    /**
     * Conducts standard Production Readiness Review (PRR) checklist inspection.
     *
     * @param  array{monitoring_configured: bool, alerts_defined: bool, playbook_documented: bool, capacity_verified: bool, rollback_rehearsed: bool}  $checklist
     * @return array{is_ready: bool, readiness_score_pct: float, missing_items: list<string>}
     */
    public function reviewReadiness(array $checklist): array
    {
        $missing = [];
        $totalItems = 5;
        $passedItems = 0;

        if (! ($checklist['monitoring_configured'] ?? false)) {
            $missing[] = 'Dasbor metrik telemetri belum dikonfigurasi.';
        } else {
            $passedItems++;
        }

        if (! ($checklist['alerts_defined'] ?? false)) {
            $missing[] = 'Peringatan ambang batas SLO belum didaftarkan di SreAlertRouter.';
        } else {
            $passedItems++;
        }

        if (! ($checklist['playbook_documented'] ?? false)) {
            $missing[] = 'Buku panduan tanggap insiden (Incident Playbook) belum dipublikasikan.';
        } else {
            $passedItems++;
        }

        if (! ($checklist['capacity_verified'] ?? false)) {
            $missing[] = 'Uji kapasitas beban lalu lintas puncak belum divalidasi.';
        } else {
            $passedItems++;
        }

        if (! ($checklist['rollback_rehearsed'] ?? false)) {
            $missing[] = 'Prosedur rollback pemulihan belum diuji coba pada lingkungan staging.';
        } else {
            $passedItems++;
        }

        $readinessScore = round(($passedItems / $totalItems) * 100.0, 1);
        $isReady = ($missing === []);

        return [
            'is_ready' => $isReady,
            'readiness_score_pct' => $readinessScore,
            'missing_items' => $missing,
        ];
    }
}
