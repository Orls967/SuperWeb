<?php

declare(strict_types=1);

namespace Modules\Aviation\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan akun standar buku besar untuk modul Aviation (Lini 26, D177-8).
 */
final class AviationAccounts
{
    public const PASSENGER_UNEARNED = 'avi:passenger_unearned:IDR';

    public const TICKET_REVENUE = 'avi:ticket_revenue:IDR';

    public const TAX_PAYABLE = 'avi:tax_payable:IDR';

    public const REFUND_PAYABLE = 'avi:refund_payable:IDR';

    public const CARGO_RECEIVABLE = 'avi:cargo_receivable:IDR';

    public const CARGO_REVENUE = 'avi:cargo_revenue:IDR';

    public const MAINTENANCE_EXPENSE = 'avi:maintenance_expense:IDR';

    public const MAINTENANCE_PAYABLE = 'avi:maintenance_payable:IDR';

    // Akun Fase 178
    public const CARE_EXPENSE = 'avi:care_expense:IDR';

    public const CARE_PAYABLE = 'avi:care_payable:IDR';

    public const MILES_LIABILITY = 'avi:miles_liability:IDR';

    public const MILES_PROGRAM_EXPENSE = 'avi:miles_program_expense:IDR';

    public const MILES_BREAKAGE_REVENUE = 'avi:miles_breakage_revenue:IDR';

    public const CODESHARE_RECEIVABLE = 'avi:codeshare_receivable:IDR';

    public const CODESHARE_PAYABLE = 'avi:codeshare_payable:IDR';

    public const INSURANCE_RECOVERY_RECEIVABLE = 'avi:insurance_recovery_receivable:IDR';

    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => self::PASSENGER_UNEARNED,
                'name' => 'Aviation Passenger Unearned Revenue',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::TICKET_REVENUE,
                'name' => 'Aviation Ticket Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::TAX_PAYABLE,
                'name' => 'Aviation Passenger Airport & Gov Tax Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::REFUND_PAYABLE,
                'name' => 'Aviation Passenger Ticket Refund Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::CARGO_RECEIVABLE,
                'name' => 'Aviation Air Cargo Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::CARGO_REVENUE,
                'name' => 'Aviation Air Cargo Freight Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::MAINTENANCE_EXPENSE,
                'name' => 'Aviation Aircraft Maintenance Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::MAINTENANCE_PAYABLE,
                'name' => 'Aviation Aircraft Maintenance Accounts Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::CARE_EXPENSE,
                'name' => 'Aviation Passenger Care Disruption Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::CARE_PAYABLE,
                'name' => 'Aviation Passenger Care Voucher Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::MILES_LIABILITY,
                'name' => 'Aviation Frequent Flyer Miles Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::MILES_PROGRAM_EXPENSE,
                'name' => 'Aviation Frequent Flyer Program Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::MILES_BREAKAGE_REVENUE,
                'name' => 'Aviation Expired Miles Breakage Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::CODESHARE_RECEIVABLE,
                'name' => 'Aviation Codeshare Settlement Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::CODESHARE_PAYABLE,
                'name' => 'Aviation Codeshare Partner Settlement Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::INSURANCE_RECOVERY_RECEIVABLE,
                'name' => 'Aviation Disruption Insurance Recovery Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
