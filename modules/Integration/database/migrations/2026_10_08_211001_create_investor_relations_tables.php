<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 211.2: Non-GAAP to GAAP financial reconciliation bridges
        Schema::create('fin_ir_nongaap_bridges', function (Blueprint $table) {
            $table->id();
            $table->string('bridge_code')->unique();
            $table->string('period_code');
            $table->string('metric_name'); // e.g. ADJUSTED_EBITDA
            $table->decimal('reported_nongaap_amount_idr', 18, 2);
            $table->decimal('gaap_operating_profit_idr', 18, 2);
            $table->decimal('reconciling_items_net_idr', 18, 2);
            $table->decimal('bridge_discrepancy_idr', 18, 2)->default(0.00); // GAAP + Net items == Non-GAAP
            $table->timestamps();
        });

        // 211.4: Shareholder corporate actions and token supply balancing
        Schema::create('fin_ir_corporate_actions', function (Blueprint $table) {
            $table->id();
            $table->string('action_code')->unique();
            $table->string('action_type'); // STOCK_SPLIT, TOKEN_DISTRIBUTION, RIGHTS_ISSUE
            $table->decimal('pre_action_token_supply', 24, 4);
            $table->decimal('delta_tokens_created', 24, 4);
            $table->decimal('post_action_token_supply', 24, 4);
            $table->decimal('balance_check_variance', 24, 4)->default(0.0000); // Pre + Delta == Post
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_ir_corporate_actions');
        Schema::dropIfExists('fin_ir_nongaap_bridges');
    }
};
