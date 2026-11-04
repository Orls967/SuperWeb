<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mar_ponds', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->foreignId('farmer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('capacity_units');
            $table->string('location', 150);
            $table->timestamps();
        });

        Schema::create('mar_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('cycle_number', 32)->unique();
            $table->foreignId('pond_id')->constrained('mar_ponds')->cascadeOnDelete();
            $table->foreignId('farmer_id')->constrained('users')->cascadeOnDelete();
            $table->string('species', 50);
            $table->unsignedInteger('stocked_count');
            $table->unsignedInteger('initial_weight_grams_per_fish');
            $table->unsignedBigInteger('advance_amount')->default(0);
            $table->string('status', 20)->default('active'); // active, harvested, settled
            $table->timestamp('started_at');
            $table->timestamps();
        });

        Schema::create('mar_feed_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('mar_cycles')->cascadeOnDelete();
            $table->date('feed_date');
            $table->unsignedInteger('quantity_kg');
            $table->unsignedBigInteger('cost_per_kg');
            $table->unsignedBigInteger('total_cost');
            $table->boolean('loan_disbursed')->default(false);
            $table->timestamps();
        });

        Schema::create('mar_harvests', function (Blueprint $table) {
            $table->id();
            $table->string('harvest_number', 32)->unique();
            $table->foreignId('cycle_id')->constrained('mar_cycles')->cascadeOnDelete();
            $table->date('harvest_date');
            $table->unsignedInteger('harvested_count');
            $table->unsignedInteger('avg_weight_grams');
            $table->unsignedInteger('total_weight_kg');
            $table->unsignedInteger('grade_a_kg')->default(0);
            $table->unsignedBigInteger('grade_a_price')->default(0);
            $table->unsignedInteger('grade_b_kg')->default(0);
            $table->unsignedBigInteger('grade_b_price')->default(0);
            $table->unsignedInteger('grade_c_kg')->default(0);
            $table->unsignedBigInteger('grade_c_price')->default(0);
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedInteger('fcr_scaled'); // FCR * 1000 (misal 1330 = 1.330)
            $table->timestamps();
        });

        Schema::create('mar_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('mar_cycles')->cascadeOnDelete();
            $table->foreignId('farmer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('advance_deduction');
            $table->unsignedBigInteger('feed_loan_deduction');
            $table->unsignedBigInteger('net_payout');
            $table->timestamp('settled_at');
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mar_payouts');
        Schema::dropIfExists('mar_harvests');
        Schema::dropIfExists('mar_feed_logs');
        Schema::dropIfExists('mar_cycles');
        Schema::dropIfExists('mar_ponds');
    }
};
