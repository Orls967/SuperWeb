<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const RENAMES = [
        'HOTEL_SYNDICATION_YIELD_DISTRIBUTION' => 'HTL_SYNDICATE_YIELD_DISTRIB',
        'MINING_RECLAMATION_PROVISION_ACCRUAL' => 'MINING_RECLAMATION_ACCRUAL',
        'MINING_RECLAMATION_PROVISION_RELEASE' => 'MINING_RECLAMATION_RELEASE',
        'RETAIL_MARKETPLACE_SELLER_SETTLEMENT' => 'RETAIL_MKTPLACE_SELLER_SETTLE',
        'ENERGY_GREEN_LEASE_DISCOUNT_ACCRUAL' => 'ENERGY_GREEN_LEASE_DISCOUNT',
        'ENERGY_CONSOLIDATED_UTILITY_INVOICE' => 'ENERGY_CONSOLIDATED_UTILITY',
        'MINING_INTERCOMPANY_RENEWABLE_POWER' => 'MINING_INTERCO_RENEWABLE_POWER',
        'ENERGY_PPA_NET_METERING_SETTLEMENT' => 'ENERGY_PPA_NET_METER_SETTLE',
        'MINING_WATER_MONITORING_SETTLEMENT' => 'MINING_WATER_MONITOR_SETTLE',
        'RETAIL_BUNDLE_SUBSCRIPTION_BILLING' => 'RETAIL_BUNDLE_SUB_BILLING',
        'TICKET_SECONDARY_RESALE_SETTLEMENT' => 'TICKET_SECONDARY_RESALE_SETTLE',
        'HOTEL_RATE_PARITY_PENALTY_ACCRUAL' => 'HOTEL_RATE_PARITY_PENALTY',
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $old => $new) {
            DB::table('bank_ledger_transactions')->where('type', $old)->update(['type' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $old => $new) {
            DB::table('bank_ledger_transactions')->where('type', $new)->update(['type' => $old]);
        }
    }
};
