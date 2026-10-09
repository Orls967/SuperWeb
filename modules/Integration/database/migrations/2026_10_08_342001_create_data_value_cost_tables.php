<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_product_query_cost_trackers', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_code')->unique();
            $table->string('domain_name'); // e.g. MINING, SMELTER, HR, FINANCE
            $table->string('consumer_id');
            $table->decimal('query_cost_usd', 10, 4);
            $table->decimal('domain_budget_usd', 15, 2);
            $table->decimal('month_to_date_cost_usd', 15, 2);
            $table->boolean('budget_alert_triggered')->default(false); // 342.2 & 342.5 Edge case
            $table->timestamps();
        });

        Schema::create('data_asset_business_value_attributions', function (Blueprint $table) {
            $table->id();
            $table->string('attribution_code')->unique();
            $table->string('dataset_name');
            $table->string('use_case_title');
            $table->string('attribution_method'); // CONSERVATIVE_COST_AVOIDANCE, REVENUE_UPLIFT
            $table->decimal('attributed_value_usd', 18, 2);
            $table->boolean('finance_reviewed_and_approved')->default(false); // 342.3, 342.4, 342.6 Risk
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_asset_business_value_attributions');
        Schema::dropIfExists('data_product_query_cost_trackers');
    }
};
