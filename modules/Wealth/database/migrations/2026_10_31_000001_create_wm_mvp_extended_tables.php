<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wm_profiles')) {
            Schema::table('wm_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('wm_profiles', 'risk_score')) {
                    $table->unsignedTinyInteger('risk_score')->default(3)->after('risk_profile');
                }
                if (! Schema::hasColumn('wm_profiles', 'target_money_market_bps')) {
                    $table->unsignedInteger('target_money_market_bps')->default(3000)->after('risk_score');
                }
                if (! Schema::hasColumn('wm_profiles', 'target_bonds_bps')) {
                    $table->unsignedInteger('target_bonds_bps')->default(4000)->after('target_money_market_bps');
                }
                if (! Schema::hasColumn('wm_profiles', 'target_equity_rwa_bps')) {
                    $table->unsignedInteger('target_equity_rwa_bps')->default(3000)->after('target_bonds_bps');
                }
                if (! Schema::hasColumn('wm_profiles', 'drift_band_bps')) {
                    $table->unsignedInteger('drift_band_bps')->default(500)->after('target_equity_rwa_bps');
                }
            });
        }

        if (! Schema::hasTable('wm_portfolio_holdings')) {
            Schema::create('wm_portfolio_holdings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('profile_id')->constrained('wm_profiles')->cascadeOnDelete();
                $table->string('asset_code');
                $table->unsignedBigInteger('units')->default(0);
                $table->unsignedBigInteger('average_cost_nav')->default(10000);
                $table->unsignedBigInteger('current_nav')->default(10000);
                $table->unsignedBigInteger('current_market_value_idr')->default(0);
                $table->timestamp('last_mtm_at')->nullable();
                $table->timestamps();

                $table->unique(['profile_id', 'asset_code']);
            });
        }

        if (! Schema::hasTable('wm_nav_history')) {
            Schema::create('wm_nav_history', function (Blueprint $table) {
                $table->id();
                $table->string('asset_code');
                $table->date('nav_date');
                $table->unsignedBigInteger('nav_scale_4'); // 10000 = 1.0000
                $table->string('source');
                $table->string('idempotency_key')->unique();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['asset_code', 'nav_date']);
            });
        }

        if (! Schema::hasTable('wm_sweep_configs')) {
            Schema::create('wm_sweep_configs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('threshold_cash_idr')->default(5000000);
                $table->unsignedInteger('sweep_percentage_bps')->default(10000);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wm_sweep_configs');
        Schema::dropIfExists('wm_nav_history');
        Schema::dropIfExists('wm_portfolio_holdings');

        if (Schema::hasTable('wm_profiles')) {
            Schema::table('wm_profiles', function (Blueprint $table) {
                $table->dropColumn([
                    'risk_score',
                    'target_money_market_bps',
                    'target_bonds_bps',
                    'target_equity_rwa_bps',
                    'drift_band_bps',
                ]);
            });
        }
    }
};
