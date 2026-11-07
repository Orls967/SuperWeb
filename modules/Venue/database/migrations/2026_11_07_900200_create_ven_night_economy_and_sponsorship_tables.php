<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 90.1 Kontrak Artis Multi-tier & Versus Deal
        Schema::create('ven_artist_engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('ven_entertainment_events')->cascadeOnDelete();
            $table->string('artist_name', 128);
            $table->string('management_company', 128)->nullable();
            $table->string('contract_number', 64)->unique();
            $table->string('deal_type', 32); // guarantee, door_split, versus
            $table->bigInteger('guarantee_idr')->default(0);
            $table->integer('door_share_bps')->default(0);
            $table->integer('wht_bps')->default(0); // withholding tax PPh 21/26
            $table->bigInteger('advance_paid_idr')->default(0);
            $table->bigInteger('gross_pay_idr')->default(0);
            $table->bigInteger('wht_idr')->default(0);
            $table->bigInteger('net_paid_idr')->default(0);
            $table->string('currency', 10)->default('IDR');
            $table->bigInteger('valas_minor_cents')->default(0);
            $table->bigInteger('exchange_rate_idr')->default(1);
            $table->string('status', 32)->default('offered'); // ArtistEngagementStateMachine
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });

        // 90.1 Rider Teknis & Hospitality Artis
        Schema::create('ven_artist_riders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')->constrained('ven_artist_engagements')->cascadeOnDelete();
            $table->string('rider_category', 32); // technical, hospitality, security, transport
            $table->string('description', 255);
            $table->bigInteger('budget_idr')->default(0);
            $table->bigInteger('actual_spent_idr')->default(0);
            $table->boolean('is_fulfilled')->default(false);
            $table->string('receipt_reference', 64)->nullable();
            $table->timestamps();

            $table->index(['engagement_id', 'rider_category']);
        });

        // 90.2 Inventaris Bar & Spirit MMEA Impor
        Schema::create('ven_bar_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('ven_entertainment_venues')->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('brand_name', 128);
            $table->string('category', 64); // spirit_premium, wine_champagne, beer, non_alcoholic
            $table->string('alcohol_tier', 10)->default('C'); // A, B, C (PMK 160/2023)
            $table->integer('unit_volume_ml')->default(750);
            $table->integer('current_stock_bottles')->default(0);
            $table->integer('safety_stock_bottles')->default(5);
            $table->bigInteger('cost_per_bottle_idr')->default(0);
            $table->bigInteger('selling_price_per_bottle_idr')->default(0);
            $table->bigInteger('cif_per_bottle_idr')->default(0);
            $table->bigInteger('excise_duty_per_bottle_idr')->default(0);
            $table->timestamps();

            $table->index(['venue_id', 'category']);
        });

        // 90.2 Log Shift Bar, Spillage, & Varians Stok
        Schema::create('ven_bar_shift_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('ven_entertainment_venues')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('ven_entertainment_zones')->cascadeOnDelete();
            $table->string('shift_code', 64)->unique();
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->integer('opening_bottles')->default(0);
            $table->integer('closing_bottles')->default(0);
            $table->integer('pos_sales_bottles')->default(0);
            $table->integer('spillage_ml')->default(0);
            $table->integer('variance_bottles')->default(0);
            $table->foreignId('supervisor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('open'); // open, audited, reconciled
            $table->timestamps();

            $table->index(['venue_id', 'status']);
        });

        // 90.3 RevPAT & Profitabilitas Real-Time
        Schema::create('ven_table_revpat_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('table_code', 32);
            $table->date('metric_date');
            $table->string('zone_code', 32);
            $table->integer('available_hours')->default(8);
            $table->integer('occupied_hours')->default(0);
            $table->bigInteger('food_revenue_idr')->default(0);
            $table->bigInteger('beverage_revenue_idr')->default(0);
            $table->bigInteger('minimum_spend_shortfall_idr')->default(0);
            $table->bigInteger('total_revenue_idr')->default(0);
            $table->bigInteger('revpat_idr')->default(0); // Revenue Per Available Table-hour
            $table->timestamps();

            $table->index(['table_code', 'metric_date']);
        });

        // 90.4 Perluasan Membership dengan Spend 365, Locker, & Grace Period
        if (Schema::hasTable('ven_memberships')) {
            Schema::table('ven_memberships', function (Blueprint $table) {
                if (! Schema::hasColumn('ven_memberships', 'spend_365_days_idr')) {
                    $table->bigInteger('spend_365_days_idr')->default(0)->after('loyalty_points');
                    $table->integer('visits_365_days')->default(0)->after('spend_365_days_idr');
                    $table->string('bottle_locker_number', 32)->nullable()->after('visits_365_days');
                    $table->timestamp('grace_period_expires_at')->nullable()->after('bottle_locker_number');
                }
            });
        }

        // 90.5 Order Bundle Festival
        if (! Schema::hasTable('ven_festival_bundle_orders')) {
            Schema::create('ven_festival_bundle_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained('ven_entertainment_events')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
                $table->string('order_number', 64)->unique();
                $table->string('bundle_code', 64);
                $table->bigInteger('bundle_price_idr');
                $table->string('status', 32)->default('paid'); // FestivalBundleStateMachine
                $table->timestamps();

                $table->index(['event_id', 'status']);
            });
        }

        // 90.5 Alokasi SSP Multi-Vendor Bundle
        Schema::create('ven_festival_bundle_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('ven_festival_bundle_orders')->cascadeOnDelete();
            $table->string('component_name', 64);
            $table->string('vendor_code', 64);
            $table->bigInteger('ssp_idr');
            $table->bigInteger('allocated_amount_idr');
            $table->bigInteger('platform_fee_idr');
            $table->bigInteger('vendor_net_idr');
            $table->string('status', 32)->default('unearned'); // unearned, redeemed, settled
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        // 90.7 Kemitraan Sponsor & Eksposur Terbobot
        Schema::create('ven_sponsor_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('ven_entertainment_events')->cascadeOnDelete();
            $table->string('sponsor_name', 128);
            $table->string('brand_category', 64);
            $table->bigInteger('contract_value_idr');
            $table->bigInteger('target_impressions');
            $table->bigInteger('delivered_impressions')->default(0);
            $table->bigInteger('cpm_rate_idr')->default(50000);
            $table->string('status', 32)->default('proposed'); // SponsorDealStateMachine
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });

        // 90.7 Milestone Pembayaran Sponsor Berbasis KPI
        Schema::create('ven_sponsor_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained('ven_sponsor_deals')->cascadeOnDelete();
            $table->string('milestone_name', 128);
            $table->date('due_date');
            $table->bigInteger('target_impressions');
            $table->bigInteger('delivered_impressions')->default(0);
            $table->integer('payout_ratio_bps')->default(0);
            $table->bigInteger('milestone_budget_idr');
            $table->bigInteger('milestone_payout_idr')->default(0);
            $table->string('status', 32)->default('pending'); // pending, verified, invoiced, paid
            $table->timestamps();

            $table->index(['deal_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ven_sponsor_milestones');
        Schema::dropIfExists('ven_sponsor_deals');
        Schema::dropIfExists('ven_festival_bundle_allocations');
        Schema::dropIfExists('ven_festival_bundle_orders');
        Schema::dropIfExists('ven_table_revpat_metrics');
        Schema::dropIfExists('ven_bar_shift_logs');
        Schema::dropIfExists('ven_bar_inventory_items');
        Schema::dropIfExists('ven_artist_riders');
        Schema::dropIfExists('ven_artist_engagements');
    }
};
