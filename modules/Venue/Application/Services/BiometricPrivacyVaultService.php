<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\Carbon;
use Modules\Venue\Domain\Algorithms\Biometric\BiometricLivenessMatcherEngine;
use Modules\Venue\Domain\DTOs\BiometricScanResultDTO;
use Modules\Venue\Domain\Models\BiometricCredential;
use Modules\Venue\Domain\StateMachines\BiometricEnrolmentStateMachine;
use Modules\Venue\Domain\ValueObjects\BiometricTemplateDigest;

/**
 * Layanan Vault Privasi Biometrik (Privacy-Preserving Biometric Vault).
 * Mengelola pendaftaran kredensial satu arah, verifikasi pintu masuk gerbang turnstile,
 * perlindungan anti-spoofing liveness, serta pemenuhan hak pencabutan (Right to Revoke).
 */
final class BiometricPrivacyVaultService
{
    private const string VAULT_SALT_KEY = 'VENUE_PRIVACY_VAULT_KEY_2026';

    public function __construct(
        private readonly BiometricLivenessMatcherEngine $livenessEngine,
        private readonly BiometricEnrolmentStateMachine $stateMachine
    ) {}

    /**
     * Daftarkan templat biometrik baru dengan enkripsi satu arah bergaram privat.
     */
    public function enrolGuestBiometrics(
        int $userId,
        string $rawFeatureVector,
        string $challengeProof,
        string $sensorModel = 'FACECAM-3D-PRO'
    ): BiometricCredential {
        // 1. Eksekusi verifikasi liveness challenge
        $cert = $this->livenessEngine->match(
            userId: $userId,
            featureVector: $rawFeatureVector,
            challengeProof: $challengeProof,
            saltSecret: self::VAULT_SALT_KEY,
            minLivenessThresholdBps: 8500
        );

        // 2. Bungkus dalam BiometricTemplateDigest
        $digest = BiometricTemplateDigest::createFromFeatureVector(
            userId: $userId,
            rawVectorInMemory: $rawFeatureVector,
            saltSecret: self::VAULT_SALT_KEY,
            saltKeyId: 'K-2026-PRIMARY',
            livenessScoreBps: $cert->livenessScoreBps,
            sensorModel: $sensorModel
        );

        // 3. Matikan / Cabut templat aktif sebelumnya milik user jika ada
        BiometricCredential::where('user_id', $userId)
            ->where('status', BiometricEnrolmentStateMachine::STATUS_ENROLLED_ACTIVE)
            ->update([
                'status' => BiometricEnrolmentStateMachine::STATUS_REVOKED,
                'is_active' => false,
                'revoked_at' => Carbon::now(),
            ]);

        $credentialCode = sprintf(
            'BIO-%d-%s',
            $userId,
            substr(hash_hmac('sha256', (string) $userId, self::VAULT_SALT_KEY), 0, 8)
        );

        return BiometricCredential::create([
            'credential_code' => $credentialCode,
            'user_id' => $userId,
            'biometric_template_hash' => $digest->templateHash,
            'liveness_signature_hash' => $cert->certificateSignature,
            'liveness_score_bps' => $cert->livenessScoreBps,
            'sensor_model' => $sensorModel,
            'status' => BiometricEnrolmentStateMachine::STATUS_ENROLLED_ACTIVE,
            'consented_at' => Carbon::now(),
            'is_active' => true,
        ]);
    }

    /**
     * Verifikasi scan masuk di gerbang turnstile dengan perlindungan anti-spoofing.
     */
    public function scanTurnstileGate(
        int $userId,
        string $gateCode,
        string $probeFeatureVector,
        string $livenessProof
    ): BiometricScanResultDTO {
        $credential = BiometricCredential::where('user_id', $userId)
            ->where('status', BiometricEnrolmentStateMachine::STATUS_ENROLLED_ACTIVE)
            ->first();

        $now = time();

        if ($credential === null) {
            return new BiometricScanResultDTO(
                userId: $userId,
                gateCode: $gateCode,
                accessGranted: false,
                livenessScoreBps: 0,
                tamperProofAuditToken: 'TOKEN-NO-CREDENTIAL',
                scanTimestamp: $now,
                denialReason: 'Kredensial biometrik tidak ditemukan atau tidak aktif'
            );
        }

        try {
            $cert = $this->livenessEngine->match(
                userId: $userId,
                featureVector: $probeFeatureVector,
                challengeProof: $livenessProof,
                saltSecret: self::VAULT_SALT_KEY
            );

            if (! $cert->isMatched) {
                return new BiometricScanResultDTO(
                    userId: $userId,
                    gateCode: $gateCode,
                    accessGranted: false,
                    livenessScoreBps: $cert->livenessScoreBps,
                    tamperProofAuditToken: 'TOKEN-MISMATCH',
                    scanTimestamp: $now,
                    denialReason: 'Templat wajah probe tidak cocok dengan data terdaftar'
                );
            }

            $auditPayload = sprintf('GATE-AUDIT:%d:%s:%d', $userId, $gateCode, $now);
            $auditToken = hash_hmac('sha256', $auditPayload, self::VAULT_SALT_KEY);

            return new BiometricScanResultDTO(
                userId: $userId,
                gateCode: $gateCode,
                accessGranted: true,
                livenessScoreBps: $cert->livenessScoreBps,
                tamperProofAuditToken: $auditToken,
                scanTimestamp: $now,
                denialReason: null
            );
        } catch (\Throwable $e) {
            return new BiometricScanResultDTO(
                userId: $userId,
                gateCode: $gateCode,
                accessGranted: false,
                livenessScoreBps: 0,
                tamperProofAuditToken: 'TOKEN-LIVENESS-FAILED',
                scanTimestamp: $now,
                denialReason: $e->getMessage()
            );
        }
    }

    /**
     * Cabut persetujuan data biometrik tamu seketika (Hak Penghapusan GDPR / UU PDP).
     */
    public function revokeBiometricProfile(int $userId, string $revocationReason): bool
    {
        $credentials = BiometricCredential::where('user_id', $userId)->get();

        foreach ($credentials as $cred) {
            if ($cred->status !== BiometricEnrolmentStateMachine::STATUS_REVOKED) {
                $cred->status = $this->stateMachine->transition($cred->status, BiometricEnrolmentStateMachine::STATUS_REVOKED);
                $cred->is_active = false;
                $cred->revoked_at = Carbon::now();
                $cred->save();
            }
        }

        return true;
    }

    /**
     * Ringkasan statistik kepatuhan privasi biometrik untuk laporan audit tahunan.
     *
     * @return array{
     *   total_enrolled: int,
     *   active_credentials: int,
     *   revoked_credentials: int,
     *   average_liveness_bps: int,
     *   unconsented_violations: int
     * }
     */
    public function getPrivacyComplianceStatistics(): array
    {
        $all = BiometricCredential::query()->get();
        $total = $all->count();
        $active = $all->where('status', BiometricEnrolmentStateMachine::STATUS_ENROLLED_ACTIVE)->count();
        $revoked = $all->where('status', BiometricEnrolmentStateMachine::STATUS_REVOKED)->count();
        $unconsented = $all->whereNull('consented_at')->count();

        $avgLiveness = $total > 0
            ? (int) intdiv((int) $all->sum('liveness_score_bps'), $total)
            : 0;

        return [
            'total_enrolled' => $total,
            'active_credentials' => $active,
            'revoked_credentials' => $revoked,
            'average_liveness_bps' => $avgLiveness,
            'unconsented_violations' => $unconsented,
        ];
    }
}
