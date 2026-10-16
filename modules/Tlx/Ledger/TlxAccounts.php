<?php

declare(strict_types=1);

namespace Modules\Tlx\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Tlx.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class TlxAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'tlx:cloud_chargeback_receivable:IDR',
                'name' => 'Edge Cloud AI GPU Chargeback Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'tlx:cloud_infrastructure_revenue:IDR',
                'name' => 'Edge Cloud Compute & AI Hosting Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'tlx:intercompany_receivable:IDR',
                'name' => 'Smart City IoT Intercompany Fiber Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'tlx:interconnect_clearing:IDR',
                'name' => 'National Wholesale Voice & SMS Interconnect Clearing',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'tlx:interconnect_net_settlement:IDR',
                'name' => 'Peer Carrier Net Interconnect Settlement Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'tlx:iot_service_revenue:IDR',
                'name' => 'Smart City Industrial IoT Telemetry Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
        ];
    }
}
