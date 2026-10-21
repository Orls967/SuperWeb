<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Services;

use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Domain\Models\DestinationPackage;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioItem;
use Modules\Hotel\Domain\Models\TimeshareInvestor;
use Modules\Hotel\Domain\Models\TimeshareUnit;
use RuntimeException;

class HotelFolioAndPackageService
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Add addon consumption item to folio (F&B room service, spa, minibar, etc.)
     */
    public function addFolioItem(string $folioId, string $category, string $description, int $amountIdr): HotelFolioItem
    {
        $folio = HotelFolio::findOrFail($folioId);

        $item = HotelFolioItem::create([
            'id' => (string) Str::uuid(),
            'folio_id' => $folio->id,
            'item_category' => $category,
            'description' => $description,
            'amount_idr' => $amountIdr,
        ]);

        $folio->increment('total_addon_charges', $amountIdr);
        $folio->update([
            'outstanding_balance' => ($folio->total_room_charges + $folio->total_addon_charges) - $folio->total_paid,
        ]);

        return $item;
    }

    /**
     * Distribute timeshare rental yield dividend pro-rata to token holders
     */
    public function distributeTimeshareDailyDividend(string $timeshareUnitId, int $netRentalIncome): array
    {
        $unit = TimeshareUnit::findOrFail($timeshareUnitId);
        $investors = TimeshareInvestor::where('timeshare_unit_id', $timeshareUnitId)->get();

        $totalShares = $investors->sum('token_shares');
        if ($totalShares !== $unit->total_token_shares) {
            throw new RuntimeException("Invariant violation: sum of investor shares ({$totalShares}) != total token shares ({$unit->total_token_shares})");
        }

        $distributions = [];
        $allocatedAmount = 0;

        foreach ($investors as $investor) {
            $shareFraction = $investor->token_shares / $unit->total_token_shares;
            $dividend = (int) floor($netRentalIncome * $shareFraction);
            $allocatedAmount += $dividend;

            $distributions[] = [
                'investor_id' => $investor->investor_user_id,
                'token_shares' => $investor->token_shares,
                'dividend_idr' => $dividend,
            ];

            if ($this->ledger && $dividend > 0) {
                $this->ledger->post(new PostingDTO(
                    type: 'HTL_TIMESHARE_DIVIDEND',
                    description: "Timeshare dividend for investor {$investor->investor_user_id} unit {$unit->unit_code}",
                    idempotencyKey: "hotel:timeshare_dividend:{$unit->id}:{$investor->id}:daily",
                    entries: Posting::lines()
                        ->debit('expense:htl_timeshare_payout:IDR', $dividend)
                        ->credit("investor:dividend:{$investor->investor_user_id}:IDR", $dividend)
                        ->toEntries('IDR'),
                    referenceType: 'htl_timeshare_investors',
                    referenceId: $investor->id,
                ));
            }
        }

        return [
            'unit_code' => $unit->unit_code,
            'net_income' => $netRentalIncome,
            'total_allocated' => $allocatedAmount,
            'investors' => $distributions,
        ];
    }

    /**
     * Settle destination package multi-vendor escrow
     */
    public function settleDestinationPackage(string $packageId, string $customerUserId): array
    {
        $package = DestinationPackage::findOrFail($packageId);
        $vendorShares = $package->vendor_shares;

        $totalAllocated = array_sum(array_column($vendorShares, 'amount'));
        if ($totalAllocated !== $package->total_price_idr) {
            throw new RuntimeException("Destination package invariant broken: allocated ({$totalAllocated}) != total price ({$package->total_price_idr})");
        }

        if ($this->ledger) {
            $lines = Posting::lines()->debit('escrow:destination_package:IDR', $package->total_price_idr);
            foreach ($vendorShares as $share) {
                $account = $share['account'] ?? "vendor:settlement:{$share['vendor']}:IDR";
                $lines->credit($account, (int) $share['amount']);
            }

            $this->ledger->post(new PostingDTO(
                type: 'HTL_DESTINATION_PKG_SETTLE',
                description: "Destination package settlement for {$package->package_code}",
                idempotencyKey: "hotel:package_settle:{$package->id}",
                entries: $lines->toEntries('IDR'),
                referenceType: 'htl_destination_packages',
                referenceId: $package->id,
            ));
        }

        return [
            'package_code' => $package->package_code,
            'total_paid' => $package->total_price_idr,
            'settled_vendors' => count($vendorShares),
        ];
    }

    /**
     * Corporate billing settlement (invoicing monthly to company as Accounts Receivable / City Ledger)
     */
    public function settleCorporateBilling(string $folioId, string $corporateId, int $amountIdr): HotelFolio
    {
        $folio = HotelFolio::findOrFail($folioId);

        $folio->increment('total_paid', $amountIdr);
        $newBalance = max(0, ($folio->total_room_charges + $folio->total_addon_charges) - $folio->total_paid);

        $folio->update([
            'outstanding_balance' => $newBalance,
            'status' => $newBalance === 0 ? 'settled' : 'open',
        ]);

        if ($this->ledger && $amountIdr > 0) {
            $this->ledger->post(new PostingDTO(
                type: 'HOTEL_FOLIO_SETTLEMENT',
                description: "Corporate billing AR for company {$corporateId} folio {$folio->id}",
                idempotencyKey: "hotel:corp_billing:{$folio->id}:{$corporateId}:{$amountIdr}",
                entries: Posting::lines()
                    ->debit('htl:city_ledger_receivable:IDR', $amountIdr)
                    ->credit('htl:room_revenue:IDR', $amountIdr)
                    ->toEntries('IDR'),
                referenceType: 'htl_folios',
                referenceId: $folio->id,
            ));
        }

        return $folio->fresh();
    }
}
