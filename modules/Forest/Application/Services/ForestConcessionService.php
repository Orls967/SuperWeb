<?php

declare(strict_types=1);

namespace Modules\Forest\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Esg\Contracts\CarbonCreditIssuer;
use Modules\Forest\Domain\Models\ForestConcessionPlot;
use Modules\Forest\Domain\Models\ForestHarvestPermit;
use Modules\Forest\Domain\Models\ForestRestorationPlot;
use Modules\Forest\Domain\Models\ForestTimberLog;
use Modules\Forest\Domain\Models\ForestTimberSale;
use Modules\Forest\Domain\Models\ForestTreeInventory;
use Modules\Forest\Ledger\ForestAccounts;
use Modules\Forest\Ledger\ForestTransactionTypes;
use Modules\Shared\Domain\Algorithms\Forestry\TreeVolumeCalculator;
use RuntimeException;

final class ForestConcessionService
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly CarbonCreditIssuer $carbonCreditIssuer,
    ) {}

    public function createPlot(array $payload): ForestConcessionPlot
    {
        return ForestConcessionPlot::create([
            'plot_code' => $payload['plot_code'],
            'concession_name' => $payload['concession_name'],
            'area_hectares' => (int) $payload['area_hectares'],
            'annual_quota_m3' => (int) $payload['annual_quota_m3'],
            'harvested_volume_m3' => 0,
            'status' => $payload['status'] ?? 'active',
        ]);
    }

    public function registerTree(array $payload): ForestTreeInventory
    {
        $volDm3 = TreeVolumeCalculator::calculateVolumeDm3(
            (int) $payload['dbh_cm'],
            (int) $payload['height_m'],
            (int) ($payload['form_factor_pct'] ?? 70)
        );

        return ForestTreeInventory::create([
            'tree_tag' => $payload['tree_tag'],
            'plot_id' => (int) $payload['plot_id'],
            'species_name' => $payload['species_name'],
            'dbh_cm' => (int) $payload['dbh_cm'],
            'height_m' => (int) $payload['height_m'],
            'form_factor_pct' => (int) ($payload['form_factor_pct'] ?? 70),
            'calculated_volume_dm3' => $volDm3,
            'status' => 'standing',
        ]);
    }

    public function requestHarvestPermit(array $payload, int $requesterUserRef): ForestHarvestPermit
    {
        $plot = ForestConcessionPlot::findOrFail((int) $payload['plot_id']);
        $authM3 = (int) $payload['authorized_volume_m3'];

        // Cek kuota petak
        $remainingQuota = $plot->annual_quota_m3 - $plot->harvested_volume_m3;
        if ($authM3 > $remainingQuota) {
            throw new RuntimeException("Volume izin tebang ({$authM3} m3) melebihi sisa kuota petak ({$remainingQuota} m3).");
        }

        return ForestHarvestPermit::create([
            'permit_code' => $payload['permit_code'],
            'plot_id' => $plot->id,
            'authorized_volume_m3' => $authM3,
            'harvested_volume_m3' => 0,
            'valid_from' => $payload['valid_from'],
            'valid_until' => $payload['valid_until'],
            'status' => 'submitted',
            'requester_user_ref' => $requesterUserRef,
        ]);
    }

    public function approveHarvestPermit(int $permitId, int $approverUserRef): ForestHarvestPermit
    {
        $permit = ForestHarvestPermit::findOrFail($permitId);

        // Four-eyes principle: requester tidak boleh menyetujui izin sendiri
        if ($permit->requester_user_ref === $approverUserRef) {
            throw new RuntimeException('Prinsip four-eyes: pemohon izin tebang tidak boleh menyetujui izinnya sendiri.');
        }

        if ($permit->status !== 'submitted') {
            throw new RuntimeException("Izin tebang dengan status '{$permit->status}' tidak dapat disetujui.");
        }

        $permit->update([
            'status' => 'approved',
            'approver_user_ref' => $approverUserRef,
            'approved_at' => now(),
        ]);

        return $permit;
    }

    public function logHarvestedTimber(array $payload): ForestTimberLog
    {
        return DB::transaction(function () use ($payload) {
            $permit = ForestHarvestPermit::lockForUpdate()->findOrFail((int) $payload['permit_id']);

            if ($permit->status !== 'approved') {
                throw new RuntimeException('Kayu tebangan hanya dapat dicatat pada izin tebang yang sudah disetujui.');
            }

            $volDm3 = (int) $payload['volume_dm3'];
            $volM3 = TreeVolumeCalculator::toM3($volDm3);

            if ($permit->harvested_volume_m3 + $volM3 > $permit->authorized_volume_m3) {
                throw new RuntimeException('Volume tebangan melampaui kuota izin tebang.');
            }

            // Dapatkan log terakhir untuk gapless serial & hash chain
            $lastLog = ForestTimberLog::where('permit_id', $permit->id)
                ->orderByDesc('serial_number')
                ->lockForUpdate()
                ->first();

            $serial = $lastLog ? $lastLog->serial_number + 1 : 1;
            $prevHash = $lastLog ? $lastLog->canonical_hash : str_repeat('0', 64);

            $logCode = $payload['log_code'];
            $canonicalData = "{$logCode}:{$permit->permit_code}:{$serial}:{$volDm3}:{$prevHash}";
            $canonicalHash = hash('sha256', $canonicalData);

            $timberLog = ForestTimberLog::create([
                'log_code' => $logCode,
                'plot_id' => $permit->plot_id,
                'permit_id' => $permit->id,
                'tree_tag' => $payload['tree_tag'],
                'volume_dm3' => $volDm3,
                'serial_number' => $serial,
                'prev_hash' => $prevHash,
                'canonical_hash' => $canonicalHash,
                'stage' => 'stump',
            ]);

            $permit->increment('harvested_volume_m3', $volM3);
            ForestConcessionPlot::where('id', $permit->plot_id)->increment('harvested_volume_m3', $volM3);

            ForestTreeInventory::where('tree_tag', $payload['tree_tag'])->update(['status' => 'felled']);

            return $timberLog;
        });
    }

    public function recordTimberSale(array $payload): ForestTimberSale
    {
        return DB::transaction(function () use ($payload) {
            $existing = ForestTimberSale::where('idempotency_key', $payload['idempotency_key'])->first();
            if ($existing) {
                return $existing;
            }

            $totalAmountIdr = (int) $payload['total_amount_idr'];
            $levyIdr = (int) $payload['forestry_levy_idr'];
            $netRevenueIdr = $totalAmountIdr - $levyIdr;

            if ($netRevenueIdr < 0) {
                throw new InvalidArgumentException('Pungutan kehutanan tidak boleh melebihi nilai penjualan.');
            }

            $sale = ForestTimberSale::create([
                'sale_code' => $payload['sale_code'],
                'buyer_party_ref' => (int) $payload['buyer_party_ref'],
                'total_volume_dm3' => (int) $payload['total_volume_dm3'],
                'total_amount_idr' => $totalAmountIdr,
                'forestry_levy_idr' => $levyIdr,
                'idempotency_key' => $payload['idempotency_key'],
                'status' => 'invoiced',
            ]);

            $posting = Posting::lines()->debit(ForestAccounts::AR_TIMBER, $totalAmountIdr);
            if ($netRevenueIdr > 0) {
                $posting = $posting->credit(ForestAccounts::REVENUE_TIMBER, $netRevenueIdr);
            }
            if ($levyIdr > 0) {
                $posting = $posting->credit(ForestAccounts::FORESTRY_LEVY_PAYABLE, $levyIdr);
            }

            $dto = new PostingDTO(
                type: ForestTransactionTypes::FOREST_TIMBER_SALE->value,
                description: "Penjualan kayu log {$sale->sale_code} & pungutan PSDH DR",
                idempotencyKey: $payload['idempotency_key'],
                entries: $posting->toEntries('IDR'),
                referenceType: 'FOR_TIMBER_SALE',
                referenceId: (string) $sale->id,
            );

            $this->ledgerService->post($dto);

            return $sale;
        });
    }

    public function recordRestorationOutcome(array $payload): ForestRestorationPlot
    {
        $plot = ForestRestorationPlot::create([
            'restoration_code' => $payload['restoration_code'],
            'plot_id' => (int) $payload['plot_id'],
            'planted_hectares' => (int) $payload['planted_hectares'],
            'trees_planted' => (int) $payload['trees_planted'],
            'survival_rate_pct' => (int) $payload['survival_rate_pct'],
            'satellite_ndvi_score' => (int) $payload['satellite_ndvi_score'],
            'carbon_credits_issued' => 0,
        ]);

        if ($plot->survival_rate_pct >= 75 && $plot->satellite_ndvi_score >= 60) {
            $kgCo2e = $plot->planted_hectares * 15000;
            $idempotencyKey = "ESG-CARBON-REST-{$plot->restoration_code}";
            $this->carbonCreditIssuer->issueRestorationCredits(
                $plot->id,
                $kgCo2e,
                (int) date('Y'),
                $idempotencyKey
            );
            $plot->update(['carbon_credits_issued' => $kgCo2e]);
        }

        return $plot;
    }
}
