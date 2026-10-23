<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Application\Actions;

use InvalidArgumentException;
use Modules\TradeFinance\Domain\Models\TradeLoan;

class DiscountSupplyChainFinanceInvoiceAction
{
    /**
     * @param array{
     *     borrower_name: string,
     *     principal_amount_idr: int,
     *     annual_rate_percent: float,
     *     tenor_days: int,
     *     disbursed_at?: string,
     *     due_date?: string,
     *     loan_number?: string
     * } $data
     * @return array{
     *     loan: TradeLoan,
     *     principal_amount_idr: int,
     *     discount_charge_idr: int,
     *     net_disbursement_idr: int,
     *     annual_rate_percent: float,
     *     tenor_days: int
     * }
     */
    public function execute(array $data): array
    {
        $principal = $data['principal_amount_idr'];
        $ratePercent = $data['annual_rate_percent'];
        $tenorDays = $data['tenor_days'];

        if ($principal <= 0) {
            throw new InvalidArgumentException('Principal amount must be greater than zero.');
        }

        if ($ratePercent < 0) {
            throw new InvalidArgumentException('Interest rate percent cannot be negative.');
        }

        if ($tenorDays <= 0) {
            throw new InvalidArgumentException('Tenor days must be positive.');
        }

        // Integer minor unit calculation using 360-day commercial basis
        $discountCharge = (int) round(($principal * ($ratePercent / 100.0) * $tenorDays) / 360.0);
        $netDisbursement = $principal - $discountCharge;

        $disbursedAt = $data['disbursed_at'] ?? date('Y-m-d');
        $dueDate = $data['due_date'] ?? date('Y-m-d', strtotime("+$tenorDays days", strtotime($disbursedAt)));
        $loanNumber = $data['loan_number'] ?? 'SCF-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        $loan = TradeLoan::create([
            'loan_number' => $loanNumber,
            'facility_type' => 'scf',
            'borrower_name' => $data['borrower_name'],
            'principal_amount_idr' => $principal,
            'interest_rate_percent' => $ratePercent,
            'disbursed_at' => $disbursedAt,
            'due_date' => $dueDate,
            'repaid_amount_idr' => 0,
            'status' => 'disbursed',
        ]);

        return [
            'loan' => $loan,
            'principal_amount_idr' => $principal,
            'discount_charge_idr' => $discountCharge,
            'net_disbursement_idr' => $netDisbursement,
            'annual_rate_percent' => $ratePercent,
            'tenor_days' => $tenorDays,
        ];
    }
}
