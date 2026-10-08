<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 208.1: Tax calculation records with gapless e-faktur simulation & withholding
        Schema::create('erm_tax_compliance_records', function (Blueprint $table) {
            $table->id();
            $table->integer('tax_invoice_seq')->unique();
            $table->string('efaktur_number')->unique();
            $table->string('jurisdiction'); // ID, SG, MY
            $table->string('tax_type'); // PPN_11, PPH_23, PPH_21, WHT
            $table->decimal('taxable_base_amount_idr', 18, 2);
            $table->decimal('tax_rate_percent', 5, 2);
            $table->decimal('calculated_tax_amount_idr', 18, 2);
            $table->timestamps();
        });

        // 208.4: Trade-based money laundering (TBML) guards with price variance detection
        Schema::create('erm_tbml_trade_guards', function (Blueprint $table) {
            $table->id();
            $table->string('trade_code')->unique();
            $table->string('invoice_number');
            $table->decimal('customs_declared_value_idr', 18, 2);
            $table->decimal('invoice_value_idr', 18, 2);
            $table->decimal('variance_percent', 6, 2);
            $table->boolean('is_flagged_for_review')->default(false);
            $table->string('status')->default('CLEARED'); // CLEARED, HELD_FOR_REVIEW
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erm_tbml_trade_guards');
        Schema::dropIfExists('erm_tax_compliance_records');
    }
};
