<?php

declare(strict_types=1);

namespace Modules\Venue\Console\Commands;

use Illuminate\Console\Command;
use Modules\Venue\Domain\Algorithms\Biometric\BiometricLivenessMatcherEngine;
use Modules\Venue\Domain\Algorithms\CrowdSafety\CrowdDensitySurgePredictor;
use Modules\Venue\Domain\Algorithms\Experience\WaitPerServiceGovernor;
use Modules\Venue\Domain\Algorithms\Personalization\PersonalizedOfferContextRouter;
use Modules\Venue\Domain\Models\BiometricCredential;
use Modules\Venue\Domain\Models\EntertainmentZone;

class AuditVenueCrowdCommand extends Command
{
    protected $signature = 'venue:audit-crowd';

    protected $aliases = ['ven:audit-crowd'];

    protected $description = 'Audit mandiri keselamatan kerumunan, prediksi lonjakan massa 15 menit, verifikasi biometrik satu arah, dan skor pengalaman tamu WPS';

    public function handle(
        BiometricLivenessMatcherEngine $livenessEngine,
        CrowdDensitySurgePredictor $surgePredictor,
        WaitPerServiceGovernor $wpsGovernor,
        PersonalizedOfferContextRouter $offerRouter
    ): int {
        $this->info('Starting Venue Crowd Safety, Biometric & Guest Experience Audit (venue:audit-crowd)...');

        $discrepancies = 0;
        $auditRows = [];

        // 1. Audit Verifikasi Algoritma V13 Biometric Liveness & One-Way Hash
        try {
            $bioCert = $livenessEngine->match(
                userId: 901,
                featureVector: 'FEAT-AUDIT-TEST-001',
                challengeProof: 'CHALLENGE-BLINK-SUCCESS',
                saltSecret: 'AUDIT_SALT_SECRET'
            );

            if (! $bioCert->isMatched || $bioCert->livenessScoreBps < 8500) {
                $discrepancies++;
                $auditRows[] = ['V13 Biometric Engine', 'FAIL', 'Skor liveness atau pencocokan tidak valid'];
            } else {
                $auditRows[] = ['V13 Biometric Engine', 'PASS', sprintf('Liveness: %d bps, Hash: Valid', $bioCert->livenessScoreBps)];
            }
        } catch (\Throwable $e) {
            $discrepancies++;
            $auditRows[] = ['V13 Biometric Engine', 'FAIL', $e->getMessage()];
        }

        // 2. Audit Verifikasi Algoritma V14 Crowd Density Surge Predictor
        $surgeResult = $surgePredictor->predictSurge(
            zoneId: 1,
            currentHeadcount: 450,
            zoneAreaSqm: 100,
            legalCapacityLimit: 500,
            inflowVelocityPerMinute: 10,
            outflowVelocityPerMinute: 2,
            weatherFactorBps: 10000,
            saltSecret: 'AUDIT_SALT_SECRET'
        );

        if (! $surgeResult->gateRestricted || $surgeResult->recommendedAction !== 'RESTRICT_ACCESS') {
            $discrepancies++;
            $auditRows[] = ['V14 Crowd Surge Predictor', 'FAIL', 'Gagal memicu pembatasan gerbang otomatis'];
        } else {
            $auditRows[] = ['V14 Crowd Surge Predictor', 'PASS', sprintf('Prediksi: %d, Aksi: %s', $surgeResult->predictedHeadcount15m, $surgeResult->recommendedAction)];
        }

        // 3. Audit Verifikasi Algoritma V15 Wait Per Service (WPS) Governor
        $wpsResult = $wpsGovernor->calculateWps(
            stationId: 1,
            activeServersCount: 3,
            arrivalRatePerMinuteBps: 8000,
            serviceRatePerMinuteBps: 2000,
            maxTolerableWaitMinutes: 15,
            saltSecret: 'AUDIT_SALT_SECRET'
        );

        if (! $wpsResult->reinforcementNeeded || $wpsResult->wpsScore >= 60) {
            $discrepancies++;
            $auditRows[] = ['V15 WPS Governor', 'FAIL', 'Gagal mendeteksi antrean jenuh stasiun'];
        } else {
            $auditRows[] = ['V15 WPS Governor', 'PASS', sprintf('Skor WPS: %d, Bantuan: Perlu', $wpsResult->wpsScore)];
        }

        // 4. Audit Verifikasi Algoritma V16 Personalized Offer Context Router
        $offerResult = $offerRouter->evaluateOffer(
            userId: 902,
            currentZoneId: 1,
            membershipTierCode: 'VIP_PLATINUM',
            baseItemPriceIdr: 2000000,
            itemCogsFloorPriceIdr: 1600000,
            lastOfferTimestamp: time() - 4000,
            offersSentTonightCount: 1,
            currentTimestamp: time(),
            saltSecret: 'AUDIT_SALT_SECRET'
        );

        if (! $offerResult->isEligible || ! $offerResult->floorGuardrailTriggered || $offerResult->discountedPriceIdr !== 1600000) {
            $discrepancies++;
            $auditRows[] = ['V16 Personalized Router', 'FAIL', 'Gagal menerapkan floor guardrail margin COGS'];
        } else {
            $auditRows[] = ['V16 Personalized Router', 'PASS', sprintf('Diskon: %d bps, Harga: Rp %d (Floor Active)', $offerResult->discountBps, $offerResult->discountedPriceIdr)];
        }

        // 5. Audit Integritas Data Basis Data Kerumunan & Kredensial Biometrik
        $zones = EntertainmentZone::query()->get();
        foreach ($zones as $zone) {
            if ($zone->capacity_limit > 0 && $zone->current_occupancy > ($zone->capacity_limit * 1.5)) {
                $discrepancies++;
                $auditRows[] = [
                    'Integritas Okupansi Zona',
                    'FAIL',
                    sprintf('Zona [%s] mengalami kelebihan muatan fatal: %d / %d', $zone->zone_code, $zone->current_occupancy, $zone->capacity_limit),
                ];
            }
        }

        // 6. Cek Kredensial Biometrik Tanpa Persetujuan (Unconsented Biometrics)
        $unconsented = BiometricCredential::whereNull('consented_at')->count();
        if ($unconsented > 0) {
            $discrepancies++;
            $auditRows[] = [
                'Integritas Privasi Biometrik',
                'FAIL',
                sprintf('Ditemukan %d kredensial biometrik tanpa rekaman persetujuan eksplisit (Pelanggaran UU PDP/GDPR)', $unconsented),
            ];
        }

        $this->table(['Komponen Audit', 'Status', 'Catatan Evaluasi'], $auditRows);

        if ($discrepancies > 0) {
            $this->error(sprintf('AUDIT KESELAMATAN KERUMUNAN VENUE GAGAL: Terdeteksi %d inkonsistensi.', $discrepancies));

            return 1;
        }

        $this->info('Audit venue:audit-crowd SUCCESSFUL (0 discrepancies found).');

        return 0;
    }
}
