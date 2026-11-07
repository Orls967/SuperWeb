<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Domain\Models\TimeshareInvestor;
use Modules\Hotel\Domain\Models\TimeshareUnit;
use RuntimeException;

/**
 * Tindakan Distribusi Hasil Sewa Villa Timeshare kepada Pemegang Token Fraksional.
 */
final class DistributeTimeshareRentalYieldAction
{
    public function __construct(
        private readonly ?LedgerService $ledger,
    ) {}

    /**
     * @return array{unit_code: string, gross_rental_idr: int, management_fee_idr: int, net_distributable_idr: int, investors: array<int, array{investor_id: string, token_shares: int, dividend_idr: int}>}
     */
    public function execute(string $unitId, int $grossRentalIncomeIdr): array
    {
        if ($grossRentalIncomeIdr <= 0) {
            throw new InvalidArgumentException('Pendapatan sewa kotor harus lebih besar dari 0.');
        }

        $unit = TimeshareUnit::findOrFail($unitId);
        $investors = TimeshareInvestor::where('timeshare_unit_id', $unit->id)->get();

        if ($investors->isEmpty()) {
            throw new RuntimeException("Tidak ada investor terdaftar untuk unit {$unit->unit_code}");
        }

        // Potongan Management Fee Properti 15%
        $managementFeeRate = 0.15;
        $managementFeeIdr = (int) round($grossRentalIncomeIdr * $managementFeeRate);
        $netDistributableIdr = $grossRentalIncomeIdr - $managementFeeIdr;

        $totalTokenShares = $investors->sum('token_shares');
        if ($totalTokenShares <= 0) {
            throw new RuntimeException("Total token share tidak valid untuk unit {$unit->unit_code}");
        }

        $distributions = [];
        $totalAllocatedDividend = 0;

        foreach ($investors as $inv) {
            $shareRatio = $inv->token_shares / (float) $totalTokenShares;
            $dividend = (int) round($netDistributableIdr * $shareRatio);
            $totalAllocatedDividend += $dividend;

            $distributions[] = [
                'investor_id' => (string) $inv->investor_user_id,
                'token_shares' => (int) $inv->token_shares,
                'dividend_idr' => $dividend,
            ];

            // Posting double entry akuntansi dividen ke rekening perantara investor
            if ($this->ledger && $dividend > 0) {
                $this->ledger->post(new PostingDTO(
                    type: 'HTL_TIMESHARE_DIVIDEND',
                    description: "Timeshare dividend payout for investor {$inv->investor_user_id} unit {$unit->unit_code}",
                    idempotencyKey: "htl:ts_yield:{$unit->id}:{$inv->id}:".date('Ymd'),
                    entries: Posting::lines()
                        ->debit('expense:htl_timeshare_payout:IDR', $dividend)
                        ->credit("investor:dividend:{$inv->investor_user_id}:IDR", $dividend)
                        ->toEntries('IDR'),
                    referenceType: 'htl_timeshare_investors',
                    referenceId: $inv->id,
                ));
            }
        }

        return [
            'unit_code' => $unit->unit_code,
            'gross_rental_idr' => $grossRentalIncomeIdr,
            'management_fee_idr' => $managementFeeIdr,
            'net_distributable_idr' => $netDistributableIdr,
            'investors' => $distributions,
        ];
    }
}
