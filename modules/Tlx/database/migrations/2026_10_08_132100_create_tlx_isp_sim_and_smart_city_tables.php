<?php

declare(strict_types=1);

namespace Modules\Tlx\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 132.1 ISP retail subscriptions
        Schema::create('tlx_isp_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subscription_code')->unique();
            $table->string('customer_id'); // customer party
            $table->string('plan_type'); // HOME_BROADBAND, B2B_DEDICATED, FIXED_WIRELESS
            $table->double('speed_mbps', 8, 2);
            $table->double('monthly_usage_cap_gb', 10, 2); // e.g. 1000 GB
            $table->double('current_usage_gb', 10, 2)->default(0.0);
            $table->bigInteger('monthly_fee_minor');
            $table->string('billing_type'); // PREPAID, POSTPAID
            $table->string('service_status')->default('ACTIVE'); // ACTIVE, THROTTLED, PAUSED, CANCELLED
            $table->boolean('is_overdue')->default(false);
            $table->timestamps();

            $table->index(['customer_id', 'service_status']);
        });

        // 132.2 SIM/eSIM mobile plans & auto-renew wallet
        Schema::create('tlx_sim_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('msisdn')->unique(); // phone number
            $table->string('iccid')->unique();
            $table->string('account_tier'); // PARENT, CHILD_FAMILY
            $table->string('parent_msisdn')->nullable();
            $table->string('wallet_id');
            $table->string('plan_code');
            $table->double('quota_allowance_gb', 8, 2);
            $table->double('quota_remaining_gb', 8, 2);
            $table->double('rollover_quota_gb', 8, 2)->default(0.0);
            $table->bigInteger('auto_renew_fee_minor');
            $table->string('status')->default('ACTIVE'); // ACTIVE, PAUSED, SUSPENDED
            $table->timestamps();

            $table->index(['wallet_id', 'status']);
        });

        // 132.2 Interconnect operator settlement
        Schema::create('tlx_interconnect_settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('settlement_batch_code')->unique();
            $table->string('partner_operator_code'); // TELKOM_ID, INDOSAT_ID, XL_ID
            $table->string('period_month'); // YYYY-MM
            $table->bigInteger('inbound_minutes');
            $table->bigInteger('outbound_minutes');
            $table->bigInteger('inbound_receivable_minor');
            $table->bigInteger('outbound_payable_minor');
            $table->bigInteger('net_settlement_minor'); // positive = receivable from partner, negative = payable
            $table->string('status')->default('SETTLED');
            $table->timestamps();

            $table->index(['partner_operator_code', 'period_month'], 'tlx_settle_partner_month_idx');
        });

        // 132.3 Smart City Contracts & Services
        Schema::create('tlx_smart_city_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('service_code')->unique();
            $table->string('municipality_name'); // DKI Jakarta, Surabaya, etc.
            $table->string('service_type'); // SMART_PARKING, IOT_STREET_LIGHT, TRAFFIC_CCTV
            $table->integer('active_sensor_count');
            $table->double('sla_target_pct', 5, 2)->default(99.90);
            $table->double('sla_achieved_pct', 5, 2)->default(100.00);
            $table->bigInteger('monthly_contract_value_minor');
            $table->string('status')->default('ACTIVE');
            $table->timestamps();

            $table->index(['municipality_name', 'service_type']);
        });

        // 132.5 Churn & Upsell Analytics
        Schema::create('tlx_churn_predictions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subscriber_id')->unique();
            $table->string('subscriber_type'); // ISP, MOBILE
            $table->double('usage_decline_pct', 5, 2); // e.g. 50% drop
            $table->integer('support_tickets_count')->default(0);
            $table->double('churn_risk_score', 4, 2); // 0.00 - 1.00
            $table->string('risk_level'); // LOW, MEDIUM, HIGH, CRITICAL
            $table->string('recommended_action'); // UPGRADE_OFFER, RETENTION_DISCOUNT, PROACTIVE_CALL
            $table->timestamps();

            $table->index(['risk_level', 'churn_risk_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tlx_churn_predictions');
        Schema::dropIfExists('tlx_smart_city_services');
        Schema::dropIfExists('tlx_interconnect_settlements');
        Schema::dropIfExists('tlx_sim_subscriptions');
        Schema::dropIfExists('tlx_isp_subscriptions');
    }
};
