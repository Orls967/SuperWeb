<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_social_impact_metrics', function (Blueprint $table) {
            $table->id();
            $table->integer('period_year')->unique();
            $table->integer('total_beneficiaries')->default(0);
            $table->integer('jobs_created')->default(0);
            $table->integer('mustahik_to_muzakki_count')->default(0);
            $table->integer('avg_income_increase_bps')->default(0);
            $table->integer('micro_npf_ratio_bps')->default(0); // NPF Mikro dalam bps
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('syb_bmt_repayments', function (Blueprint $table) {
            $table->id();
            $table->string('repayment_code', 32)->unique();
            $table->string('facility_code', 32)->index();
            $table->string('payer_party_ref', 64)->index();
            $table->bigInteger('payment_amount_idr');
            $table->bigInteger('allocated_margin_idr');
            $table->bigInteger('allocated_principal_idr');
            $table->bigInteger('allocated_tazir_idr')->default(0);
            $table->bigInteger('excess_deposit_idr')->default(0);
            $table->string('certificate_hash', 64);
            $table->timestamp('paid_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_bmt_repayments');
        Schema::dropIfExists('syb_social_impact_metrics');
    }
};
