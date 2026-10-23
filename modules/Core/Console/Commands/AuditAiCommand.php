<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Domain\Models\CoreAiAnomalyQuarantine;
use Modules\Core\Domain\Models\CoreAiDecisionSnapshot;
use Modules\Core\Domain\Models\CoreAiStockRecommendation;

class AuditAiCommand extends Command
{
    protected $signature = 'ai:audit';

    protected $description = 'Audit deterministic AI decision snapshots, pricing guardrails, and anomaly quarantines (Fase 64.4)';

    public function handle(): int
    {
        $this->info('Starting deterministic AI decision audit (Fase 64)...');

        if (! Schema::hasTable('core_ai_decision_snapshots')) {
            $this->warn('AI decision snapshots table not found; skipping snapshot verification.');

            return self::SUCCESS;
        }

        // Phase 1: Cryptographic Snapshot Replay & Hash Verification
        $snapshots = CoreAiDecisionSnapshot::all();
        $this->line("1. Verifying SHA-256 integrity of {$snapshots->count()} AI decision snapshots...");

        foreach ($snapshots as $snapshot) {
            $computedHash = hash(
                'sha256',
                (string) $snapshot->seed.json_encode($snapshot->input_snapshot).json_encode($snapshot->decision_output)
            );

            if ($computedHash !== $snapshot->decision_hash) {
                $this->error("AI audit FAILED: Decision hash mismatch on decision {$snapshot->decision_code}");
                $this->line("Expected: {$snapshot->decision_hash} | Computed: {$computedHash}");

                return self::FAILURE;
            }
        }

        // Phase 2: Pricing Guardrail Boundary Verification
        $pricingSnapshots = CoreAiDecisionSnapshot::where('model_name', 'DYNAMIC_PRICING')->get();
        $this->line("2. Verifying pricing guardrails on {$pricingSnapshots->count()} dynamic pricing snapshots...");

        foreach ($pricingSnapshots as $snapshot) {
            $finalPrice = (int) ($snapshot->decision_output['final_price_idr'] ?? 0);
            $floorPrice = (int) ($snapshot->input_snapshot['floor_price_idr'] ?? 0);
            $ceilingPrice = (int) ($snapshot->input_snapshot['ceiling_price_idr'] ?? PHP_INT_MAX);

            if ($finalPrice < $floorPrice || $finalPrice > $ceilingPrice) {
                $this->error("AI audit FAILED: Pricing guardrail breached on decision {$snapshot->decision_code} (Final: {$finalPrice}, Floor: {$floorPrice}, Ceiling: {$ceilingPrice})");

                return self::FAILURE;
            }
        }

        // Phase 3: Anomaly Quarantine Invariant Audit
        if (Schema::hasTable('core_ai_anomaly_quarantines')) {
            $anomalySnapshots = CoreAiDecisionSnapshot::where('model_name', 'TRANSACTION_ANOMALY_DETECTOR')->get();
            $this->line("3. Verifying quarantine invariants on {$anomalySnapshots->count()} anomaly evaluations...");

            foreach ($anomalySnapshots as $snapshot) {
                $riskScore = (float) ($snapshot->decision_output['risk_score'] ?? 0.0);
                if ($riskScore >= 0.80) {
                    $txRef = (string) ($snapshot->input_snapshot['transaction_ref'] ?? '');
                    $hasQuarantine = CoreAiAnomalyQuarantine::where('transaction_ref', $txRef)->exists();

                    if (! $hasQuarantine) {
                        $this->error("AI audit FAILED: Missing anomaly quarantine record for high-risk transaction {$txRef} (Score: {$riskScore})");

                        return self::FAILURE;
                    }
                }
            }
        }

        // Phase 4: Autonomous Threshold Compliance Audit
        if (Schema::hasTable('core_ai_stock_recommendations')) {
            $this->line('4. Verifying autonomous threshold compliance on stock recommendations...');
            $expensiveRecommendations = CoreAiStockRecommendation::where('estimated_cost_idr', '>', 10_000_000)->get();

            foreach ($expensiveRecommendations as $rec) {
                if (! $rec->requires_human_approval && $rec->status === 'APPROVED_AS_PO') {
                    $this->error("AI audit FAILED: Autonomous threshold violated without human approval on recommendation {$rec->recommendation_code}");

                    return self::FAILURE;
                }
            }
        }

        $this->info('AI audit PASSED: All decision snapshots and invariants verified clean');

        return self::SUCCESS;
    }
}
