<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_agri_micro_facilities', function (Blueprint $table) {
            $table->id();
            $table->string('facility_code', 32)->unique();
            $table->string('farmer_party_ref', 64)->index();
            $table->string('plot_code', 64)->index();
            $table->string('commodity', 64);
            $table->string('contract_type', 32); // SALAM, MUZARAAH
            $table->bigInteger('financing_amount_idr');
            $table->bigInteger('outstanding_balance_idr');
            $table->integer('expected_yield_kg');
            $table->bigInteger('contract_price_per_kg_idr');
            $table->integer('milestone_count')->default(3);
            $table->bigInteger('disbursed_amount_idr')->default(0);
            $table->integer('ndvi_min_threshold_bps')->default(3000);
            $table->integer('current_ndvi_bps')->default(0);
            $table->string('status', 24)->default('active');
            $table->timestamps();
        });

        Schema::create('syb_agri_milestones', function (Blueprint $table) {
            $table->id();
            $table->string('milestone_code', 32)->unique();
            $table->string('facility_code', 32)->index();
            $table->integer('milestone_number');
            $table->string('title', 128);
            $table->integer('target_ndvi_bps');
            $table->integer('disbursement_percentage');
            $table->bigInteger('amount_idr');
            $table->timestamp('verified_at')->nullable();
            $table->string('status', 16)->default('PENDING'); // PENDING, VERIFIED, DISBURSED
            $table->timestamps();

            $table->foreign('facility_code')
                ->references('facility_code')
                ->on('syb_agri_micro_facilities')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_agri_milestones');
        Schema::dropIfExists('syb_agri_micro_facilities');
    }
};
