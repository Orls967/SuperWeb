<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hospital\Domain\Models\HealthMembership;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\TourismPackage;
use Modules\Hospital\Domain\Models\WearableAdherence;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class BookMedicalTourismPackageAndWellnessMembershipAction extends BaseAction
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * Book medical tourism package with multi-vendor escrow hold, sequential milestone handling, and wellness membership.
     *
     * @return array{package: TourismPackage, membership: ?HealthMembership, wearable_adherence: ?WearableAdherence, total_price_idr: int}
     */
    public function handle(
        int $patientId,
        string $packageName,
        int $hospitalShareIdr,
        int $hotelShareIdr,
        int $transportShareIdr,
        ?string $membershipTier = null,
        int $adherenceScore = 85,
        ?string $advanceToMilestone = null
    ): array {
        $totalPriceIdr = $hospitalShareIdr + $hotelShareIdr + $transportShareIdr;

        return DB::transaction(function () use (
            $patientId,
            $packageName,
            $hospitalShareIdr,
            $hotelShareIdr,
            $transportShareIdr,
            $totalPriceIdr,
            $membershipTier,
            $adherenceScore,
            $advanceToMilestone
        ) {
            $patient = Patient::lockForUpdate()->findOrFail($patientId);

            $packageCount = TourismPackage::count() + 1;
            $packageCode = sprintf('MTP-%06d', $packageCount);

            $package = TourismPackage::create([
                'package_code' => $packageCode,
                'patient_id' => $patient->id,
                'package_name' => $packageName,
                'total_price_idr' => $totalPriceIdr,
                'hospital_share_idr' => $hospitalShareIdr,
                'hotel_share_idr' => $hotelShareIdr,
                'transport_share_idr' => $transportShareIdr,
                'current_milestone' => 'BOOKED',
                'status' => 'ESCROWED',
            ]);

            // Escrow deposit in ledger: Debit Cash, Credit Escrow Liability
            $this->ledgerService->post(new PostingDTO(
                type: 'MEDICAL_TOURISM_ESCROW_DEPOSIT',
                description: "Escrow deposit for medical tourism package {$package->package_code}",
                idempotencyKey: "HSP-MTP-DEP-{$package->package_code}",
                entries: Posting::lines()
                    ->debit('hsp:tourism_escrow_deposit:IDR', $totalPriceIdr)
                    ->credit('hsp:tourism_unearned_escrow:IDR', $totalPriceIdr)
                    ->toEntries('IDR'),
                referenceType: 'TOURISM_PACKAGE',
                referenceId: (string) $package->id,
            ));

            // Sequential milestone progression if requested
            if ($advanceToMilestone !== null) {
                $allowedTransitions = [
                    'BOOKED' => 'CHECKED_IN_HOSPITAL',
                    'CHECKED_IN_HOSPITAL' => 'PROCEDURE_COMPLETED',
                    'PROCEDURE_COMPLETED' => 'SETTLED',
                ];

                if (! isset($allowedTransitions[$package->current_milestone]) || $allowedTransitions[$package->current_milestone] !== $advanceToMilestone) {
                    throw new RuntimeException("Invalid milestone transition from {$package->current_milestone} to {$advanceToMilestone}: milestones must be completed in order");
                }

                $package->update([
                    'current_milestone' => $advanceToMilestone,
                    'status' => ($advanceToMilestone === 'SETTLED') ? 'COMPLETED' : 'IN_PROGRESS',
                ]);

                if ($advanceToMilestone === 'SETTLED') {
                    // Rule 108.6 (a): multi-vendor settlement sum = total payment
                    $this->ledgerService->post(new PostingDTO(
                        type: 'MEDICAL_TOURISM_FINAL_SETTLEMENT',
                        description: "Final multi-vendor settlement for package {$package->package_code}",
                        idempotencyKey: "HSP-MTP-SETTLE-{$package->package_code}",
                        entries: Posting::lines()
                            ->debit('hsp:tourism_unearned_escrow:IDR', $totalPriceIdr)
                            ->credit('hsp:hospital_package_revenue:IDR', $hospitalShareIdr)
                            ->credit('htl:hotel_vendor_payable:IDR', $hotelShareIdr)
                            ->credit('lgx:transport_vendor_payable:IDR', $transportShareIdr)
                            ->toEntries('IDR'),
                        referenceType: 'TOURISM_PACKAGE',
                        referenceId: (string) $package->id,
                    ));
                }
            }

            // 108.3 Health membership enrollment
            $membership = null;
            if ($membershipTier !== null) {
                $membershipNumber = sprintf('HLT-MEM-%06d', $patient->id);
                $membership = HealthMembership::updateOrCreate(
                    ['patient_id' => $patient->id],
                    [
                        'membership_number' => $membershipNumber,
                        'tier' => strtoupper($membershipTier),
                        'annual_wellness_credits_pts' => 500,
                        'remaining_credits_pts' => 500,
                        'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year')),
                    ]
                );
            }

            // 108.4 Wearable IoT adherence
            $wearable = null;
            if ($adherenceScore > 0) {
                $rewardPts = ($adherenceScore >= 80) ? 100 : 25;
                $proofHash = hash('sha256', "WEARABLE-{$patient->id}-".date('Y-m-d')."-{$adherenceScore}");

                $wearable = WearableAdherence::create([
                    'patient_id' => $patient->id,
                    'record_date' => date('Y-m-d'),
                    'daily_steps' => 10500,
                    'sleep_hours' => 7.5,
                    'adherence_score' => $adherenceScore,
                    'pts_rewarded' => $rewardPts,
                    'proof_hash' => $proofHash,
                ]);
            }

            $this->audit(
                action: 'hospital.medical_tourism.package_booked',
                auditable: $package,
                oldValues: null,
                newValues: [
                    'package_code' => $packageCode,
                    'patient_id' => $patient->id,
                    'total_price_idr' => $totalPriceIdr,
                    'milestone' => $package->current_milestone,
                    'membership_tier' => $membershipTier,
                ],
                correlationId: "hsp_mtp_{$packageCode}",
                impactType: 'medical_tourism_booking'
            );

            return [
                'package' => $package,
                'membership' => $membership,
                'wearable_adherence' => $wearable,
                'total_price_idr' => $totalPriceIdr,
            ];
        });
    }
}
