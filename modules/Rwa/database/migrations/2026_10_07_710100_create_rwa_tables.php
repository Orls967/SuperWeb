<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rwa_assets')) {
            Schema::create('rwa_assets', function (Blueprint $table) {
                $table->id();
                $table->string('token_symbol')->unique(); // e.g., RWA-MALL01, RWA-TRK05
                $table->string('name');
                $table->string('underlying_asset_type'); // mall_unit, truck, machinery
                $table->string('underlying_asset_id');
                $table->unsignedBigInteger('appraisal_value_idr');
                $table->unsignedBigInteger('total_supply_tokens');
                $table->unsignedBigInteger('token_price_idr');
                $table->string('status')->default('offering'); // offering, active, liquidated
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rwa_holdings')) {
            Schema::create('rwa_holdings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rwa_asset_id')->constrained('rwa_assets')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users');
                $table->unsignedBigInteger('token_balance')->default(0);
                $table->timestamps();

                $table->unique(['rwa_asset_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('rwa_dividends')) {
            Schema::create('rwa_dividends', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rwa_asset_id')->constrained('rwa_assets');
                $table->unsignedBigInteger('total_revenue_pool_idr');
                $table->unsignedBigInteger('distributed_idr');
                $table->unsignedBigInteger('rounding_reserve_idr');
                $table->timestamp('distribution_date');
                $table->string('idempotency_key')->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rwa_dividends');
        Schema::dropIfExists('rwa_holdings');
        Schema::dropIfExists('rwa_assets');
    }
};
