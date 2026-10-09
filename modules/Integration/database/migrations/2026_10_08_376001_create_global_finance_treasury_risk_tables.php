<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_treasury_counterparty_exposures', function (Blueprint $table) {
            $table->id();
            $table->string('exposure_code')->unique();
            $table->string('counterparty_name');
            $table->decimal('gross_exposure_usd', 15, 2);
            $table->decimal('eligible_collateral_usd', 15, 2)->default(0.00);
            $table->decimal('net_exposure_usd', 15, 2);
            $table->decimal('exposure_limit_usd', 15, 2);
            $table->boolean('exposure_limit_exceeded')->default(false);
            $table->boolean('board_approval_granted')->default(false); // 376.5 Edge case
            $table->boolean('transaction_proceeded')->default(false);
            $table->timestamps();
        });

        Schema::create('global_treasury_funding_waterfalls', function (Blueprint $table) {
            $table->id();
            $table->string('waterfall_code')->unique();
            $table->decimal('requested_amount_usd', 15, 2);
            $table->decimal('total_drawn_usd', 15, 2);
            $table->boolean('never_overdrawn')->default(true); // 376.3 & 376.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_treasury_funding_waterfalls');
        Schema::dropIfExists('global_treasury_counterparty_exposures');
    }
};
