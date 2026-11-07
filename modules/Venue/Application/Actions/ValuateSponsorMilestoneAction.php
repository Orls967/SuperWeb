<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Application\Services\VenueEconomyLedgerService;
use Modules\Venue\Domain\Algorithms\SponsorExposureInput;
use Modules\Venue\Domain\Algorithms\SponsorExposureValuator;
use Modules\Venue\Domain\Models\SponsorMilestone;

final class ValuateSponsorMilestoneAction extends BaseAction
{
    public function __construct(
        private readonly SponsorExposureValuator $valuator,
        private readonly VenueEconomyLedgerService $ledgerService
    ) {}

    public function execute(
        int $milestoneId,
        SponsorExposureInput $input
    ): array {
        return DB::transaction(function () use ($milestoneId, $input) {
            $milestone = SponsorMilestone::query()->with('deal')->lockForUpdate()->find($milestoneId);
            if (! $milestone) {
                throw new InvalidArgumentException("Milestone sponsor ID {$milestoneId} tidak ditemukan.");
            }

            // 1. Hitung Eksposur Terbobot & Rasio Pembayaran via Algoritma V8
            $valuation = $this->valuator->valuate($input);

            // 2. Validasi Sertifikat Komputasi
            $valuation->certificate->isValid();

            // 3. Update Milestone
            $status = ($valuation->payoutRatioBps >= 7_000) ? 'verified' : 'under_delivery_penalty';

            $milestone->update([
                'delivered_impressions' => $valuation->deliveredImpressions,
                'payout_ratio_bps' => $valuation->payoutRatioBps,
                'milestone_payout_idr' => $valuation->milestonePayoutIdr,
                'status' => $status,
            ]);

            // 4. Update Akumulasi di Deal Induk
            $deal = $milestone->deal;
            $deal->increment('delivered_impressions', $valuation->deliveredImpressions);

            // 5. Posting Akrual Buku Besar bila Payout Berhasil Diverifikasi
            if ($valuation->milestonePayoutIdr > 0) {
                $this->ledgerService->postSponsorMilestoneAccrual(
                    dealCode: "SPON-{$deal->id}",
                    milestoneName: $milestone->milestone_name,
                    milestonePayoutIdr: $valuation->milestonePayoutIdr
                );

                $milestone->update(['status' => 'invoiced']);
            }

            return [
                'milestone' => $milestone,
                'delivered_impressions' => $valuation->deliveredImpressions,
                'achievement_bps' => $valuation->achievementBps,
                'payout_ratio_bps' => $valuation->payoutRatioBps,
                'exposure_value_idr' => $valuation->exposureValueIdr,
                'milestone_payout_idr' => $valuation->milestonePayoutIdr,
                'status' => $milestone->status,
            ];
        });
    }
}
