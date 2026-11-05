<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_letters_of_credit', function (Blueprint $table) {
            $table->id();
            $table->string('lc_number', 64)->unique();
            $table->string('applicant_party_ref', 64)->index();
            $table->string('beneficiary_party_ref', 64)->index();
            $table->string('issuing_bank_party_ref', 64)->index();
            $table->string('amount_currency', 3)->default('IDR');
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('margin_deposit_cents')->default(0);
            $table->string('status', 32)->index();
            $table->date('expiry_date');
            $table->unsignedBigInteger('wakalah_fee_cents')->default(0);
            $table->text('terms_conditions')->nullable();
            $table->timestamps();
        });

        Schema::create('syb_istisna_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lc_id')->constrained('syb_letters_of_credit')->cascadeOnDelete();
            $table->string('contract_number', 64)->unique();
            $table->string('manufacturer_party_ref', 64)->index();
            $table->text('specification_summary');
            $table->unsignedBigInteger('total_price_cents');
            $table->unsignedBigInteger('budgeted_cost_cents');
            $table->unsignedBigInteger('actual_cost_incurred_cents')->default(0);
            $table->unsignedBigInteger('recognized_revenue_cents')->default(0);
            $table->unsignedInteger('progress_percent')->default(0);
            $table->string('status', 32)->index();
            $table->timestamps();
        });

        Schema::create('syb_istisna_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('istisna_id')->constrained('syb_istisna_contracts')->cascadeOnDelete();
            $table->string('milestone_name', 128);
            $table->unsignedBigInteger('cost_incurred_cents');
            $table->unsignedInteger('progress_percentage');
            $table->text('inspection_note')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verifier_ref', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_istisna_milestones');
        Schema::dropIfExists('syb_istisna_contracts');
        Schema::dropIfExists('syb_letters_of_credit');
    }
};
