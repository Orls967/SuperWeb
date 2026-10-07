<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Platform Economy Service (Fase 147)
 *
 * Implements:
 *  - Open API Developer Tiers, sandbox, rate limits, usage billing
 *  - App store / marketplace certification and revenue share
 *  - White-label isolated multi-tenant management
 *  - Embedded finance (payment, escrow, insurance) fee split
 *  - Developer relations: deprecation lifecycle & bug bounty payouts
 */
class PlatformEconomyService
{
    public const TIERS = [
        'FREE' => [
            'rate_limit' => 60,
            'rate_per_1k' => 0.00,
        ],
        'PRO' => [
            'rate_limit' => 600,
            'rate_per_1k' => 15.00, // Rp 15 per 1,000 calls
        ],
        'ENTERPRISE' => [
            'rate_limit' => 6000,
            'rate_per_1k' => 10.00, // Rp 10 per 1,000 calls bulk
        ],
    ];

    /**
     * Register a developer account.
     */
    public function registerDeveloper(string $name, string $email, string $tier = 'FREE'): object
    {
        $tierUpper = strtoupper($tier);
        $tierConfig = self::TIERS[$tierUpper] ?? self::TIERS['FREE'];
        $code = 'DEV-'.strtoupper(Str::random(8));
        $apiKey = 'agy_live_'.Str::random(32);

        $id = DB::table('pe_developer_accounts')->insertGetId([
            'developer_code' => $code,
            'name' => $name,
            'email' => $email,
            'tier' => $tierUpper,
            'rate_limit_per_min' => $tierConfig['rate_limit'],
            'billing_rate_per_1k_calls' => $tierConfig['rate_per_1k'],
            'api_key' => $apiKey,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('pe_developer_accounts')->find($id);
    }

    /**
     * Check rate limit.
     */
    public function checkRateLimit(string $developerCode, int $currentMinuteRequests): bool
    {
        $dev = DB::table('pe_developer_accounts')->where('developer_code', $developerCode)->first();
        if (! $dev || $dev->status !== 'ACTIVE') {
            return false;
        }

        return $currentMinuteRequests <= $dev->rate_limit_per_min;
    }

    /**
     * Record API usage & calculate billing.
     */
    public function recordApiUsage(string $developerCode, string $endpoint, string $lineCode, int $calls = 1, string $version = 'v3'): object
    {
        $dev = DB::table('pe_developer_accounts')->where('developer_code', $developerCode)->first();
        $ratePer1k = (float) ($dev->billing_rate_per_1k_calls ?? 0.0);
        $billedAmount = ($calls / 1000.0) * $ratePer1k;

        $id = DB::table('pe_api_usage_logs')->insertGetId([
            'developer_code' => $developerCode,
            'endpoint' => $endpoint,
            'version' => $version,
            'line_code' => strtoupper($lineCode),
            'calls_count' => $calls,
            'billed_amount' => $billedAmount,
            'ledger_reference' => 'LEDGER-API-'.strtoupper(Str::random(8)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('pe_api_usage_logs')->find($id);
    }

    /**
     * Submit and certify marketplace app.
     */
    public function registerMarketplaceApp(string $developerCode, string $name, string $category, float $revSharePct = 20.0): object
    {
        $appCode = 'APP-'.strtoupper(Str::random(8));

        $id = DB::table('pe_marketplace_apps')->insertGetId([
            'app_code' => $appCode,
            'name' => $name,
            'developer_code' => $developerCode,
            'category' => strtoupper($category),
            'revenue_share_pct' => $revSharePct,
            'certification_status' => 'PENDING',
            'certification_checks' => json_encode([]),
            'is_listed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('pe_marketplace_apps')->find($id);
    }

    /**
     * Run certification suite for marketplace app.
     */
    public function certifyApp(string $appCode, bool $passesSecurity, bool $passesRegression): object
    {
        $checks = [
            'security_scan' => $passesSecurity,
            'regression_suite' => $passesRegression,
            'verified_at' => now()->toIso8601String(),
        ];

        $passed = $passesSecurity && $passesRegression;

        DB::table('pe_marketplace_apps')->where('app_code', $appCode)->update([
            'certification_status' => $passed ? 'CERTIFIED' : 'REJECTED',
            'certification_checks' => json_encode($checks),
            'is_listed' => $passed,
            'updated_at' => now(),
        ]);

        return (object) DB::table('pe_marketplace_apps')->where('app_code', $appCode)->first();
    }

    /**
     * Create isolated white-label tenant instance.
     */
    public function createWhiteLabelTenant(string $brandName, string $solutionType, float $subscriptionFee): object
    {
        $tenantCode = 'WL-'.strtoupper(Str::random(8));
        $prefix = strtolower(Str::slug($brandName)).'_schema';

        $id = DB::table('pe_white_label_tenants')->insertGetId([
            'tenant_code' => $tenantCode,
            'brand_name' => $brandName,
            'solution_type' => strtoupper($solutionType),
            'isolated_schema_or_prefix' => $prefix,
            'monthly_subscription_fee' => $subscriptionFee,
            'subscription_status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('pe_white_label_tenants')->find($id);
    }

    /**
     * Execute embedded finance transaction with fee split.
     */
    public function processEmbeddedFinance(string $developerCode, string $productType, float $grossAmount, float $platformFeePct = 2.5): object
    {
        $txCode = 'EF-'.strtoupper(Str::random(10));
        $platformFee = round($grossAmount * ($platformFeePct / 100.0), 2);
        $partnerFee = round($grossAmount - $platformFee, 2);

        $id = DB::table('pe_embedded_finance_txs')->insertGetId([
            'tx_code' => $txCode,
            'developer_code' => $developerCode,
            'product_type' => strtoupper($productType),
            'gross_amount' => $grossAmount,
            'platform_fee' => $platformFee,
            'partner_fee' => $partnerFee,
            'status' => 'COMPLETED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('pe_embedded_finance_txs')->find($id);
    }

    /**
     * Register API deprecation policy.
     */
    public function registerDeprecation(string $version, string $endpoint, Carbon $sunsetAt): object
    {
        $id = DB::table('pe_api_deprecations')->insertGetId([
            'version' => $version,
            'endpoint' => $endpoint,
            'sunset_at' => $sunsetAt,
            'status' => now()->greaterThan($sunsetAt) ? 'SUNSET' : 'DEPRECATED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('pe_api_deprecations')->find($id);
    }

    /**
     * Check if endpoint version is accessible.
     */
    public function isEndpointAvailable(string $version, string $endpoint): bool
    {
        $record = DB::table('pe_api_deprecations')
            ->where('version', $version)
            ->where('endpoint', $endpoint)
            ->first();

        if (! $record) {
            return true; // Not deprecated, active
        }

        return now()->lessThan(Carbon::parse($record->sunset_at));
    }

    /**
     * Report bug bounty and issue ledger payout.
     */
    public function reportBugBounty(string $reporterEmail, string $severity, float $payoutAmount): object
    {
        $reportCode = 'BB-'.strtoupper(Str::random(8));
        $ledgerRef = 'PAYOUT-BOUNTY-'.strtoupper(Str::random(8));

        $id = DB::table('pe_bug_bounty_reports')->insertGetId([
            'report_code' => $reportCode,
            'reporter_email' => $reporterEmail,
            'severity' => strtoupper($severity),
            'bounty_payout' => $payoutAmount,
            'payout_status' => 'PAID',
            'ledger_payout_ref' => $ledgerRef,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('pe_bug_bounty_reports')->find($id);
    }

    /**
     * Platform Economy Audit (`api:audit` quality gate).
     */
    public function audit(): array
    {
        $discrepancies = 0;

        // 1. Verify unbilled or negative usage logs
        $invalidUsage = DB::table('pe_api_usage_logs')
            ->where('billed_amount', '<', 0)
            ->count();
        $discrepancies += $invalidUsage;

        // 2. Verify embedded finance balances (gross = platform_fee + partner_fee)
        $txs = DB::table('pe_embedded_finance_txs')->get();
        foreach ($txs as $tx) {
            $sum = round((float) $tx->platform_fee + (float) $tx->partner_fee, 2);
            if (abs($sum - (float) $tx->gross_amount) > 0.01) {
                $discrepancies++;
            }
        }

        // 3. Verify white label isolated namespaces exist
        $invalidTenants = DB::table('pe_white_label_tenants')
            ->whereNull('isolated_schema_or_prefix')
            ->orWhere('isolated_schema_or_prefix', '')
            ->count();
        $discrepancies += $invalidTenants;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_developers' => DB::table('pe_developer_accounts')->count(),
            'total_apps' => DB::table('pe_marketplace_apps')->count(),
            'total_tenants' => DB::table('pe_white_label_tenants')->count(),
            'total_embedded_txs' => $txs->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
