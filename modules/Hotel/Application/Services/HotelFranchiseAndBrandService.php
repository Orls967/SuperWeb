<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Hotel\Domain\Models\BrandStandardAudit;
use Modules\Hotel\Domain\Models\FranchiseContract;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\RateParityViolation;

class HotelFranchiseAndBrandService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 111.1 Brand Standard Audit and Property Listing Status
     */
    public function conductBrandAudit(
        HotelProperty $property,
        int $passedItems,
        int $totalItems = 200
    ): BrandStandardAudit {
        $score = ($totalItems > 0) ? ($passedItems / $totalItems) * 100 : 0;

        if ($score >= 90) {
            $starGrade = '5_STAR_LUXURY';
            $listingStatus = 'ACTIVE';
        } elseif ($score >= 75) {
            $starGrade = '4_STAR';
            $listingStatus = 'ACTIVE';
        } elseif ($score >= 60) {
            $starGrade = '3_STAR';
            $listingStatus = 'ACTION_PLAN_REQUIRED';
        } else {
            $starGrade = 'UNGRADED';
            $listingStatus = 'SUSPENDED';
        }

        return BrandStandardAudit::create([
            'id' => (string) Str::uuid(),
            'audit_code' => 'BSA-'.strtoupper(bin2hex(random_bytes(4))),
            'property_id' => $property->id,
            'audit_date' => now()->toDateString(),
            'total_checklist_items' => $totalItems,
            'passed_items_count' => $passedItems,
            'compliance_score_percent' => round($score, 2),
            'simulated_star_grade' => $starGrade,
            'listing_status' => $listingStatus,
        ]);
    }

    /**
     * 111.2 Onboard Franchise Contract and Bill Initial Fee
     */
    public function onboardFranchise(
        HotelProperty $property,
        int $initialFeeIdr,
        float $royaltyPercent = 5.0
    ): FranchiseContract {
        return DB::transaction(function () use ($property, $initialFeeIdr, $royaltyPercent) {
            $contract = FranchiseContract::create([
                'id' => (string) Str::uuid(),
                'contract_code' => 'HFC-'.strtoupper(bin2hex(random_bytes(4))),
                'property_id' => $property->id,
                'contract_type' => 'FRANCHISE',
                'initial_franchise_fee_idr' => $initialFeeIdr,
                'royalty_percentage' => $royaltyPercent,
                'status' => 'ACTIVE',
            ]);

            if ($initialFeeIdr > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: 'HOTEL_FRANCHISE_FEE_BILLING',
                    description: "Initial franchise fee for property {$property->name}",
                    idempotencyKey: "HTL-FEE-{$contract->contract_code}",
                    entries: [
                        PostingEntryDTO::forCode('htl:franchise_receivable:IDR', 'IDR', $initialFeeIdr),
                        PostingEntryDTO::forCode('htl:franchise_fee_revenue:IDR', 'IDR', -$initialFeeIdr),
                    ],
                    referenceType: 'HOTEL_FRANCHISE_CONTRACT',
                    referenceId: $contract->id,
                ));
            }

            return $contract;
        });
    }

    /**
     * 111.2 Settle Monthly Franchise Royalty from Folio Revenue
     */
    public function billMonthlyRoyalty(
        FranchiseContract $contract,
        int $monthlyFolioRevenueIdr
    ): int {
        $royaltyAmountIdr = (int) round(($monthlyFolioRevenueIdr * $contract->royalty_percentage) / 100);

        if ($royaltyAmountIdr > 0) {
            $this->ledgerService->post(new PostingDTO(
                type: 'HOTEL_FRANCHISE_ROYALTY_BILLING',
                description: "Monthly franchise royalty for contract {$contract->contract_code}",
                idempotencyKey: "HTL-ROY-{$contract->contract_code}-".now()->format('Ym'),
                entries: [
                    PostingEntryDTO::forCode('htl:franchise_receivable:IDR', 'IDR', $royaltyAmountIdr),
                    PostingEntryDTO::forCode('htl:royalty_revenue:IDR', 'IDR', -$royaltyAmountIdr),
                ],
                referenceType: 'HOTEL_FRANCHISE_CONTRACT',
                referenceId: $contract->id,
            ));
        }

        return $royaltyAmountIdr;
    }

    /**
     * 111.4 Detect Rate Parity Violation & Accrue OTA Penalty
     */
    public function detectRateParityViolation(
        HotelProperty $property,
        string $otaChannel,
        int $directBarRateIdr,
        int $otaUndercutRateIdr
    ): ?RateParityViolation {
        if ($otaUndercutRateIdr >= $directBarRateIdr) {
            return null; // No violation
        }

        // Penalty levy: 2x the undercut price difference
        $penaltyIdr = ($directBarRateIdr - $otaUndercutRateIdr) * 2;

        return DB::transaction(function () use ($property, $otaChannel, $directBarRateIdr, $otaUndercutRateIdr, $penaltyIdr) {
            $violation = RateParityViolation::create([
                'id' => (string) Str::uuid(),
                'violation_code' => 'RPV-'.strtoupper(bin2hex(random_bytes(4))),
                'property_id' => $property->id,
                'ota_channel_name' => $otaChannel,
                'direct_bar_rate_idr' => $directBarRateIdr,
                'ota_undercut_rate_idr' => $otaUndercutRateIdr,
                'penalty_levy_idr' => $penaltyIdr,
                'status' => 'PENALTY_ACCRUED',
            ]);

            $this->ledgerService->post(new PostingDTO(
                type: 'HOTEL_RATE_PARITY_PENALTY_ACCRUAL',
                description: "Rate parity undercut penalty levy on {$otaChannel} for property {$property->property_code}",
                idempotencyKey: "HTL-RPV-{$violation->violation_code}",
                entries: [
                    PostingEntryDTO::forCode('htl:ota_penalty_receivable:IDR', 'IDR', $penaltyIdr),
                    PostingEntryDTO::forCode('htl:parity_penalty_revenue:IDR', 'IDR', -$penaltyIdr),
                ],
                referenceType: 'RATE_PARITY_VIOLATION',
                referenceId: $violation->id,
            ));

            return $violation;
        });
    }
}
