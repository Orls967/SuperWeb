<?php

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\BiometricCredential;
use Modules\Venue\Domain\Models\CrowdSafetyTelemetry;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\PersonalizedPromo;
use RuntimeException;

class VenueCrowdAndBiometricService
{
    /**
     * 117.1 Register Biometric Face-ID Template (Privacy-compliant irreversible hash)
     */
    public function registerBiometricCredential(
        int $userId,
        string $rawBiometricFeatureVector,
        string $livenessProof
    ): BiometricCredential {
        // Irreversible cryptographic hashing of biometric template (Rule 117.6 a)
        $templateHash = hash('sha256', "BIO-TEMPLATE-{$userId}-{$rawBiometricFeatureVector}");
        $livenessHash = hash('sha256', "LIVENESS-{$livenessProof}-".now()->toDateString());

        return BiometricCredential::create([
            'credential_code' => 'BIO-'.strtoupper(bin2hex(random_bytes(4))),
            'user_id' => $userId,
            'biometric_template_hash' => $templateHash,
            'liveness_signature_hash' => $livenessHash,
            'is_active' => true,
        ]);
    }

    /**
     * 117.1 Verify Face-ID Door Entry
     */
    public function verifyBiometricDoorEntry(
        BiometricCredential $credential,
        string $scannedFeatureVector,
        string $scannedLivenessProof
    ): bool {
        if (! $credential->is_active) {
            throw new RuntimeException('Biometric access denied: Credential is not active');
        }

        $incomingTemplateHash = hash('sha256', "BIO-TEMPLATE-{$credential->user_id}-{$scannedFeatureVector}");
        if ($incomingTemplateHash !== $credential->biometric_template_hash) {
            throw new RuntimeException('Biometric match failed: Face-ID template mismatch');
        }

        return true;
    }

    /**
     * 117.2 & 117.3 Compute Zone Density & Crowd Safety Predictive Telemetry
     */
    public function analyzeZoneCrowdSafety(EntertainmentZone $zone, int $currentHeadcount): CrowdSafetyTelemetry
    {
        $limit = $zone->capacity_limit;
        $occupancyPercent = ($limit > 0) ? ($currentHeadcount / $limit) * 100 : 0;

        // Predict headcount in 15 minutes: deterministic trend +10% inflow during peak
        $predicted15m = (int) round($currentHeadcount * 1.10);

        if ($predicted15m >= $limit) {
            $action = 'RESTRICT_ACCESS';
            $gateRestricted = true;
        } elseif ($predicted15m >= ($limit * 0.85)) {
            $action = 'OPEN_SECONDARY_GATE';
            $gateRestricted = false;
        } else {
            $action = 'NORMAL_ENTRY';
            $gateRestricted = false;
        }

        return CrowdSafetyTelemetry::create([
            'telemetry_code' => 'CST-'.strtoupper(bin2hex(random_bytes(4))),
            'zone_id' => $zone->id,
            'current_headcount' => $currentHeadcount,
            'density_capacity_limit' => $limit,
            'occupancy_percentage' => round($occupancyPercent, 2),
            'predicted_headcount_15m' => $predicted15m,
            'recommended_action' => $action,
            'gate_restricted' => $gateRestricted,
        ]);
    }

    /**
     * 117.5 & 117.6 (d) Dispatch Personalized In-Venue Promotion with Deduplication
     */
    public function sendPersonalizedPromo(
        int $userId,
        EntertainmentZone $zone,
        string $promoTitle,
        int $discountPercent
    ): PersonalizedPromo {
        // Deduplicate per user, zone, and title
        $existing = PersonalizedPromo::where('user_id', $userId)
            ->where('zone_id', $zone->id)
            ->where('promo_title', $promoTitle)
            ->first();

        if ($existing) {
            return $existing; // Return existing without duplicate dispatch
        }

        return PersonalizedPromo::create([
            'promo_dispatch_code' => 'PRM-'.strtoupper(bin2hex(random_bytes(4))),
            'user_id' => $userId,
            'zone_id' => $zone->id,
            'promo_title' => $promoTitle,
            'discount_percent' => $discountPercent,
            'expires_at' => now()->addHours(2),
            'status' => 'DELIVERED',
        ]);
    }
}
