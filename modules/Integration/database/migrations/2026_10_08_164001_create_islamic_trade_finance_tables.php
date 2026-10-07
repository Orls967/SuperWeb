<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 164.1: Islamic Letter of Credit (Istisna' + Wakalah)
        Schema::create('syb_islamic_lcs', function (Blueprint $table) {
            $table->id();
            $table->string('lc_number')->unique();
            $table->string('importer_account');
            $table->string('exporter_code');
            $table->decimal('goods_value', 18, 2);
            $table->decimal('wakalah_fee', 18, 2);
            $table->string('akad_type')->default('ISTISNA_WAKALAH');
            $table->string('status')->default('ISSUED'); // ISSUED, SETTLED
            $table->timestamps();
        });

        // 164.2: Salam & Parallel Salam contracts
        Schema::create('syb_salam_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_code')->unique();
            $table->string('farmer_id');
            $table->string('commodity_name');
            $table->decimal('advance_payment_paid', 18, 2);
            $table->decimal('quantity_tons', 10, 2);
            $table->date('delivery_due_date');
            $table->string('parallel_hedge_contract_code')->nullable();
            $table->boolean('positions_balanced')->default(true);
            $table->timestamps();
        });

        // 164.4: Commodity Murabahah / Tawarruq for FX conversion
        Schema::create('syb_tawarruq_fx_flows', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code')->unique();
            $table->string('source_currency', 3);
            $table->string('target_currency', 3);
            $table->decimal('source_amount', 18, 2);
            $table->decimal('target_amount', 18, 2);
            $table->decimal('broker_service_fee', 18, 2);
            $table->boolean('shariah_board_cleared')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_tawarruq_fx_flows');
        Schema::dropIfExists('syb_salam_contracts');
        Schema::dropIfExists('syb_islamic_lcs');
    }
};
