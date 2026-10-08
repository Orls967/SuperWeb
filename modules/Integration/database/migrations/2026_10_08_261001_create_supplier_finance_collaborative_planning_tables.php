<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_collaborative_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code')->unique();
            $table->string('supplier_name');
            $table->string('partnership_tier'); // STRATEGIC, PREFERRED, STANDARD
            $table->boolean('is_forecast_sharing_consented')->default(true); // 261.5
            $table->date('contract_expiry_date');
            $table->boolean('portal_access_active')->default(true); // 261.4
            $table->timestamps();
        });

        Schema::create('supplier_forecast_shares', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code')->index();
            $table->integer('rolling_months')->default(12);
            $table->integer('projected_demand_units');
            $table->integer('confirmed_capacity_units');
            $table->timestamps();
        });

        Schema::create('supplier_scf_early_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_ref')->unique();
            $table->string('supplier_code')->index();
            $table->string('invoice_code')->index();
            $table->decimal('invoice_face_value_usd', 15, 2);
            $table->decimal('discount_rate_pct', 5, 2);
            $table->decimal('requested_early_amount_usd', 15, 2);
            $table->decimal('approved_payout_usd', 15, 2);
            $table->decimal('investor_pool_capacity_usd', 15, 2);
            $table->boolean('is_pro_rata_allocated')->default(false); // 261.6
            $table->string('payout_status')->default('APPROVED'); // APPROVED, QUEUED_PRO_RATA, REJECTED
            $table->timestamps();
        });

        Schema::create('supplier_collaborative_spc_feeds', function (Blueprint $table) {
            $table->id();
            $table->string('feed_code')->unique();
            $table->string('supplier_code')->index();
            $table->string('sample_batch_code');
            $table->decimal('spc_quality_score', 5, 2);
            $table->decimal('data_quality_score', 5, 2); // 261.7
            $table->boolean('improvement_plan_required')->default(false); // 261.7
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_collaborative_spc_feeds');
        Schema::dropIfExists('supplier_scf_early_payments');
        Schema::dropIfExists('supplier_forecast_shares');
        Schema::dropIfExists('supplier_collaborative_profiles');
    }
};
