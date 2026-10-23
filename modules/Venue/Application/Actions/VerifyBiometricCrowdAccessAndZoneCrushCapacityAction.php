<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Domain\Models\BiometricCredential;
use Modules\Venue\Domain\Models\CrowdSafetyTelemetry;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\PersonalizedPromo;
use RuntimeException;

class VerifyBiometricCrowdAccessAndZoneCrushCapacityAction extends BaseAction
{
    /**
     * Verify biometric Face-ID credential, analyze real-time crowd density, and enforce automatic gate lock.
     *
     * @return array{credential: BiometricCredential, telemetry: CrowdSafetyTelemetry, gate_restricted: bool, promo: ?PersonalizedPromo}
     */
    public function handle(
        int $userId,
        string $featureVector,
        string $livenessProof,
        int $zoneId,
        int $currentHeadcount,
        string $promoTitle = '',
        int $promoDiscountPct = 15
    ): array {
        return DB::transaction(function () use (
            $userId,
            $featureVector,
            $livenessProof,
            $zoneId,
            $currentHeadcount,
            $promoTitle,
            $promoDiscountPct
        ) {
            // 117.1 Irreversible cryptographic biometric Face-ID template hashing
            $templateHash = hash('sha256', "BIO-TEMPLATE-{$userId}-{$featureVector}");
            $livenessHash = hash('sha256', "LIVENESS-{$livenessProof}-".date('Y-m-d'));

            $credential = BiometricCredential::where('user_id', $userId)->first();
            if (! $credential) {
                $count = BiometricCredential::count() + 1;
                $credential = BiometricCredential::create([
                    'credential_code' => sprintf('BIO-%06d', $count),
                    'user_id' => $userId,
                    'biometric_template_hash' => $templateHash,
                    'liveness_signature_hash' => $livenessHash,
                    'is_active' => true,
                ]);
            } else {
                if (! $credential->is_active) {
                    throw new RuntimeException('Biometric access denied: Credential is not active');
                }
                if ($credential->biometric_template_hash !== $templateHash) {
                    throw new RuntimeException('Biometric match failed: Face-ID template mismatch');
                }
            }

            // 117.2 & 117.3 Real-time Zone Density & Predictive Crowd Crush Safety Lock
            $zone = EntertainmentZone::lockForUpdate()->findOrFail($zoneId);
            $limit = $zone->capacity_limit;
            $occupancyPercent = ($limit > 0) ? ($currentHeadcount / $limit) * 100 : 0;

            // Deterministic 15-minute predictive inflow trend (+10% during peak)
            $predicted15m = (int) round($currentHeadcount * 1.10);

            if ($predicted15m >= $limit) {
                $action = 'RESTRICT_ACCESS';
                $gateRestricted = true; // Automatic gate lockout
            } elseif ($predicted15m >= (int) round($limit * 0.85)) {
                $action = 'OPEN_SECONDARY_GATE';
                $gateRestricted = false;
            } else {
                $action = 'NORMAL_ENTRY';
                $gateRestricted = false;
            }

            $telemetryCount = CrowdSafetyTelemetry::count() + 1;
            $telemetry = CrowdSafetyTelemetry::create([
                'telemetry_code' => sprintf('CST-%06d', $telemetryCount),
                'zone_id' => $zone->id,
                'current_headcount' => $currentHeadcount,
                'density_capacity_limit' => $limit,
                'occupancy_percentage' => round($occupancyPercent, 2),
                'predicted_headcount_15m' => $predicted15m,
                'recommended_action' => $action,
                'gate_restricted' => $gateRestricted,
            ]);

            // 117.5 Personalized On-Site Promotion with Deduplication
            $promo = null;
            if (! empty($promoTitle)) {
                $existing = PersonalizedPromo::where('user_id', $userId)
                    ->where('zone_id', $zone->id)
                    ->where('promo_title', $promoTitle)
                    ->first();

                if (! $existing) {
                    $promoCount = PersonalizedPromo::count() + 1;
                    $promo = PersonalizedPromo::create([
                        'promo_dispatch_code' => sprintf('PRM-%06d', $promoCount),
                        'user_id' => $userId,
                        'zone_id' => $zone->id,
                        'promo_title' => $promoTitle,
                        'discount_percent' => $promoDiscountPct,
                        'expires_at' => date('Y-m-d H:i:s', strtotime('+4 hours')),
                        'status' => 'DISPATCHED',
                    ]);
                }
            }

            $this->audit(
                action: 'venue.crowd_biometric.verified',
                auditable: $telemetry,
                oldValues: null,
                newValues: [
                    'user_id' => $userId,
                    'zone_id' => $zone->id,
                    'occupancy_percentage' => $telemetry->occupancy_percentage,
                    'recommended_action' => $action,
                    'gate_restricted' => $gateRestricted,
                ],
                correlationId: "ven_crowd_{$telemetry->telemetry_code}",
                impactType: 'crowd_safety_biometric'
            );

            return [
                'credential' => $credential,
                'telemetry' => $telemetry,
                'gate_restricted' => $gateRestricted,
                'promo' => $promo,
            ];
        });
    }
}
