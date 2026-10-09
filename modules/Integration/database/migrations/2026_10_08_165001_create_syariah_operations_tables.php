<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 165.1 & 165.2: Portfolio financing & NPF (Non-Performing Financing) tracking
        Schema::create('syb_portfolio_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('evaluation_period', 10);
            $table->decimal('total_financing_portfolio', 18, 2);
            $table->decimal('current_performing_financing', 18, 2);
            $table->decimal('non_performing_financing', 18, 2);
            $table->decimal('npf_ratio_pct', 5, 2); // NPF target < 5.0%
            $table->timestamps();
        });

        // 165.3: Cross-line halal merchant payment wallet transactions
        Schema::create('syb_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code')->unique();
            $table->string('account_number');
            $table->string('merchant_line_code', 10); // RST, HTL, VEN, MAL
            $table->decimal('amount', 18, 2);
            $table->boolean('halal_certified_merchant')->default(true);
            $table->string('status')->default('SUCCESS');
            $table->timestamps();
        });

        // Halal certificate registry for merchants
        Schema::create('syb_halal_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_code')->unique();
            $table->string('certificate_number');
            $table->boolean('is_certified_halal')->default(true);
            $table->date('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_halal_certificates');
        Schema::dropIfExists('syb_wallet_transactions');
        Schema::dropIfExists('syb_portfolio_metrics');
    }
};
