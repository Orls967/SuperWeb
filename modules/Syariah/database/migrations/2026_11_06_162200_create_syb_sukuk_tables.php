<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_sukuk_facilities', function (Blueprint $table) {
            $table->id();
            $table->string('sukuk_code', 64)->unique();
            $table->string('facility_name', 191);
            $table->string('asset_code', 64);
            $table->foreignId('rwa_asset_id')->nullable()->constrained('rwa_assets')->nullOnDelete();
            $table->unsignedBigInteger('face_value_idr');
            $table->unsignedInteger('total_units');
            $table->date('maturity_date');
            $table->unsignedSmallInteger('annual_coupon_bps');
            $table->string('status', 24)->default('STRUCTURING');
            $table->string('spv_party_name', 191);
            $table->foreignId('dps_reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dps_reviewed_at')->nullable();
            $table->string('dps_opinion_note', 255)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->unsignedBigInteger('total_coupon_distributed_idr')->default(0);
            $table->string('idempotency_key', 120)->unique();
            $table->timestamps();

            $table->index(['status', 'maturity_date'], 'idx_syb_sukuk_status_mat');
        });

        Schema::create('syb_sukuk_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sukuk_facility_id')->constrained('syb_sukuk_facilities')->cascadeOnDelete();
            $table->string('period_code', 32);
            $table->unsignedBigInteger('coupon_pool_idr');
            $table->unsignedBigInteger('distributed_idr');
            $table->unsignedBigInteger('rounding_reserve_idr')->default(0);
            $table->date('distribution_date');
            $table->string('idempotency_key', 120)->unique();
            $table->timestamps();

            $table->unique(['sukuk_facility_id', 'period_code'], 'uq_syb_sukuk_dist_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_sukuk_distributions');
        Schema::dropIfExists('syb_sukuk_facilities');
    }
};
