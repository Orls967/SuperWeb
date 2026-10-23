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
                $table->string('underlying_asset_type'); // mall_units, lgx_trucks, ast_assets, oto_fleet
                $table->string('underlying_asset_id');
                $table->string('appraisal_document_ref')->nullable();
                $table->unsignedBigInteger('appraisal_value_idr');
                $table->unsignedBigInteger('total_supply_tokens');
                $table->unsignedBigInteger('token_price_idr');
                $table->decimal('max_holder_concentration_pct', 5, 2)->default(25.00);
                $table->string('status')->default('draft'); // draft, approved, active, paused, retired
                $table->unsignedBigInteger('approved_by_primary_id')->nullable();
                $table->unsignedBigInteger('approved_by_secondary_id')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->index('underlying_asset_id');
                $table->foreign('approved_by_primary_id')->references('id')->on('users');
                $table->foreign('approved_by_secondary_id')->references('id')->on('users');
            });
        }

        if (! Schema::hasTable('rwa_offerings')) {
            Schema::create('rwa_offerings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rwa_asset_id')->constrained('rwa_assets')->cascadeOnDelete();
                $table->string('offering_code')->unique();
                $table->unsignedBigInteger('tokens_offered');
                $table->unsignedBigInteger('tokens_sold')->default(0);
                $table->unsignedBigInteger('price_per_token_idr');
                $table->unsignedInteger('min_lot_size')->default(1);
                $table->unsignedInteger('lockup_days')->default(30);
                $table->timestamp('start_date');
                $table->timestamp('end_date');
                $table->string('status')->default('open'); // upcoming, open, closed, cancelled
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rwa_holdings')) {
            Schema::create('rwa_holdings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rwa_asset_id')->constrained('rwa_assets')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users');
                $table->boolean('is_kyc_verified')->default(true);
                $table->unsignedBigInteger('token_balance')->default(0);
                $table->unsignedBigInteger('locked_balance')->default(0);
                $table->timestamp('lock_expires_at')->nullable();
                $table->timestamps();

                $table->unique(['rwa_asset_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('rwa_orderbook_orders')) {
            Schema::create('rwa_orderbook_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_code')->unique();
                $table->foreignId('rwa_asset_id')->constrained('rwa_assets')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users');
                $table->string('side'); // buy, sell
                $table->unsignedBigInteger('quantity');
                $table->unsignedBigInteger('filled_quantity')->default(0);
                $table->unsignedBigInteger('price_per_token_idr');
                $table->string('status')->default('open'); // open, partially_filled, filled, cancelled
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rwa_dividends')) {
            Schema::create('rwa_dividends', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rwa_asset_id')->constrained('rwa_assets');
                $table->unsignedBigInteger('total_revenue_pool_idr');
                $table->unsignedBigInteger('distributed_idr');
                $table->unsignedBigInteger('rounding_reserve_idr');
                $table->date('distribution_date');
                $table->string('status')->default('completed'); // completed, retry_queued
                $table->string('idempotency_key')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rwa_corporate_actions')) {
            Schema::create('rwa_corporate_actions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rwa_asset_id')->constrained('rwa_assets')->cascadeOnDelete();
                $table->string('action_type'); // redemption, dilution, cancellation
                $table->unsignedBigInteger('tokens_affected');
                $table->unsignedBigInteger('redemption_payout_per_token_idr')->default(0);
                $table->string('prev_hash', 64);
                $table->string('block_hash', 64)->unique();
                $table->timestamp('executed_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rwa_corporate_actions');
        Schema::dropIfExists('rwa_dividends');
        Schema::dropIfExists('rwa_orderbook_orders');
        Schema::dropIfExists('rwa_holdings');
        Schema::dropIfExists('rwa_offerings');
        Schema::dropIfExists('rwa_assets');
    }
};
