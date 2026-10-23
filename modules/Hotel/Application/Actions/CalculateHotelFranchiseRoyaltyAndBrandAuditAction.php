<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Domain\Models\BrandStandardAudit;
use Modules\Hotel\Domain\Models\FranchiseContract;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\RateParityViolation;
use Modules\Shared\Application\BaseAction;

class CalculateHotelFranchiseRoyaltyAndBrandAuditAction extends BaseAction
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * Conduct brand standard audit, bill franchise royalty from folio revenue, and enforce rate parity.
     *
     * @return array{audit: BrandStandardAudit, royalty_due_idr: int, violation: ?RateParityViolation}
     */
    public function handle(
        string $propertyId,
        int $passedChecklistItems,
        int $totalChecklistItems = 200,
        int $monthlyFolioRevenueIdr = 500000000,
        float $royaltyPercentage = 5.0,
        ?string $otaChannel = null,
        int $directBarRateIdr = 1500000,
        int $otaUndercutRateIdr = 1500000
    ): array {
        return DB::transaction(function () use (
            $propertyId,
            $passedChecklistItems,
            $totalChecklistItems,
            $monthlyFolioRevenueIdr,
            $royaltyPercentage,
            $otaChannel,
            $directBarRateIdr,
            $otaUndercutRateIdr
        ) {
            $property = HotelProperty::lockForUpdate()->findOrFail($propertyId);

            // 111.1 Brand Standard Audit Checklist
            $score = ($totalChecklistItems > 0) ? ($passedChecklistItems / $totalChecklistItems) * 100 : 0;
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

            $auditCount = BrandStandardAudit::where('property_id', $property->id)->count() + 1;
            $auditCode = sprintf('BSA-%s-%04d', $property->property_code, $auditCount);

            $audit = BrandStandardAudit::create([
                'id' => (string) Str::uuid(),
                'audit_code' => $auditCode,
                'property_id' => $property->id,
                'audit_date' => date('Y-m-d'),
                'total_checklist_items' => $totalChecklistItems,
                'passed_items_count' => $passedChecklistItems,
                'compliance_score_percent' => round($score, 2),
                'simulated_star_grade' => $starGrade,
                'listing_status' => $listingStatus,
            ]);

            // 111.2 Hotel Franchise & Management Contract Royalty
            $contract = FranchiseContract::where('property_id', $property->id)->first();
            if (! $contract) {
                $contract = FranchiseContract::create([
                    'id' => (string) Str::uuid(),
                    'contract_code' => 'HFC-'.strtoupper(bin2hex(random_bytes(4))),
                    'property_id' => $property->id,
                    'contract_type' => 'FRANCHISE',
                    'initial_franchise_fee_idr' => 100000000,
                    'royalty_percentage' => $royaltyPercentage,
                    'status' => 'ACTIVE',
                ]);
            }

            $royaltyDueIdr = (int) round(($monthlyFolioRevenueIdr * $contract->royalty_percentage) / 100);

            if ($royaltyDueIdr > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: 'HOTEL_FRANCHISE_ROYALTY_BILLING',
                    description: "Monthly franchise royalty for property {$property->property_code}",
                    idempotencyKey: "HTL-ROY-{$contract->contract_code}-".date('Ym'),
                    entries: Posting::lines()
                        ->debit('htl:franchise_receivable:IDR', $royaltyDueIdr)
                        ->credit('htl:royalty_revenue:IDR', $royaltyDueIdr)
                        ->toEntries('IDR'),
                    referenceType: 'HOTEL_FRANCHISE_CONTRACT',
                    referenceId: $contract->id,
                ));
            }

            // 111.4 Rate Parity Violation Detection & Penalty
            $violation = null;
            if ($otaChannel !== null && $otaUndercutRateIdr < $directBarRateIdr) {
                $penaltyIdr = ($directBarRateIdr - $otaUndercutRateIdr) * 2;
                $violationCode = 'RPV-'.strtoupper(bin2hex(random_bytes(4)));

                $violation = RateParityViolation::create([
                    'id' => (string) Str::uuid(),
                    'violation_code' => $violationCode,
                    'property_id' => $property->id,
                    'ota_channel_name' => $otaChannel,
                    'direct_bar_rate_idr' => $directBarRateIdr,
                    'ota_undercut_rate_idr' => $otaUndercutRateIdr,
                    'penalty_levy_idr' => $penaltyIdr,
                    'status' => 'PENALTY_ACCRUED',
                ]);

                $this->ledgerService->post(new PostingDTO(
                    type: 'HOTEL_RATE_PARITY_PENALTY',
                    description: "Rate parity penalty on {$otaChannel} for {$property->property_code}",
                    idempotencyKey: "HTL-RPV-{$violation->violation_code}",
                    entries: Posting::lines()
                        ->debit('htl:ota_penalty_receivable:IDR', $penaltyIdr)
                        ->credit('htl:parity_penalty_revenue:IDR', $penaltyIdr)
                        ->toEntries('IDR'),
                    referenceType: 'RATE_PARITY_VIOLATION',
                    referenceId: $violation->id,
                ));
            }

            $this->audit(
                action: 'hotel.franchise.audit_and_royalty_calculated',
                auditable: $audit,
                oldValues: null,
                newValues: [
                    'property_id' => $property->id,
                    'star_grade' => $starGrade,
                    'listing_status' => $listingStatus,
                    'royalty_due_idr' => $royaltyDueIdr,
                    'parity_violation' => $violation?->violation_code,
                ],
                correlationId: "htl_audit_{$auditCode}",
                impactType: 'franchise_royalty_audit'
            );

            return [
                'audit' => $audit,
                'royalty_due_idr' => $royaltyDueIdr,
                'violation' => $violation,
            ];
        });
    }
}
