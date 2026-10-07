<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 147.1: Open Platform Developer Tiers & API Keys
        Schema::create('pe_developer_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('developer_code')->unique();
            $table->string('name');
            $table->string('email');
            $table->string('tier')->default('FREE'); // FREE, PRO, ENTERPRISE
            $table->integer('rate_limit_per_min')->default(60);
            $table->decimal('billing_rate_per_1k_calls', 10, 4)->default(0.0000);
            $table->string('api_key')->unique();
            $table->string('status')->default('ACTIVE'); // ACTIVE, SUSPENDED
            $table->timestamps();
        });

        Schema::create('pe_api_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('developer_code');
            $table->string('endpoint');
            $table->string('version', 10)->default('v3');
            $table->string('line_code', 10);
            $table->integer('calls_count')->default(1);
            $table->decimal('billed_amount', 14, 4)->default(0.0000);
            $table->string('ledger_reference')->nullable();
            $table->timestamps();

            $table->index(['developer_code', 'created_at']);
        });

        // 147.2: Partner App Marketplace & Certifications
        Schema::create('pe_marketplace_apps', function (Blueprint $table) {
            $table->id();
            $table->string('app_code')->unique();
            $table->string('name');
            $table->string('developer_code');
            $table->string('category'); // POS_VENDOR, HRIS, ACCOUNTING, ETC
            $table->decimal('revenue_share_pct', 5, 2)->default(20.00); // 20% platform cut
            $table->string('certification_status')->default('PENDING'); // PENDING, CERTIFIED, REJECTED
            $table->json('certification_checks')->nullable();
            $table->boolean('is_listed')->default(false);
            $table->timestamps();
        });

        // 147.3: White-Label Solution Instances (Multi-tenant isolated)
        Schema::create('pe_white_label_tenants', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_code')->unique();
            $table->string('brand_name');
            $table->string('solution_type'); // HOTEL_PMS, RESTO_POS, HEALTH_EMR
            $table->string('isolated_schema_or_prefix');
            $table->decimal('monthly_subscription_fee', 14, 2)->default(0.00);
            $table->string('subscription_status')->default('ACTIVE');
            $table->timestamps();
        });

        // 147.4: Embedded Finance Integrations
        Schema::create('pe_embedded_finance_txs', function (Blueprint $table) {
            $table->id();
            $table->string('tx_code')->unique();
            $table->string('developer_code');
            $table->string('product_type'); // PAYMENT, ESCROW, INSURANCE
            $table->decimal('gross_amount', 14, 2);
            $table->decimal('platform_fee', 14, 2);
            $table->decimal('partner_fee', 14, 2);
            $table->string('status')->default('COMPLETED');
            $table->timestamps();
        });

        // 147.5: Developer Relations, Deprecation Policies & Bug Bounty
        Schema::create('pe_api_deprecations', function (Blueprint $table) {
            $table->id();
            $table->string('version', 10);
            $table->string('endpoint');
            $table->timestamp('sunset_at');
            $table->string('status')->default('DEPRECATED'); // ACTIVE, DEPRECATED, SUNSET
            $table->timestamps();
        });

        Schema::create('pe_bug_bounty_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_code')->unique();
            $table->string('reporter_email');
            $table->string('severity'); // LOW, MEDIUM, HIGH, CRITICAL
            $table->decimal('bounty_payout', 14, 2)->default(0.00);
            $table->string('payout_status')->default('PENDING'); // PENDING, PAID
            $table->string('ledger_payout_ref')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pe_bug_bounty_reports');
        Schema::dropIfExists('pe_api_deprecations');
        Schema::dropIfExists('pe_embedded_finance_txs');
        Schema::dropIfExists('pe_white_label_tenants');
        Schema::dropIfExists('pe_marketplace_apps');
        Schema::dropIfExists('pe_api_usage_logs');
        Schema::dropIfExists('pe_developer_accounts');
    }
};
