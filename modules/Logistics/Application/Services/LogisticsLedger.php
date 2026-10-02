<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Services;

use Brick\Math\BigDecimal;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;

/**
 * Pintu tunggal posting jurnal logistik. Menjamin akun sistem `lgx:*` ada sebelum diposting dan
 * memakai konvensi tanda ledger: nilai positif = kredit, negatif = debit (total per jurnal = 0).
 */
class LogisticsLedger
{
    public const UNEARNED_FREIGHT = 'lgx:unearned_freight';

    public const FREIGHT_REVENUE = 'lgx:freight_revenue';

    public const COD_FEE_REVENUE = 'lgx:cod_fee_revenue';

    public const CARRIER_COST = 'lgx:carrier_cost';

    public const CLAIMS_EXPENSE = 'lgx:claims_expense';

    public const DD_REVENUE = 'lgx:dd_revenue';

    public const CUSTOMS_DUTY_PAYABLE = 'lgx:customs_duty_payable';

    public const FUEL_EXPENSE = 'lgx:fuel_expense';

    public const BANK_CLEARING = 'clearing:external:IDR';

    public function __construct(
        private readonly Ledger $ledger
    ) {}

    public static function arCode(int $shipperId): string
    {
        return "lgx:ar:{$shipperId}";
    }

    public static function codPayableCode(int $shipperId): string
    {
        return "lgx:cod_payable:{$shipperId}";
    }

    public static function driverCashCode(int $driverId): string
    {
        return "lgx:cod_cash:driver:{$driverId}";
    }

    public static function hubCashCode(int $hubId): string
    {
        return "lgx:cod_cash:hub:{$hubId}";
    }

    public static function carrierPayableCode(int $carrierId): string
    {
        return "lgx:carrier_payable:{$carrierId}";
    }

    /**
     * Pastikan akun sistem logistik tersedia; kode wallet/clearing milik modul lain tidak dibuat di sini.
     */
    public function ensureAccount(string $code): void
    {
        if (! str_starts_with($code, 'lgx:')) {
            return;
        }

        [$name, $kind] = match (true) {
            $code === self::UNEARNED_FREIGHT => ['Pendapatan Diterima di Muka (Unearned Freight)', AccountKind::LIABILITY],
            $code === self::FREIGHT_REVENUE => ['Pendapatan Freight & Logistik', AccountKind::REVENUE],
            $code === self::COD_FEE_REVENUE => ['Pendapatan Fee COD', AccountKind::REVENUE],
            $code === self::CARRIER_COST => ['Beban Carrier Subkontrak', AccountKind::EXPENSE],
            $code === self::CLAIMS_EXPENSE => ['Beban Klaim Kargo', AccountKind::EXPENSE],
            $code === self::DD_REVENUE => ['Pendapatan Demurrage & Detention', AccountKind::REVENUE],
            $code === self::CUSTOMS_DUTY_PAYABLE => ['Titipan Bea Cukai (Utang ke Negara)', AccountKind::LIABILITY],
            $code === self::FUEL_EXPENSE => ['Beban Bahan Bakar Armada', AccountKind::EXPENSE],
            str_starts_with($code, 'lgx:ar:') => ['Piutang B2B Shipper #'.substr($code, 7), AccountKind::ASSET],
            str_starts_with($code, 'lgx:cod_payable:') => ['Titipan COD Shipper #'.substr($code, 16), AccountKind::LIABILITY],
            str_starts_with($code, 'lgx:cod_cash:driver:') => ['Kas COD di Tangan Driver #'.substr($code, 20), AccountKind::CASH],
            str_starts_with($code, 'lgx:cod_cash:hub:') => ['Kas COD di Hub #'.substr($code, 17), AccountKind::CASH],
            str_starts_with($code, 'lgx:carrier_payable:') => ['Utang Carrier #'.substr($code, 20), AccountKind::AP],
            default => throw new \InvalidArgumentException("Kode akun logistik tidak dikenal: {$code}"),
        };

        LedgerAccount::firstOrCreate(
            ['code' => $code, 'asset_code' => 'IDR'],
            ['name' => $name, 'kind' => $kind->value, 'allow_negative' => true]
        );
    }

    /**
     * Posting jurnal IDR. $entries: daftar [kode akun | id akun, jumlah bertanda].
     *
     * @param  array<int, array{0: string|int, 1: int|string|BigDecimal}>  $entries
     */
    public function post(
        TransactionType $type,
        string $description,
        string $idempotencyKey,
        array $entries,
        ?string $referenceType = null,
        string|int|null $referenceId = null,
        ?int $createdBy = null,
    ): LedgerTransaction {
        $dtos = [];
        foreach ($entries as [$account, $amount]) {
            $amount = $amount instanceof BigDecimal ? $amount : BigDecimal::of((string) $amount);
            if ($amount->isZero()) {
                continue;
            }

            if (is_int($account)) {
                $dtos[] = PostingEntryDTO::forAccount($account, 'IDR', $amount);

                continue;
            }

            $this->ensureAccount($account);
            $dtos[] = PostingEntryDTO::forCode($account, 'IDR', $amount);
        }

        return $this->ledger->post(new PostingDTO(
            type: $type->value,
            description: $description,
            idempotencyKey: $idempotencyKey,
            entries: $dtos,
            referenceType: $referenceType,
            referenceId: $referenceId,
            createdBy: $createdBy,
            postedAt: now(),
        ));
    }

    public function balance(string $code): BigDecimal
    {
        $account = LedgerAccount::where('code', $code)->where('asset_code', 'IDR')->first();

        return BigDecimal::of($account?->cached_balance ?: '0');
    }
}
