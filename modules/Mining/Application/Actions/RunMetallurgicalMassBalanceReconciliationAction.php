<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Enums\ReconciliationRunStatus;
use Modules\Mining\Domain\Models\MiningReconciliationRun;
use Modules\Mining\Domain\StateMachines\ReconciliationRunStateMachine;
use Modules\Shared\Domain\Algorithms\Metallurgy\MassBalanceReconciler;
use Modules\Shared\Domain\Algorithms\Metallurgy\MassBalanceReconcilerCertificateVerifier;

class RunMetallurgicalMassBalanceReconciliationAction
{
    /**
     * Jalankan rekonsiliasi neraca massa metalurgi 3 titik (Feed, Konsentrat, Tailing)
     * berbasis algoritma bersertifikat M5 (D94-2, D94-4).
     *
     * @param array{
     *     site_id: string,
     *     period_month: string,
     *     feed_metal_kg: int,
     *     concentrate_metal_kg: int,
     *     tailings_metal_kg: int,
     *     feed_variance_weight?: int,
     *     concentrate_variance_weight?: int,
     *     tailings_variance_weight?: int,
     *     tolerance_kg?: int
     * } $data
     * @return array{
     *     reconciliation_id: string,
     *     run_number: string,
     *     site_id: string,
     *     period_month: string,
     *     reconciled_feed_kg: int,
     *     reconciled_concentrate_kg: int,
     *     reconciled_tailings_kg: int,
     *     recovery_bps: int,
     *     discrepancy_kg: int,
     *     status: string
     * }
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $siteId = (string) $data['site_id'];
            $periodMonth = (string) $data['period_month'];

            $reconciler = new MassBalanceReconciler;
            $result = $reconciler->reconcile(
                feedKg: (int) $data['feed_metal_kg'],
                concentrateKg: (int) $data['concentrate_metal_kg'],
                tailingKg: (int) $data['tailings_metal_kg'],
                variances: [
                    'feed' => (int) ($data['feed_variance_weight'] ?? 4),
                    'concentrate' => (int) ($data['concentrate_variance_weight'] ?? 1),
                    'tailing' => (int) ($data['tailings_variance_weight'] ?? 3),
                ],
                toleranceKg: (int) ($data['tolerance_kg'] ?? 100)
            );

            // Verifikasi sertifikat bukti matematis
            $verifier = new MassBalanceReconcilerCertificateVerifier;
            if (! $verifier->verify($result)) {
                throw new \DomainException('Sertifikat rekonsiliasi neraca massa metalurgi M5 tidak valid atau korup.');
            }

            $runNumber = 'MREC-'.$periodMonth.'-'.strtoupper(Str::random(6));

            $initialStatus = ReconciliationRunStatus::OPEN;
            $run = MiningReconciliationRun::create([
                'id' => (string) Str::uuid(),
                'run_number' => $runNumber,
                'site_id' => $siteId,
                'period_month' => $periodMonth,
                'status' => $initialStatus,
                'total_ore_mined_tons' => (int) round($result->reconciledFeedKg / 1000),
                'total_stockpile_tons' => (int) round($result->reconciledConcentrateKg / 1000),
                'total_shipped_tons' => (int) round($result->reconciledTailingsKg / 1000),
                'variance_tons' => (int) round($result->discrepancyKg / 1000),
                'variance_bps' => (int) round(abs($result->discrepancyKg) * 10000 / max(1, $result->feedMetalKg)),
                'investigation_notes' => 'Rekonsiliasi M5 neraca massa: diskrepansi '.$result->discrepancyKg.' kg (toleransi '.$result->toleranceKg.' kg). Recovery '.$result->recoveryBps.' bps.',
            ]);

            $sm = new ReconciliationRunStateMachine($run);
            $sm->transitionTo(ReconciliationRunStatus::COLLECTING, 'Mengumpulkan data assay lab');
            $sm->transitionTo(ReconciliationRunStatus::BALANCING, 'Menjalankan optimasi bobot varians M5');

            if ($result->isBalanced) {
                $sm->transitionTo(ReconciliationRunStatus::APPROVED, 'Neraca massa seimbang dalam batas toleransi');
            } else {
                $sm->transitionTo(ReconciliationRunStatus::VARIANCE_DETECTED, 'Diskrepansi neraca massa melebihi ambang batas toleransi');
            }

            return [
                'reconciliation_id' => (string) $run->id,
                'run_number' => $runNumber,
                'site_id' => $siteId,
                'period_month' => $periodMonth,
                'reconciled_feed_kg' => $result->reconciledFeedKg,
                'reconciled_concentrate_kg' => $result->reconciledConcentrateKg,
                'reconciled_tailings_kg' => $result->reconciledTailingsKg,
                'recovery_bps' => $result->recoveryBps,
                'discrepancy_kg' => $result->discrepancyKg,
                'status' => $run->status instanceof \BackedEnum ? $run->status->value : (string) $run->status,
            ];
        });
    }
}
