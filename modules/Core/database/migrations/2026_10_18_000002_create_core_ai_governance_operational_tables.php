<?php

declare(strict_types=1);

namespace Modules\Core\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 64.1 Dynamic Pricing & Floor/Ceiling Guardrail Rules
        Schema::create('core_ai_pricing_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('rule_code', 64)->unique();
            $table->string('item_code', 64)->index();
            $table->string('item_name', 255);
            $table->string('channel', 32)->default('ALL');
            $table->bigInteger('base_price_idr');
            $table->bigInteger('floor_price_idr');
            $table->bigInteger('ceiling_price_idr');
            $table->decimal('elasticity_coefficient', 4, 2)->default(-0.50);
            $table->decimal('max_surge_multiplier', 4, 2)->default(1.50);
            $table->integer('decay_hours_threshold')->default(24);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['item_code', 'channel'], 'idx_c_ai_pr_item_chan');
        });

        // 64.2 Transaction Anomaly Detection & Settlement Quarantine
        Schema::create('core_ai_anomaly_quarantines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('quarantine_code', 64)->unique();
            $table->string('transaction_ref', 64)->index();
            $table->string('customer_ref', 64)->index();
            $table->bigInteger('amount_idr');
            $table->decimal('risk_score', 5, 4);
            $table->json('anomaly_reasons');
            $table->foreignUuid('decision_snapshot_id')->nullable()->constrained('core_ai_decision_snapshots', 'id', 'fk_c_ai_quar_snap')->nullOnDelete();
            $table->string('status', 32)->default('QUARANTINED');
            $table->timestamp('quarantined_at');
            $table->timestamp('released_at')->nullable();
            $table->string('released_by', 255)->nullable();
            $table->text('release_justification')->nullable();
            $table->timestamps();

            $table->index(['status', 'risk_score'], 'idx_c_ai_quar_stat_risk');
        });

        // 64.3 Prescriptive Stock Planning & Reorder Point Recommendations
        Schema::create('core_ai_stock_recommendations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('recommendation_code', 64)->unique();
            $table->string('item_code', 64)->index();
            $table->string('item_name', 255);
            $table->integer('current_stock');
            $table->integer('calculated_rop');
            $table->integer('safety_stock');
            $table->integer('suggested_order_qty');
            $table->decimal('seasonal_factor', 4, 2)->default(1.00);
            $table->bigInteger('estimated_cost_idr');
            $table->boolean('requires_human_approval')->default(false);
            $table->foreignUuid('decision_snapshot_id')->nullable()->constrained('core_ai_decision_snapshots', 'id', 'fk_c_ai_stock_snap')->nullOnDelete();
            $table->string('status', 32)->default('PROPOSED');
            $table->string('approved_po_ref', 64)->nullable();
            $table->timestamps();

            $table->index(['status', 'item_code'], 'idx_c_ai_stock_stat_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_ai_stock_recommendations');
        Schema::dropIfExists('core_ai_anomaly_quarantines');
        Schema::dropIfExists('core_ai_pricing_rules');
    }
};
