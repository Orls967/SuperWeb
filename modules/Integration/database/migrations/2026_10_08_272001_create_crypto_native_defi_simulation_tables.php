<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defi_treasury_vaults', function (Blueprint $table) {
            $table->id();
            $table->string('vault_code')->unique();
            $table->string('vault_type'); // COLD_STORAGE, HOT_WALLET
            $table->decimal('balance_usd', 18, 4)->default(0.0000);
            $table->decimal('daily_limit_usd', 15, 2)->default(500000.00);
            $table->decimal('spent_today_usd', 15, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('defi_multisig_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tx_code')->unique();
            $table->string('vault_code')->index();
            $table->decimal('amount_usd', 15, 2);
            $table->string('destination_address');
            $table->integer('required_signatures')->default(3); // m-of-n
            $table->integer('current_signatures')->default(0);
            $table->boolean('is_break_glass_emergency')->default(false); // 272.6
            $table->string('break_glass_justification')->nullable();
            $table->string('status')->default('PENDING_SIGNATURES'); // PENDING_SIGNATURES, EXECUTED, REJECTED
            $table->timestamps();
        });

        Schema::create('defi_liquidity_pools', function (Blueprint $table) {
            $table->id();
            $table->string('pool_symbol')->unique(); // e.g. COMMODITY_POINT_USDT
            $table->decimal('reserve_x', 18, 4);
            $table->decimal('reserve_y', 18, 4);
            $table->decimal('invariant_k', 36, 4); // x * y
            $table->decimal('fee_pct', 5, 2)->default(0.30);
            $table->boolean('is_halted')->default(false); // 272.5
            $table->timestamps();
        });

        Schema::create('defi_staking_programs', function (Blueprint $table) {
            $table->id();
            $table->string('program_code')->unique();
            $table->string('token_symbol');
            $table->decimal('total_staked', 18, 4)->default(0.0000);
            $table->decimal('emission_cap_usd', 15, 2);
            $table->decimal('emitted_rewards_usd', 15, 2)->default(0.00);
            $table->boolean('is_closed')->default(false); // 272.7
            $table->boolean('payouts_completed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defi_staking_programs');
        Schema::dropIfExists('defi_liquidity_pools');
        Schema::dropIfExists('defi_multisig_transactions');
        Schema::dropIfExists('defi_treasury_vaults');
    }
};
