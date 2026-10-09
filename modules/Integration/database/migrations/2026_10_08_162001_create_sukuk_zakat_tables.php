<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 162.1: Sukuk issuance (tokenized real asset backing)
        Schema::create('syb_sukuk_issuances', function (Blueprint $table) {
            $table->id();
            $table->string('sukuk_code')->unique();
            $table->string('asset_underlying_code'); // Real estate, logistics fleet, etc.
            $table->decimal('total_issuance_amount', 18, 2);
            $table->decimal('periodic_coupon_rate_pct', 5, 2)->default(7.50);
            $table->date('maturity_date');
            $table->decimal('total_coupon_distributed', 18, 2)->default(0.00);
            $table->string('status')->default('ACTIVE'); // ACTIVE, MATURED, REDEEMED
            $table->timestamps();
        });

        // 162.2: Ijarah Muntahia Bittamleek (Lease-to-own)
        Schema::create('syb_ijarah_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('ijarah_code')->unique();
            $table->string('asset_code');
            $table->unsignedBigInteger('customer_id');
            $table->decimal('monthly_rental', 18, 2);
            $table->integer('tenor_months');
            $table->integer('months_paid')->default(0);
            $table->decimal('residual_purchase_price', 18, 2)->default(1000.00);
            $table->boolean('ownership_transferred')->default(false);
            $table->timestamps();
        });

        // 162.3: Shariah screening
        Schema::create('syb_shariah_screenings', function (Blueprint $table) {
            $table->id();
            $table->string('ticker_or_asset_code')->unique();
            $table->string('asset_name');
            $table->decimal('debt_to_assets_ratio_pct', 5, 2);
            $table->decimal('non_halal_revenue_ratio_pct', 5, 2);
            $table->boolean('is_shariah_compliant')->default(true);
            $table->timestamps();
        });

        // 162.4: Zakat calculation & distribution engine
        Schema::create('syb_zakat_calculations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('zakat_year', 4);
            $table->decimal('qualifying_wealth', 18, 2);
            $table->decimal('nisab_threshold_gold_equiv', 18, 2)->default(85000000.00); // 85g gold ~ 85m
            $table->decimal('zakat_rate_pct', 5, 2)->default(2.50);
            $table->decimal('zakat_amount_due', 18, 2);
            $table->boolean('is_settled')->default(false);
            $table->string('distribution_asnaf')->nullable(); // FAKIR, MISKIN, AMIL, RIQAB, GHARIMIN, FISABILILLAH, IBNU_SABIL, MUALAF
            $table->timestamps();
            $table->unique(['customer_id', 'zakat_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_zakat_calculations');
        Schema::dropIfExists('syb_shariah_screenings');
        Schema::dropIfExists('syb_ijarah_contracts');
        Schema::dropIfExists('syb_sukuk_issuances');
    }
};
