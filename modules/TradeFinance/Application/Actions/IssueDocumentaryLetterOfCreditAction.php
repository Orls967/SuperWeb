<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Application\Actions;

use InvalidArgumentException;
use Modules\TradeFinance\Domain\Models\LetterOfCredit;

class IssueDocumentaryLetterOfCreditAction
{
    /**
     * @param array{
     *     applicant_name: string,
     *     beneficiary_name: string,
     *     issuing_bank: string,
     *     advising_bank: string,
     *     currency?: string,
     *     amount_foreign: int,
     *     amount_functional_idr: int,
     *     issue_date: string,
     *     expiry_date: string,
     *     tenor_days?: int,
     *     type?: string,
     *     lc_number?: string
     * } $data
     */
    public function execute(array $data): LetterOfCredit
    {
        if ($data['amount_foreign'] <= 0 || $data['amount_functional_idr'] <= 0) {
            throw new InvalidArgumentException('Letter of credit amount must be greater than zero.');
        }

        if (strtotime($data['expiry_date']) < strtotime($data['issue_date'])) {
            throw new InvalidArgumentException('Expiry date cannot be before issue date.');
        }

        $currency = strtoupper($data['currency'] ?? 'USD');
        $type = $data['type'] ?? 'sight';
        $tenorDays = (int) ($data['tenor_days'] ?? 0);
        $lcNumber = $data['lc_number'] ?? 'LC-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        return LetterOfCredit::create([
            'lc_number' => $lcNumber,
            'type' => $type,
            'issuing_bank' => $data['issuing_bank'],
            'advising_bank' => $data['advising_bank'],
            'applicant_name' => $data['applicant_name'],
            'beneficiary_name' => $data['beneficiary_name'],
            'currency' => $currency,
            'amount_foreign' => $data['amount_foreign'],
            'amount_functional_idr' => $data['amount_functional_idr'],
            'issue_date' => $data['issue_date'],
            'expiry_date' => $data['expiry_date'],
            'tenor_days' => $tenorDays,
            'status' => 'issued',
        ]);
    }
}
