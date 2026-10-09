<?php

namespace Modules\Hospital\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Hospital\Domain\Models\HealthMembership;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\TourismPackage;
use Modules\Hospital\Domain\Models\WearableAdherence;
use RuntimeException;

class MedicalTourismAndMembershipService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 108.1 Book Medical Tourism Package with Multi-Vendor Escrow Deposit Hold
     */
    public function bookTourismPackage(
        Patient $patient,
        string $packageName,
        int $hospitalShareIdr,
        int $hotelShareIdr,
        int $transportShareIdr
    ): TourismPackage {
        $totalPriceIdr = $hospitalShareIdr + $hotelShareIdr + $transportShareIdr;

        return DB::transaction(function () use (
            $patient,
            $packageName,
            $hospitalShareIdr,
            $hotelShareIdr,
            $transportShareIdr,
            $totalPriceIdr
        ) {
            $package = TourismPackage::create([
                'package_code' => 'MTP-'.strtoupper(bin2hex(random_bytes(5))),
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
                entries: [
                    PostingEntryDTO::forCode('hsp:tourism_escrow_deposit:IDR', 'IDR', $totalPriceIdr),
                    PostingEntryDTO::forCode('hsp:tourism_unearned_escrow:IDR', 'IDR', -$totalPriceIdr),
                ],
                referenceType: 'TOURISM_PACKAGE',
                referenceId: (string) $package->id,
            ));

            return $package;
        });
    }

    /**
     * 108.5 & 108.6 (b) Advance Milestone and Final Multi-Vendor Settlement
     */
    public function advanceMilestone(TourismPackage $package, string $nextMilestone): TourismPackage
    {
        // Enforce sequential milestones: BOOKED -> CHECKED_IN_HOSPITAL -> PROCEDURE_COMPLETED -> SETTLED
        $allowedTransitions = [
            'BOOKED' => 'CHECKED_IN_HOSPITAL',
            'CHECKED_IN_HOSPITAL' => 'PROCEDURE_COMPLETED',
            'PROCEDURE_COMPLETED' => 'SETTLED',
        ];

        if (! isset($allowedTransitions[$package->current_milestone]) || $allowedTransitions[$package->current_milestone] !== $nextMilestone) {
            throw new RuntimeException("Invalid milestone transition from {$package->current_milestone} to {$nextMilestone}: milestones must be completed in order");
        }

        return DB::transaction(function () use ($package, $nextMilestone) {
            $package->update([
                'current_milestone' => $nextMilestone,
                'status' => ($nextMilestone === 'SETTLED') ? 'COMPLETED' : 'IN_PROGRESS',
            ]);

            if ($nextMilestone === 'SETTLED') {
                // Rule 108.6 (a): multi-vendor settlement sum = total payment
                $this->ledgerService->post(new PostingDTO(
                    type: 'MEDICAL_TOURISM_FINAL_SETTLEMENT',
                    description: "Final multi-vendor settlement for package {$package->package_code}",
                    idempotencyKey: "HSP-MTP-SETTLE-{$package->package_code}",
                    entries: [
                        PostingEntryDTO::forCode('hsp:tourism_unearned_escrow:IDR', 'IDR', $package->total_price_idr),
                        PostingEntryDTO::forCode('hsp:hospital_revenue:IDR', 'IDR', -$package->hospital_share_idr),
                        PostingEntryDTO::forCode('htl:hotel_vendor_payable:IDR', 'IDR', -$package->hotel_share_idr),
                        PostingEntryDTO::forCode('lgx:transport_vendor_payable:IDR', 'IDR', -$package->transport_share_idr),
                    ],
                    referenceType: 'TOURISM_PACKAGE',
                    referenceId: (string) $package->id,
                ));
            }

            return $package;
        });
    }

    /**
     * 108.3 Create Health Membership & Consume Wellness Credit PTS
     */
    public function createMembership(Patient $patient, string $tier = 'GOLD', int $credits = 1000): HealthMembership
    {
        return HealthMembership::create([
            'membership_number' => 'MEM-'.strtoupper(bin2hex(random_bytes(5))),
            'patient_id' => $patient->id,
            'tier' => $tier,
            'annual_wellness_credits_pts' => $credits,
            'remaining_credits_pts' => $credits,
            'expires_at' => now()->addYear(),
        ]);
    }

    public function redeemMembershipCredits(HealthMembership $membership, int $ptsToRedeem): HealthMembership
    {
        if ($ptsToRedeem > $membership->remaining_credits_pts) {
            throw new RuntimeException('Redemption failed: Requested points exceed remaining membership wellness credits quota');
        }

        $membership->decrement('remaining_credits_pts', $ptsToRedeem);

        return $membership->refresh();
    }

    /**
     * 108.4 Record Wearable IoT adherence and reward points
     */
    public function recordWearableAdherence(
        Patient $patient,
        string $date,
        int $steps,
        int $sleepHours
    ): WearableAdherence {
        $score = min(1.0, ($steps / 10000.0 * 0.6) + ($sleepHours / 8.0 * 0.4));
        $rewardPts = ($score >= 0.8) ? 50 : 10;
        $proofHash = hash('sha256', "WEARABLE-{$patient->mrn}-{$date}-{$steps}-{$sleepHours}");

        return WearableAdherence::create([
            'patient_id' => $patient->id,
            'record_date' => $date,
            'daily_steps' => $steps,
            'sleep_hours' => $sleepHours,
            'adherence_score' => $score,
            'pts_rewarded' => $rewardPts,
            'proof_hash' => $proofHash,
        ]);
    }
}
