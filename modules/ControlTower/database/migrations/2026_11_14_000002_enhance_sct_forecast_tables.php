<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sct_demand_forecasts')) {
            Schema::table('sct_demand_forecasts', function (Blueprint $table): void {
                if (! Schema::hasColumn('sct_demand_forecasts', 'mae_scaled')) {
                    $table->unsignedBigInteger('mae_scaled')->nullable()->after('mape_percent');
                }
                if (! Schema::hasColumn('sct_demand_forecasts', 'candidates_json')) {
                    $table->json('candidates_json')->nullable()->after('mae_scaled');
                }
                if (! Schema::hasColumn('sct_demand_forecasts', 'certificate_hash')) {
                    $table->string('certificate_hash', 64)->nullable()->after('candidates_json');
                }
                if (! Schema::hasColumn('sct_demand_forecasts', 'history_json')) {
                    $table->json('history_json')->nullable()->after('certificate_hash');
                }
            });
        }

        if (Schema::hasTable('sct_forecast_overrides')) {
            Schema::table('sct_forecast_overrides', function (Blueprint $table): void {
                if (! Schema::hasColumn('sct_forecast_overrides', 'status')) {
                    $table->string('status', 32)->nullable()->default('applied')->after('overridden_by_user_id');
                }
                if (! Schema::hasColumn('sct_forecast_overrides', 'deviation_bps')) {
                    $table->unsignedInteger('deviation_bps')->nullable()->after('status');
                }
                if (! Schema::hasColumn('sct_forecast_overrides', 'approval_id')) {
                    $table->unsignedBigInteger('approval_id')->nullable()->after('deviation_bps');
                }
                if (! Schema::hasColumn('sct_forecast_overrides', 'reverted_at')) {
                    $table->timestamp('reverted_at')->nullable()->after('approval_id');
                }
                if (! Schema::hasColumn('sct_forecast_overrides', 'reverted_by_user_id')) {
                    $table->unsignedBigInteger('reverted_by_user_id')->nullable()->after('reverted_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sct_forecast_overrides')) {
            Schema::table('sct_forecast_overrides', function (Blueprint $table): void {
                foreach (['reverted_by_user_id', 'reverted_at', 'approval_id', 'deviation_bps', 'status'] as $column) {
                    if (Schema::hasColumn('sct_forecast_overrides', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('sct_demand_forecasts')) {
            Schema::table('sct_demand_forecasts', function (Blueprint $table): void {
                foreach (['history_json', 'certificate_hash', 'candidates_json', 'mae_scaled'] as $column) {
                    if (Schema::hasColumn('sct_demand_forecasts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
