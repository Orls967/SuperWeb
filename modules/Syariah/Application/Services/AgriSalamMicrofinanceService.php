<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Agri\Contracts\FarmerLoanOffset;
use Modules\Agri\Contracts\NdviIndexReader;
use Modules\Syariah\Domain\Models\SybAgriMicroFacility;
use Modules\Syariah\Domain\Models\SybAgriMilestone;
use Modules\Syariah\Ledger\SyariahTransactionTypes;

class AgriSalamMicrofinanceService
{
    public function __construct(
        protected ShariahLedgerPoster $ledgerPoster,
        protected NdviIndexReader $ndviIndexReader,
        protected FarmerLoanOffset $farmerLoanOffset
    ) {}

    public function createFacility(
        string $farmerPartyId,
        string $plotCode,
        string $commodity,
        int $financingAmountIdr,
        int $expectedYieldKg,
        int $contractPricePerKgIdr,
        string $contractType = 'SALAM',
        int $milestoneCount = 3
    ): SybAgriMicroFacility {
        return DB::transaction(function () use (
            $farmerPartyId,
            $plotCode,
            $commodity,
            $financingAmountIdr,
            $expectedYieldKg,
            $contractPricePerKgIdr,
            $contractType,
            $milestoneCount
        ) {
            $code = 'AGR-'.strtoupper(Str::random(8));

            $facility = SybAgriMicroFacility::create([
                'facility_code' => $code,
                'farmer_party_ref' => $farmerPartyId,
                'plot_code' => $plotCode,
                'commodity' => $commodity,
                'contract_type' => $contractType,
                'financing_amount_idr' => $financingAmountIdr,
                'outstanding_balance_idr' => $financingAmountIdr,
                'expected_yield_kg' => $expectedYieldKg,
                'contract_price_per_kg_idr' => $contractPricePerKgIdr,
                'milestone_count' => $milestoneCount,
                'disbursed_amount_idr' => 0,
                'ndvi_min_threshold_bps' => 3000, // Minimal 0.3 NDVI
                'current_ndvi_bps' => 0,
                'status' => 'active',
            ]);

            // Buat milestone pencairan terikat fase tanam dan target NDVI
            $percentages = [40, 30, 30]; // 40% bibit/tanam, 30% pemupukan/vegetatif, 30% pra-panen
            $titles = ['Fase Olah Lahan & Pembibitan', 'Fase Vegetatif & Pemupukan Berimbang', 'Fase Pematangan & Pra-Panen'];
            $ndviTargets = [2500, 4500, 6000];

            for ($i = 0; $i < $milestoneCount; $i++) {
                $pct = $percentages[$i] ?? 25;
                $amount = (int) intdiv($financingAmountIdr * $pct, 100);

                SybAgriMilestone::create([
                    'milestone_code' => 'MLS-'.$code.'-0'.($i + 1),
                    'facility_code' => $code,
                    'milestone_number' => $i + 1,
                    'title' => $titles[$i] ?? 'Fase Pertanian #'.($i + 1),
                    'target_ndvi_bps' => $ndviTargets[$i] ?? 3500,
                    'disbursement_percentage' => $pct,
                    'amount_idr' => $amount,
                    'status' => 'PENDING',
                ]);
            }

            return $facility;
        });
    }

    public function evaluateAndDisburseMilestone(string $milestoneCode): SybAgriMilestone
    {
        return DB::transaction(function () use ($milestoneCode) {
            $milestone = SybAgriMilestone::where('milestone_code', $milestoneCode)->lockForUpdate()->firstOrFail();
            $facility = SybAgriMicroFacility::where('facility_code', $milestone->facility_code)->lockForUpdate()->firstOrFail();

            if ($milestone->status === 'DISBURSED') {
                return $milestone;
            }

            // Baca data indeks satelit NDVI dari Kontrak Antar-Modul G7-K1
            $index = $this->ndviIndexReader->readIndex($facility->plot_code, CarbonImmutable::now());
            $currentNdvi = $index['ndvi_bps'] ?? 4000;

            $facility->current_ndvi_bps = $currentNdvi;
            $facility->save();

            // Guard: Pencairan tahap lanjutan mensyaratkan kesehatan tanaman terverifikasi
            if ($milestone->milestone_number > 1 && $currentNdvi < $milestone->target_ndvi_bps) {
                throw new InvalidArgumentException("Indeks vegetasi NDVI ({$currentNdvi} bps) belum mencapai target kelayakan ({$milestone->target_ndvi_bps} bps).");
            }

            $milestone->status = 'DISBURSED';
            $milestone->verified_at = CarbonImmutable::now();
            $milestone->save();

            $facility->disbursed_amount_idr += $milestone->amount_idr;
            $facility->save();

            // Ledger posting pencairan salam bertahap
            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_MICRO_DISBURSEMENT,
                reference: $milestone->milestone_code,
                description: "Pencairan Bertahap Salam Pertanian: {$facility->facility_code} Milestone #{$milestone->milestone_number}",
                entries: [
                    'syb:agri_financing_receivable:IDR' => -$milestone->amount_idr,
                    'syb:cash_pool:IDR' => $milestone->amount_idr,
                ]
            );

            return $milestone;
        });
    }

    public function handleParametricClaimOffset(string $facilityCode, int $claimPayoutIdr, string $claimRef): int
    {
        return DB::transaction(function () use ($facilityCode, $claimPayoutIdr, $claimRef) {
            $facility = SybAgriMicroFacility::where('facility_code', $facilityCode)->lockForUpdate()->firstOrFail();

            // Panggil Kontrak Antar-Modul G7-K2 FarmerLoanOffset
            $remaining = $this->farmerLoanOffset->offsetLoanBalance(
                farmerPartyId: $facility->farmer_party_ref,
                offsetAmountIdr: $claimPayoutIdr,
                referenceClaimNumber: $claimRef
            );

            $offsetApplied = min($facility->outstanding_balance_idr, $claimPayoutIdr);
            $facility->outstanding_balance_idr -= $offsetApplied;
            if ($facility->outstanding_balance_idr <= 0) {
                $facility->outstanding_balance_idr = 0;
                $facility->status = 'paid_off';
            }
            $facility->save();

            // Ledger posting kliring klaim asuransi tani memotong saldo pembiayaan syariah
            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_AGRI_SALAM_OFFSET,
                reference: $claimRef,
                description: "Offset Pembiayaan Salam Pertanian dari Klaim Asuransi: {$facilityCode}",
                entries: [
                    'syb:takaful_claim_offset_clearing:IDR' => -$offsetApplied,
                    'syb:agri_financing_receivable:IDR' => $offsetApplied,
                ]
            );

            return $facility->outstanding_balance_idr;
        });
    }
}
