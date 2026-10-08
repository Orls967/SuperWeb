<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_jbp_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('jbp_code')->unique();
            $table->string('retailer_code')->index();
            $table->integer('target_volume_units');
            $table->integer('achieved_volume_units')->default(0);
            $table->decimal('base_incentive_rate_usd', 15, 2);
            $table->decimal('data_quality_incentive_usd', 15, 2)->default(0.00); // 262.7
            $table->decimal('planogram_penalty_usd', 15, 2)->default(0.00); // 262.3
            $table->decimal('net_settlement_incentive_usd', 15, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('distributor_pos_sellout_feeds', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique(); // 262.4
            $table->string('retailer_code')->index();
            $table->string('outlet_code');
            $table->date('batch_date');
            $table->integer('units_sold');
            $table->decimal('revenue_usd', 15, 2);
            $table->string('feed_status')->default('PROCESSED'); // PROCESSED, DELAYED_FALLBACK_USED (262.5)
            $table->decimal('forecast_confidence_score', 4, 3)->default(1.000); // 262.5
            $table->timestamps();
        });

        Schema::create('distributor_planogram_audits', function (Blueprint $table) {
            $table->id();
            $table->string('audit_code')->unique();
            $table->string('outlet_code')->index();
            $table->decimal('compliance_pct', 5, 2);
            $table->boolean('is_disputed')->default(false); // 262.6
            $table->string('dispute_photo_evidence_doc')->nullable(); // 262.6
            $table->string('dispute_resolution_outcome')->nullable(); // 262.6
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_planogram_audits');
        Schema::dropIfExists('distributor_pos_sellout_feeds');
        Schema::dropIfExists('distributor_jbp_agreements');
    }
};
