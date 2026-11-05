<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Syariah\Ledger\SyariahAccounts;
use Modules\Syariah\Ledger\SyariahTransactionTypes;
use RuntimeException;

final class SyariahTreasuryClearingService
{
    public function __construct(
        private readonly ShariahLedgerPoster $ledgerPoster,
    ) {}

    /**
     * Menempatkan likuiditas pasar uang antarbank syariah (PUAS/SiKA).
     *
     * @return array<string, mixed>
     */
    public function placeInterbankLiquidity(
        string $counterpartyBankCode,
        string $contractType, // SIMA / SIKA / WADIAH
        int $principalAmountMinor,
        int $indicativeNisbahBps,
        int $tenorDays,
        Carbon $valueDate
    ): array {
        if ($principalAmountMinor <= 0) {
            throw new RuntimeException('Principal amount must be positive.');
        }

        if ($tenorDays <= 0 || $tenorDays > 365) {
            throw new RuntimeException('Tenor must be between 1 and 365 days.');
        }

        $dealRef = "TRZ-{$contractType}-".hash('sha256', $counterpartyBankCode.microtime());
        $dealRef = substr($dealRef, 0, 32);

        $maturityDate = (clone $valueDate)->addDays($tenorDays);

        // Estimasi imbal hasil indikatif pasar uang syariah (tanpa riba)
        $estimatedYieldMinor = (int) intval(round(($principalAmountMinor * $indicativeNisbahBps * $tenorDays) / (10000.0 * 360.0)));

        return DB::transaction(function () use (
            $dealRef,
            $counterpartyBankCode,
            $contractType,
            $principalAmountMinor,
            $indicativeNisbahBps,
            $tenorDays,
            $valueDate,
            $maturityDate,
            $estimatedYieldMinor
        ) {
            SyariahAccounts::ensureAccountsExist();

            // Mutasi ledger penempatan treasury
            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_WALLET_PAYMENT,
                reference: $dealRef,
                description: "Penempatan likuiditas pasar uang syariah [{$contractType}] ke {$counterpartyBankCode}",
                entries: [
                    [
                        'account_code' => 'syb:wallet_float:IDR',
                        'amount' => -$principalAmountMinor,
                    ],
                    [
                        'account_code' => 'syb:tawarruq_fx_clearing:IDR',
                        'amount' => $principalAmountMinor,
                    ],
                ]
            );

            return [
                'deal_ref' => $dealRef,
                'counterparty_bank_code' => $counterpartyBankCode,
                'contract_type' => $contractType,
                'principal_amount_minor' => $principalAmountMinor,
                'indicative_nisbah_bps' => $indicativeNisbahBps,
                'tenor_days' => $tenorDays,
                'value_date' => $valueDate->toDateString(),
                'maturity_date' => $maturityDate->toDateString(),
                'estimated_yield_minor' => $estimatedYieldMinor,
                'status' => 'ACTIVE',
            ];
        });
    }

    /**
     * Penyelesaian jatuh tempo penempatan treasury syariah.
     *
     * @return array<string, mixed>
     */
    public function settleInterbankMaturity(
        string $dealRef,
        int $principalAmountMinor,
        int $realizedYieldMinor
    ): array {
        return DB::transaction(function () use ($dealRef, $principalAmountMinor, $realizedYieldMinor) {
            SyariahAccounts::ensureAccountsExist();

            $totalProceeds = $principalAmountMinor + $realizedYieldMinor;

            $entries = [
                [
                    'account_code' => 'syb:wallet_float:IDR',
                    'amount' => $totalProceeds,
                ],
                [
                    'account_code' => 'syb:tawarruq_fx_clearing:IDR',
                    'amount' => -$principalAmountMinor,
                ],
            ];

            if ($realizedYieldMinor > 0) {
                $entries[] = [
                    'account_code' => 'syb:ujrah_income:IDR',
                    'amount' => -$realizedYieldMinor,
                ];
            }

            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_WALLET_PAYMENT,
                reference: "SETTLE-{$dealRef}",
                description: "Penyelesaian jatuh tempo pasar uang syariah {$dealRef}",
                entries: $entries
            );

            return [
                'deal_ref' => $dealRef,
                'principal_returned_minor' => $principalAmountMinor,
                'realized_yield_minor' => $realizedYieldMinor,
                'total_proceeds_minor' => $totalProceeds,
                'settled_at' => Carbon::now()->toIso8601String(),
                'status' => 'SETTLED',
            ];
        });
    }
}
