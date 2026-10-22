<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 186.2: End-to-end value chain simulation tracking with Σ=0 balance verification
        Schema::create('val_chain_simulations', function (Blueprint $table) {
            $table->id();
            $table->string('sim_code')->unique();
            $table->string('chain_name'); // e.g. AGRO_FOOD_HOSPITALITY_CHAIN
            $table->integer('compression_days')->default(90);
            $table->bigInteger('total_debits_idr');
            $table->bigInteger('total_credits_idr');
            $table->bigInteger('net_imbalance_idr')->default(0); // Must stay Σ=0
            $table->string('status')->default('COMPLETED');
            $table->timestamps();
        });

        // 186.3: Cross-line contract bridge adapters (sewa, offtake, ppa, franchise)
        Schema::create('val_contract_bridges', function (Blueprint $table) {
            $table->id();
            $table->string('bridge_code')->unique();
            $table->string('core_contract_code');
            $table->string('contract_type'); // OFFTAKE, PPA, FRANCHISE, LEASE
            $table->string('upstream_line'); // e.g. AGRI, MINING, ENERGY
            $table->string('downstream_line'); // e.g. FOOD, LOGISTICS, RETAIL
            $table->bigInteger('committed_value_idr');
            $table->boolean('has_duplicate_state')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('val_contract_bridges');
        Schema::dropIfExists('val_chain_simulations');
    }
};
