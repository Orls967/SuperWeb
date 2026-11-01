<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ven_demand_forecasts') && ! Schema::hasTable('vnd_demand_forecasts')) {
            Schema::rename('ven_demand_forecasts', 'vnd_demand_forecasts');
        }

        if (Schema::hasTable('ven_auto_pos') && ! Schema::hasTable('vnd_auto_pos')) {
            Schema::rename('ven_auto_pos', 'vnd_auto_pos');
        }

        if (Schema::hasTable('ven_vending_units') && ! Schema::hasTable('vnd_vending_units')) {
            Schema::rename('ven_vending_units', 'vnd_vending_units');
        }

        if (Schema::hasTable('ven_vending_transactions') && ! Schema::hasTable('vnd_vending_transactions')) {
            Schema::rename('ven_vending_transactions', 'vnd_vending_transactions');
        }

        if (Schema::hasTable('ven_restock_tasks') && ! Schema::hasTable('vnd_restock_tasks')) {
            Schema::rename('ven_restock_tasks', 'vnd_restock_tasks');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vnd_restock_tasks') && ! Schema::hasTable('ven_restock_tasks')) {
            Schema::rename('vnd_restock_tasks', 'ven_restock_tasks');
        }

        if (Schema::hasTable('vnd_vending_transactions') && ! Schema::hasTable('ven_vending_transactions')) {
            Schema::rename('vnd_vending_transactions', 'ven_vending_transactions');
        }

        if (Schema::hasTable('vnd_vending_units') && ! Schema::hasTable('ven_vending_units')) {
            Schema::rename('vnd_vending_units', 'ven_vending_units');
        }

        if (Schema::hasTable('vnd_auto_pos') && ! Schema::hasTable('ven_auto_pos')) {
            Schema::rename('vnd_auto_pos', 'ven_auto_pos');
        }

        if (Schema::hasTable('vnd_demand_forecasts') && ! Schema::hasTable('ven_demand_forecasts')) {
            Schema::rename('vnd_demand_forecasts', 'ven_demand_forecasts');
        }
    }
};
