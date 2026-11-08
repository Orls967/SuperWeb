<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\DTO\MiceMasterFolioBillingDTO;
use Modules\Hotel\Domain\Models\MiceContract;

final class HotelMiceMasterFolioBillingService
{
    /**
     * @param  array<int, array{type: string, description: string, amount: float, billed_to: string}>  $chargeItems
     * @param  array<int, array{type: string, amount: float, reference: string}>  $payments
     */
    public function calculateMasterBilling(string $contractId, array $chargeItems = [], array $payments = []): MiceMasterFolioBillingDTO
    {
        $contract = MiceContract::findOrFail($contractId);

        $totalMasterCharges = 0.0;
        $totalIndividualCharges = 0.0;
        $subFolios = [];
        $ledgerEntries = [];

        $baseContractCharge = (float) ($contract->contract_total_value_idr ?? 0);
        $totalMasterCharges += $baseContractCharge;
        $ledgerEntries[] = [
            'entry_id' => 'LE-BASE-'.substr(uniqid(), -6),
            'account' => 'MICE Master Revenue',
            'type' => 'debit',
            'amount' => $baseContractCharge,
            'description' => 'Contracted Event Package & Venue Allotment',
        ];

        foreach ($chargeItems as $item) {
            $amount = (float) ($item['amount'] ?? 0.0);
            $billedTo = (string) ($item['billed_to'] ?? 'master');
            $desc = (string) ($item['description'] ?? 'Charge Item');

            if ($billedTo === 'master') {
                $totalMasterCharges += $amount;
            } else {
                $totalIndividualCharges += $amount;
                $subFolios[] = [
                    'guest_reference' => $billedTo,
                    'description' => $desc,
                    'amount' => $amount,
                ];
            }

            $ledgerEntries[] = [
                'entry_id' => 'LE-'.substr(uniqid(), -6),
                'account' => $billedTo === 'master' ? 'MICE Master Charges' : 'Guest Incidental Folio',
                'type' => 'debit',
                'amount' => $amount,
                'description' => $desc,
            ];
        }

        $totalCreditsDeposits = 0.0;
        foreach ($payments as $payment) {
            $payAmount = (float) ($payment['amount'] ?? 0.0);
            $totalCreditsDeposits += $payAmount;
            $ledgerEntries[] = [
                'entry_id' => 'LE-PAY-'.substr(uniqid(), -6),
                'account' => 'Cash/Bank Clearing',
                'type' => 'credit',
                'amount' => $payAmount,
                'description' => 'MICE Payment / Deposit: '.($payment['reference'] ?? 'Bank Transfer'),
            ];
        }

        $netBalanceDue = round(($totalMasterCharges + $totalIndividualCharges) - $totalCreditsDeposits, 2);
        $isBalanced = abs($netBalanceDue) < 0.01;

        return new MiceMasterFolioBillingDTO(
            masterFolioId: 'MF-'.($contract->event_code ?? 'MICE'),
            contractNumber: (string) ($contract->event_code ?? 'MICE-UNKNOWN'),
            corporatePayerName: (string) ($contract->client_name ?? 'Corporate Client'),
            totalMasterCharges: (int) round($totalMasterCharges),
            totalIndividualCharges: (int) round($totalIndividualCharges),
            totalCreditsDeposits: (int) round($totalCreditsDeposits),
            netBalanceDue: (int) round($netBalanceDue),
            subFolios: $subFolios,
            ledgerEntries: $ledgerEntries,
            isBalanced: $isBalanced
        );
    }
}
