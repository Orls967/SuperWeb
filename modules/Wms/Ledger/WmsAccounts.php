<?php

declare(strict_types=1);

namespace Modules\Wms\Ledger;

use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul WMS (W8-D83 / D41-9).
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class WmsAccounts
{
    public const string INVENTORY_IN_TRANSIT = 'wms:inventory_in_transit:IDR';

    public const string RECEIVING_CLEARING = 'wms:receiving_clearing:IDR';

    public const string ISSUE_CLEARING = 'wms:issue_clearing:IDR';

    public const string INVENTORY_SHRINKAGE = 'wms:inventory_shrinkage:IDR';

    public const string INVENTORY_GAIN = 'wms:inventory_gain:IDR';

    public const string INVENTORY_WRITEOFF = 'wms:inventory_writeoff:IDR';

    public const string TRANSIT_LOSS = 'wms:transit_loss:IDR';

    public const string COST_REVALUATION_VARIANCE = 'wms:cost_revaluation_variance:IDR';

    public const string THREE_PL_RECEIVABLE = 'wms:3pl_receivable:IDR';

    public const string THREE_PL_REVENUE = 'wms:3pl_revenue:IDR';

    public const string OPENING_BALANCE_EQUITY = 'wms:opening_balance_equity:IDR';

    /**
     * Kode akun persediaan per gudang: `wms:inventory:{gudang}:IDR`.
     */
    public static function inventoryForWarehouse(string $warehouseCode): string
    {
        $normalized = strtoupper(trim($warehouseCode));
        if ($normalized === '') {
            $normalized = 'DEFAULT';
        }

        return "wms:inventory:{$normalized}:IDR";
    }

    /**
     * Memastikan akun persediaan per-gudang tersedia di ledger_accounts secara idempoten.
     */
    public static function ensureWarehouseInventoryAccount(string $warehouseCode): string
    {
        $code = self::inventoryForWarehouse($warehouseCode);
        LedgerAccount::firstOrCreate(
            ['code' => $code, 'asset_code' => 'IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'WMS Inventory '.strtoupper(trim($warehouseCode)),
                'kind' => AccountKind::ASSET->value,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );

        return $code;
    }

    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => self::inventoryForWarehouse('DEFAULT'),
                'name' => 'WMS Warehouse Inventory Default',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::INVENTORY_IN_TRANSIT,
                'name' => 'WMS Inventory In Transit',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::RECEIVING_CLEARING,
                'name' => 'WMS Receiving Clearing',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::ISSUE_CLEARING,
                'name' => 'WMS Issue Clearing',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::INVENTORY_SHRINKAGE,
                'name' => 'WMS Inventory Shrinkage Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::INVENTORY_GAIN,
                'name' => 'WMS Inventory Gain Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => self::INVENTORY_WRITEOFF,
                'name' => 'WMS Inventory Write-Off Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::TRANSIT_LOSS,
                'name' => 'WMS Transit Loss Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::COST_REVALUATION_VARIANCE,
                'name' => 'WMS Cost Revaluation Variance',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::THREE_PL_RECEIVABLE,
                'name' => 'WMS 3PL Storage & Handling Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::THREE_PL_REVENUE,
                'name' => 'WMS 3PL Storage & Handling Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => self::OPENING_BALANCE_EQUITY,
                'name' => 'WMS Opening Balance Equity',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
