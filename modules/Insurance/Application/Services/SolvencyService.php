<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Insurance\Domain\Enums\SolvencyActionStatus;
use Modules\Insurance\Domain\Enums\SolvencyActionType;
use Modules\Insurance\Domain\Enums\SolvencyAlertLevel;
use Modules\Insurance\Domain\Models\InsuranceClaimPenuh;
use Modules\Insurance\Domain\Models\InsurancePolicyPenuh;
use Modules\Insurance\Domain\Models\SolvencyAction;
use Modules\Insurance\Domain\Models\SolvencySnapshot;
use Modules\Shared\Domain\Algorithms\Chain\CanonicalHashChain;
use Modules\Shared\Domain\Algorithms\Solvency\RiskBasedCapital;
use Modules\Shared\Domain\Algorithms\Solvency\RiskBasedCapitalCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Solvency\SolvencyResult;

final class SolvencyService
{
    public function __construct(
        private readonly RiskBasedCapital $rbc,
        private readonly RiskBasedCapitalCertificateVerifier $verifier,
        private readonly ParameterRepository $parameters,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Hitung solvabilitas snapshot pada tanggal valuasi.
     *
     * @param  array<string, int>|null  $customRiskCharges
     */
    public function createSnapshot(CarbonImmutable $valuationDate, ?array $customRiskCharges = null, ?int $customCapital = null, ?int $actorId = null): SolvencySnapshot
    {
        $snapshotKey = 'solv:'.$valuationDate->format('Y-m-d');
        $existing = SolvencySnapshot::query()->where('snapshot_key', $snapshotKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $correlations = $this->parameters->json('rbc.correlation', $valuationDate, [
            'premium' => ['reserve' => 5000, 'market' => 2500],
            'reserve' => ['market' => 2500],
        ]);

        $riskCharges = $customRiskCharges ?? $this->computeRiskChargesFromData($valuationDate);
        $availableCapital = $customCapital ?? $this->computeAvailableCapital();
        $operationalIdr = (int) ($this->parameters->json('rbc.operational_idr', $valuationDate, 30000000));

        $result = $this->rbc->compute($riskCharges, $correlations, $operationalIdr, $availableCapital);
        $this->verifier->verify($result);

        $ratioBps = $result->solvencyRatioBps;
        $alertLevel = $this->determineAlertLevel($ratioBps);

        $paramVersions = [
            'rbc.correlation' => 1,
            'rbc.operational_idr' => 1,
        ];

        $inputData = [
            'valuation_date' => $valuationDate->format('Y-m-d'),
            'risk_charges' => $riskCharges,
            'correlations' => $correlations,
            'operational_idr' => $operationalIdr,
            'available_capital_idr' => $availableCapital,
        ];

        $chain = new CanonicalHashChain;
        $inputHash = $chain->link(CanonicalHashChain::GENESIS_HASH, $inputData);

        return DB::transaction(function () use (
            $snapshotKey,
            $valuationDate,
            $paramVersions,
            $riskCharges,
            $correlations,
            $operationalIdr,
            $availableCapital,
            $result,
            $ratioBps,
            $alertLevel,
            $inputHash,
            $actorId
        ) {
            $snapshot = SolvencySnapshot::create([
                'snapshot_key' => $snapshotKey,
                'valuation_date' => $valuationDate->format('Y-m-d'),
                'parameter_versions' => $paramVersions,
                'risk_charges' => $riskCharges,
                'correlation' => $correlations,
                'operational_idr' => $operationalIdr,
                'available_capital_idr' => $availableCapital,
                'rbc_idr' => $result->rbcIdr,
                'ratio_bps' => $ratioBps,
                'alert_level' => $alertLevel,
                'input_hash' => $inputHash,
                'created_by' => $actorId,
            ]);

            // Buat rekomendasi aksi bila di bawah ambang sehat (150%)
            if ($alertLevel !== SolvencyAlertLevel::Healthy) {
                $this->generateRecommendedActions($snapshot, $result);
            }

            return $snapshot;
        });
    }

    /**
     * Simulasi what-if atas sebuah aksi rekomendasi.
     *
     * @param  array<string, mixed>  $params
     */
    public function simulateAction(SolvencySnapshot $snapshot, SolvencyActionType $type, array $params): int
    {
        $charges = $snapshot->risk_charges;
        $correlations = $snapshot->correlation;
        $operational = $snapshot->operational_idr;
        $capital = $snapshot->available_capital_idr;

        switch ($type) {
            case SolvencyActionType::CapitalInjection:
                $injection = (int) ($params['amount_idr'] ?? 0);
                $capital += $injection;
                break;

            case SolvencyActionType::AddQuotaShare:
                $addedShareBps = (int) ($params['quota_share_bps'] ?? 2000);
                // Tambah QS mengurangi beban premi secara proporsional
                $reductionFactor = max(0, 10000 - $addedShareBps);
                if (isset($charges['premium'])) {
                    $charges['premium'] = (int) intdiv($charges['premium'] * $reductionFactor, 10000);
                }
                break;

            case SolvencyActionType::ReduceExposure:
                $class = (string) ($params['class'] ?? 'premium');
                $pct = (int) ($params['reduction_pct'] ?? 20);
                if (isset($charges[$class])) {
                    $charges[$class] = (int) intdiv($charges[$class] * (100 - $pct), 100);
                }
                break;
        }

        $simResult = $this->rbc->compute($charges, $correlations, $operational, $capital);
        $this->verifier->verify($simResult);

        return $simResult->solvencyRatioBps;
    }

    private function determineAlertLevel(int $ratioBps): SolvencyAlertLevel
    {
        if ($ratioBps < 12000) {
            return SolvencyAlertLevel::Regulator;
        }
        if ($ratioBps < 15000) {
            return SolvencyAlertLevel::EarlyWarning;
        }

        return SolvencyAlertLevel::Healthy;
    }

    private function generateRecommendedActions(SolvencySnapshot $snapshot, SolvencyResult $result): void
    {
        // 1. Injeksi modal agar mencapai 15000 bps (150%)
        $shortfall = $this->rbc->capitalShortfall($result, 15000);
        $simRatio1 = $this->simulateAction($snapshot, SolvencyActionType::CapitalInjection, ['amount_idr' => $shortfall]);
        SolvencyAction::create([
            'snapshot_id' => $snapshot->id,
            'action_type' => SolvencyActionType::CapitalInjection,
            'params' => ['amount_idr' => $shortfall, 'target_ratio_bps' => 15000],
            'simulated_ratio_bps' => $simRatio1,
            'status' => SolvencyActionStatus::Proposed,
        ]);

        // 2. Tambah Quota Share 20%
        $simRatio2 = $this->simulateAction($snapshot, SolvencyActionType::AddQuotaShare, ['quota_share_bps' => 2000]);
        SolvencyAction::create([
            'snapshot_id' => $snapshot->id,
            'action_type' => SolvencyActionType::AddQuotaShare,
            'params' => ['quota_share_bps' => 2000],
            'simulated_ratio_bps' => $simRatio2,
            'status' => SolvencyActionStatus::Proposed,
        ]);

        // 3. Kurangi eksposur kelas premi 25%
        $simRatio3 = $this->simulateAction($snapshot, SolvencyActionType::ReduceExposure, ['class' => 'premium', 'reduction_pct' => 25]);
        SolvencyAction::create([
            'snapshot_id' => $snapshot->id,
            'action_type' => SolvencyActionType::ReduceExposure,
            'params' => ['class' => 'premium', 'reduction_pct' => 25],
            'simulated_ratio_bps' => $simRatio3,
            'status' => SolvencyActionStatus::Proposed,
        ]);
    }

    /**
     * Hitung beban risiko dari data riil polis dan klaim aktif.
     *
     * @return array<string, int>
     */
    private function computeRiskChargesFromData(CarbonImmutable $valuationDate): array
    {
        $activePremiums = (int) InsurancePolicyPenuh::query()
            ->where('status', 'active')
            ->sum('retained_premium_idr');

        $activeClaims = (int) InsuranceClaimPenuh::query()
            ->whereIn('status', ['registered', 'investigating', 'approved'])
            ->sum('claimed_amount_idr');

        $premiumCharge = max(100000000, (int) intdiv($activePremiums * 20, 100)); // 20% premi
        $reserveCharge = max(150000000, (int) intdiv($activeClaims * 30, 100));   // 30% cadangan klaim
        $marketCharge = max(100000000, (int) intdiv($premiumCharge * 50, 100));

        return [
            'premium' => $premiumCharge,
            'reserve' => $reserveCharge,
            'market' => $marketCharge,
        ];
    }

    private function computeAvailableCapital(): int
    {
        // Default modal tersedia minimum 1.5M bila belum ada penyertaan modal tersimpan
        return 1500000000;
    }
}

