<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Insurance\Domain\Models\FraudScore;
use Modules\Insurance\Domain\Models\SiuInvestigation;
use Modules\Shared\Domain\Algorithms\Graph\UnionFind;
use Modules\Shared\Domain\Algorithms\Graph\UnionFindCertificateVerifier;

final class AssessFraudRiskAction
{
    public function __construct(
        private readonly UnionFind $unionFind,
        private readonly UnionFindCertificateVerifier $verifier,
    ) {}

    /**
     * @param  list<string>  $networkEntities  Daftar entitas yang terlibat (nomor HP, no rekening, NIK, alamat hash)
     * @param  list<array{0: string, 1: string}>  $networkEdges  Sisi relasi antar entitas
     * @param  array<string, mixed>  $claimMetrics
     */
    public function execute(
        string $assessmentKey,
        string $targetType,
        string $targetRef,
        string $subjectPartyId,
        array $networkEntities = [],
        array $networkEdges = [],
        array $claimMetrics = [],
    ): FraudScore {
        return DB::transaction(function () use (
            $assessmentKey,
            $targetType,
            $targetRef,
            $subjectPartyId,
            $networkEntities,
            $networkEdges,
            $claimMetrics
        ) {
            $existing = FraudScore::query()->where('assessment_key', $assessmentKey)->first();
            if ($existing !== null) {
                return $existing;
            }

            // 1. Eksekusi UnionFind untuk deteksi fraud ring jika ada data relasi graf
            $ringDetected = false;
            $ringClusters = [];
            if ($networkEntities !== [] && $networkEdges !== []) {
                $ufResult = $this->unionFind->build($networkEntities, $networkEdges);
                $this->verifier->verify($ufResult);

                $rings = $ufResult->rings(minSize: 3);
                if ($rings !== []) {
                    $ringDetected = true;
                    $ringClusters = $rings;
                }
            }

            // 2. Kalkulasi Fraud Score
            $score = 10; // baseline
            $breakdown = ['baseline' => 10];

            if ($ringDetected) {
                $score += 50;
                $breakdown['fraud_ring_network'] = 50;
            }

            if (($claimMetrics['frequency_last_90d'] ?? 0) >= 3) {
                $score += 25;
                $breakdown['high_claim_frequency'] = 25;
            }

            if (($claimMetrics['telematics_contradiction'] ?? false) === true) {
                $score += 20;
                $breakdown['telematics_mismatch'] = 20;
            }

            $score = min(100, $score);

            $riskLevel = match (true) {
                $score >= 80 => 'critical',
                $score >= 60 => 'high',
                $score >= 35 => 'medium',
                default => 'low',
            };

            $recommendedAction = match ($riskLevel) {
                'critical', 'high' => 'siu_investigation',
                'medium' => 'manual_review',
                default => 'auto_approve',
            };

            $assessment = FraudScore::create([
                'assessment_key' => $assessmentKey,
                'target_type' => $targetType,
                'target_ref' => $targetRef,
                'risk_score' => $score,
                'risk_level' => $riskLevel,
                'ring_fraud_detected' => $ringDetected,
                'ring_cluster_nodes' => $ringClusters,
                'score_breakdown' => $breakdown,
                'recommended_action' => $recommendedAction,
            ]);

            // Jika high/critical -> otomatis buka berkas investigasi SIU
            if (in_array($riskLevel, ['high', 'critical'], true)) {
                SiuInvestigation::create([
                    'case_number' => 'SIU-'.strtoupper(uniqid()),
                    'fraud_assessment_id' => $assessment->id,
                    'subject_party_id' => $subjectPartyId,
                    'reason' => "Skor risiko fraud tinggi ({$score}/100)".($ringDetected ? ' - Sindikat fraud ring terdeteksi' : ''),
                    'status' => 'assigned',
                ]);
            }

            return $assessment;
        });
    }
}
