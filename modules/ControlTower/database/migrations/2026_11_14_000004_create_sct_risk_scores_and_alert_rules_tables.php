<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sct_order_risk_scores')) {
            Schema::create('sct_order_risk_scores', function (Blueprint $table): void {
                $table->id();
                $table->string('item_code', 32)->index();
                $table->string('order_reference', 64)->nullable()->index();
                $table->unsignedInteger('risk_score')->default(0);
                $table->string('risk_tier', 16)->index();
                $table->unsignedInteger('shortfall_component')->default(0);
                $table->unsignedInteger('lead_time_component')->default(0);
                $table->unsignedInteger('shelf_life_component')->default(0);
                $table->unsignedInteger('transit_component')->default(0);
                $table->json('factors_json')->nullable();
                $table->text('mitigation_recommendation')->nullable();
                $table->timestamp('scored_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sct_alert_rules')) {
            Schema::create('sct_alert_rules', function (Blueprint $table): void {
                $table->id();
                $table->string('rule_code', 64)->unique();
                $table->string('category', 32)->index();
                $table->string('severity', 16);
                $table->unsignedInteger('threshold_bps')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('sct_disruption_alerts')) {
            Schema::table('sct_disruption_alerts', function (Blueprint $table): void {
                if (! Schema::hasColumn('sct_disruption_alerts', 'dedup_key')) {
                    $table->string('dedup_key', 128)->nullable()->unique()->after('alert_code');
                }
                if (! Schema::hasColumn('sct_disruption_alerts', 'mitigation_proposal_json')) {
                    $table->json('mitigation_proposal_json')->nullable()->after('status');
                }
                if (! Schema::hasColumn('sct_disruption_alerts', 'approved_by_user_id')) {
                    $table->unsignedBigInteger('approved_by_user_id')->nullable()->after('mitigation_proposal_json');
                    $table->foreign('approved_by_user_id')->references('id')->on('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sct_disruption_alerts')) {
            Schema::table('sct_disruption_alerts', function (Blueprint $table): void {
                if (Schema::hasColumn('sct_disruption_alerts', 'approved_by_user_id')) {
                    $table->dropForeign(['approved_by_user_id']);
                    $table->dropColumn('approved_by_user_id');
                }
                if (Schema::hasColumn('sct_disruption_alerts', 'mitigation_proposal_json')) {
                    $table->dropColumn('mitigation_proposal_json');
                }
                if (Schema::hasColumn('sct_disruption_alerts', 'dedup_key')) {
                    $table->dropUnique(['dedup_key']);
                    $table->dropColumn('dedup_key');
                }
            });
        }

        Schema::dropIfExists('sct_alert_rules');
        Schema::dropIfExists('sct_order_risk_scores');
    }
};
