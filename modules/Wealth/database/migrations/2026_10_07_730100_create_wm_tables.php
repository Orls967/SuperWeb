<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wm_profiles')) {
            Schema::create('wm_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users');
                $table->string('risk_profile')->default('moderate'); // conservative, moderate, aggressive
                $table->unsignedBigInteger('emergency_fund_target_idr')->default(10000000);
                $table->unsignedBigInteger('monthly_spend_baseline_idr')->default(5000000);
                $table->boolean('auto_invest_enabled')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wm_plans')) {
            Schema::create('wm_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('profile_id')->constrained('wm_profiles')->cascadeOnDelete();
                $table->unsignedInteger('allocation_mutual_funds_pct')->default(50);
                $table->unsignedInteger('allocation_gold_pct')->default(30);
                $table->unsignedInteger('allocation_crypto_pct')->default(20);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wm_holdings')) {
            Schema::create('wm_holdings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('profile_id')->constrained('wm_profiles')->cascadeOnDelete();
                $table->string('asset_type'); // mutual_fund, digital_gold, crypto
                $table->unsignedBigInteger('value_idr')->default(0);
                $table->timestamps();

                $table->unique(['profile_id', 'asset_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wm_holdings');
        Schema::dropIfExists('wm_plans');
        Schema::dropIfExists('wm_profiles');
    }
};
