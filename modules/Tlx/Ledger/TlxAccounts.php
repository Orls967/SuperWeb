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
            [
                'code' => 'tlx:network_slice_revenue:IDR',
                'name' => 'Enterprise 5G Network Slice Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'tlx:telecom_receivable:IDR',
                'name' => 'Telecom Enterprise Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'tlx:colocation_revenue:IDR',
                'name' => 'Data Center Colocation Rack Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'tlx:sla_penalty_expense:IDR',
                'name' => 'Data Center PUE SLA Penalty Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'tlx:sla_penalty_payable:IDR',
                'name' => 'Data Center SLA Penalty Credit Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'tlx:intercompany_payable:IDR',
                'name' => 'Intercompany Telecom Payable (Buyer Book)',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'tlx:intercompany_telecom_expense:IDR',
                'name' => 'Intercompany Telecom Connectivity Expense (Buyer Book)',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'tlx:power_revenue:IDR',
                'name' => 'Data Center Excess Power Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
        ];
    }
}
