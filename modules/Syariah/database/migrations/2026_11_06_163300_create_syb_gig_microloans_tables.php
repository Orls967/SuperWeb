<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_gig_microloans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_code', 32)->unique();
            $table->string('worker_party_ref', 64)->index();
            $table->bigInteger('gross_average_income_idr');
            $table->integer('income_volatility_bps');
            $table->integer('amanah_score');
            $table->bigInteger('approved_credit_limit_idr');
            $table->bigInteger('disbursed_amount_idr');
            $table->bigInteger('remaining_balance_idr');
            $table->bigInteger('monthly_deduction_idr');
            $table->string('status', 24)->default('active');
            $table->timestamps();
        });

        Schema::create('syb_gig_deductions', function (Blueprint $table) {
            $table->id();
            $table->string('deduction_code', 32)->unique();
            $table->string('loan_code', 32)->index();
            $table->string('payout_ref', 64)->nullable();
            $table->bigInteger('deducted_amount_idr');
            $table->bigInteger('allocated_margin_idr');
            $table->bigInteger('allocated_principal_idr');
            $table->bigInteger('allocated_tazir_idr')->default(0);
            $table->timestamp('deducted_at');
            $table->timestamps();

            $table->foreign('loan_code')
                ->references('loan_code')
                ->on('syb_gig_microloans')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_gig_deductions');
        Schema::dropIfExists('syb_gig_microloans');
    }
};
