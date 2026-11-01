<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Table med_studio_slot_claims (atomic 30-min discrete slot lock)
        Schema::create('med_studio_slot_claims', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('studio_id');
            $table->timestamp('slot_start');
            $table->timestamp('slot_end');
            $table->uuid('booking_id');
            $table->timestamps();

            $table->unique(['studio_id', 'slot_start'], 'med_slot_claims_stu_start_uniq');
            $table->index('booking_id', 'med_slot_claims_booking_idx');

            $table->foreign('studio_id', 'med_slot_claims_stu_fk')
                ->references('id')
                ->on('med_studios')
                ->cascadeOnDelete();
            $table->foreign('booking_id', 'med_slot_claims_bkg_fk')
                ->references('id')
                ->on('med_studio_bookings')
                ->cascadeOnDelete();
        });

        // 2. Add columns to med_studio_bookings
        Schema::table('med_studio_bookings', function (Blueprint $table): void {
            $table->timestamp('hold_expires_at')->nullable();
            $table->bigInteger('hold_amount_minor')->default(0);
            $table->bigInteger('cancellation_fee_minor')->default(0);
            $table->string('idempotency_key', 64)->nullable()->unique('med_studio_bkg_idemp_uniq');
        });

        // 3. Table med_ip_rights_shares (append-only copyright bps split)
        Schema::create('med_ip_rights_shares', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('ip_id', 64);
            $table->string('rights_type', 50);
            $table->string('rightsholder_party_id', 64);
            $table->integer('share_bps');
            $table->date('effective_date');
            $table->timestamps();

            $table->unique(['ip_id', 'rights_type', 'rightsholder_party_id', 'effective_date'], 'med_ip_rshares_uniq');
            $table->index(['ip_id', 'rights_type', 'effective_date'], 'med_ip_rshares_eff_idx');
        });

        // 4. Add columns to med_ip_licenses
        Schema::table('med_ip_licenses', function (Blueprint $table): void {
            $table->string('contract_number', 64)->nullable();
            $table->string('rights_type', 50)->default('STREAMING');
            $table->boolean('is_exclusive')->default(false);
            $table->bigInteger('advance_payment_minor')->default(0);
            $table->string('idempotency_key', 64)->nullable()->unique('med_ip_lic_idemp_uniq');

            $table->index(['ip_id', 'rights_type', 'territory', 'is_exclusive'], 'med_lic_overlap_idx');
        });

        // 5. Table med_license_revenues
        Schema::create('med_license_revenues', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('license_id');
            $table->string('billing_period', 7);
            $table->bigInteger('gross_revenue_minor');
            $table->integer('distribution_fee_bps')->default(0);
            $table->bigInteger('distribution_fee_minor')->default(0);
            $table->bigInteger('recouped_advance_minor')->default(0);
            $table->bigInteger('net_distributable_minor')->default(0);
            $table->string('idempotency_key', 64)->nullable()->unique('med_lic_rev_idemp_uniq');
            $table->string('status', 20)->default('RECORDED');
            $table->timestamps();

            $table->foreign('license_id', 'med_lic_rev_lic_fk')
                ->references('id')
                ->on('med_ip_licenses')
                ->cascadeOnDelete();
            $table->index(['license_id', 'billing_period'], 'med_lic_rev_period_idx');
        });

        // 6. Table med_royalty_statements
        Schema::create('med_royalty_statements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('statement_number', 64)->unique('med_roy_stmt_num_uniq');
            $table->uuid('license_id');
            $table->string('billing_period', 7);
            $table->bigInteger('gross_revenue_minor');
            $table->bigInteger('total_distribution_fee_minor');
            $table->bigInteger('total_recouped_minor')->default(0);
            $table->bigInteger('total_royalty_distributed_minor');
            $table->string('idempotency_key', 64)->nullable()->unique('med_roy_stmt_idemp_uniq');
            $table->string('status', 20)->default('ISSUED');
            $table->timestamps();

            $table->foreign('license_id', 'med_roy_stmt_lic_fk')
                ->references('id')
                ->on('med_ip_licenses')
                ->cascadeOnDelete();
            $table->index(['license_id', 'billing_period'], 'med_roy_stmt_lic_idx');
        });

        // 7. Table med_royalty_statement_lines
        Schema::create('med_royalty_statement_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('statement_id');
            $table->string('rightsholder_party_id', 64);
            $table->string('rights_type', 50);
            $table->integer('share_bps');
            $table->bigInteger('gross_share_minor');
            $table->bigInteger('recoupment_deducted_minor')->default(0);
            $table->bigInteger('net_payable_minor');
            $table->timestamps();

            $table->foreign('statement_id', 'med_stmt_lines_stmt_fk')
                ->references('id')
                ->on('med_royalty_statements')
                ->cascadeOnDelete();
            $table->index(['statement_id', 'rightsholder_party_id'], 'med_stmt_lines_holder_idx');
        });

        // 8. Table med_ip_advance_balances
        Schema::create('med_ip_advance_balances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('license_id');
            $table->string('rightsholder_party_id', 64);
            $table->bigInteger('initial_advance_minor');
            $table->bigInteger('recouped_amount_minor')->default(0);
            $table->bigInteger('remaining_balance_minor');
            $table->string('idempotency_key', 64)->nullable()->unique('med_adv_bal_idemp_uniq');
            $table->timestamps();

            $table->foreign('license_id', 'med_adv_bal_lic_fk')
                ->references('id')
                ->on('med_ip_licenses')
                ->cascadeOnDelete();
            $table->unique(['license_id', 'rightsholder_party_id'], 'med_adv_bal_lic_holder_uniq');
        });

        // 9. Table med_ad_placements
        Schema::create('med_ad_placements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('channel_id');
            $table->string('placement_code', 64)->unique('med_ad_plc_code_uniq');
            $table->string('location_name');
            $table->bigInteger('floor_cpm_minor')->default(50000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('channel_id', 'med_ad_plc_chan_fk')
                ->references('id')
                ->on('med_distribution_channels')
                ->cascadeOnDelete();
        });

        // 10. Add columns to med_ad_campaigns
        Schema::table('med_ad_campaigns', function (Blueprint $table): void {
            $table->uuid('placement_id')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique('med_ad_cmp_idemp_uniq');

            $table->foreign('placement_id', 'med_ad_cmp_plc_fk')
                ->references('id')
                ->on('med_ad_placements')
                ->nullOnDelete();
        });

        // 11. Table med_ad_impressions (append-only fact table)
        Schema::create('med_ad_impressions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('campaign_id');
            $table->string('impression_hash', 64)->unique('med_ad_imp_hash_uniq');
            $table->string('device_ip_hash', 64);
            $table->timestamp('timestamp_utc');
            $table->boolean('is_verified')->default(true);
            $table->boolean('is_fraud')->default(false);
            $table->string('fraud_reason')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id', 'med_ad_imp_cmp_fk')
                ->references('id')
                ->on('med_ad_campaigns')
                ->cascadeOnDelete();
            $table->index(['campaign_id', 'is_verified', 'is_fraud'], 'med_ad_imp_verif_idx');
            $table->index(['campaign_id', 'device_ip_hash', 'timestamp_utc'], 'med_ad_imp_device_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_ad_impressions');
        Schema::table('med_ad_campaigns', function (Blueprint $table): void {
            $table->dropColumn(['placement_id', 'idempotency_key']);
        });
        Schema::dropIfExists('med_ad_placements');
        Schema::dropIfExists('med_ip_advance_balances');
        Schema::dropIfExists('med_royalty_statement_lines');
        Schema::dropIfExists('med_royalty_statements');
        Schema::dropIfExists('med_license_revenues');
        Schema::table('med_ip_licenses', function (Blueprint $table): void {
            $table->dropColumn(['contract_number', 'rights_type', 'is_exclusive', 'advance_payment_minor', 'idempotency_key']);
        });
        Schema::dropIfExists('med_ip_rights_shares');
        Schema::table('med_studio_bookings', function (Blueprint $table): void {
            $table->dropColumn(['hold_expires_at', 'hold_amount_minor', 'cancellation_fee_minor', 'idempotency_key']);
        });
        Schema::dropIfExists('med_studio_slot_claims');
    }
};
