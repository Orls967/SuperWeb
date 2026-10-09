<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppm_corporate_ventures', function (Blueprint $table) {
            $table->id();
            $table->string('venture_code')->unique();
            $table->string('venture_name');
            $table->string('option_type'); // BUILD_IN_HOUSE, INCUBATE, JOINT_VENTURE, TOKEN_INVESTMENT
            $table->string('funnel_stage')->default('EXPERIMENTS'); // IDEAS, EXPERIMENTS, PILOTS, SCALED, KILLED, EXITED
            $table->decimal('current_valuation', 15, 2);
            $table->decimal('total_approved_funding', 15, 2);
            $table->decimal('disbursed_funding', 15, 2)->default(0);
            $table->boolean('shared_platform_access_active')->default(true);
            $table->boolean('is_internal_competitor')->default(false); // 234.6 edge case
            $table->boolean('non_compete_isolated')->default(false);
            $table->timestamps();
        });

        Schema::create('ppm_venture_funding_tranches', function (Blueprint $table) {
            $table->id();
            $table->string('tranche_code')->unique();
            $table->string('venture_code')->index();
            $table->string('tranche_stage'); // SEED, SERIES_A, SERIES_B
            $table->decimal('approved_amount', 15, 2);
            $table->string('milestone_description');
            $table->boolean('milestone_achieved')->default(false);
            $table->boolean('is_audited')->default(false);
            $table->boolean('disbursed')->default(false);
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ppm_incubation_asset_usage', function (Blueprint $table) {
            $table->id();
            $table->string('venture_code')->index();
            $table->string('shared_asset_type'); // MARKETPLACE, DATA_API, LOGISTICS, PAYMENT_GATEWAY
            $table->decimal('metered_units', 15, 2);
            $table->decimal('cost_rate', 15, 2);
            $table->decimal('total_shared_cost', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppm_incubation_asset_usage');
        Schema::dropIfExists('ppm_venture_funding_tranches');
        Schema::dropIfExists('ppm_corporate_ventures');
    }
};
