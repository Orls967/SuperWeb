<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_marketing_segments', function (Blueprint $table) {
            $table->id();
            $table->string('segment_code')->unique();
            $table->string('segment_name');
            $table->string('criteria_type'); // RFM, BEHAVIOR, LIFECYCLE, VALUE_TIER
            $table->decimal('min_score', 8, 2)->default(0);
            $table->decimal('max_score', 8, 2)->default(100);
            $table->timestamps();
        });

        Schema::create('crm_marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_code')->unique();
            $table->string('campaign_name');
            $table->string('segment_code')->index();
            $table->decimal('allocated_budget', 15, 2);
            $table->decimal('spent_budget', 15, 2)->default(0);
            $table->decimal('max_budget_limit', 15, 2);
            $table->boolean('requires_override_approval')->default(false);
            $table->string('status')->default('ACTIVE'); // ACTIVE, PAUSED, COMPLETED
            $table->timestamps();
        });

        Schema::create('crm_customer_communication_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('customer_golden_id')->unique();
            $table->integer('global_daily_cap')->default(3);
            $table->boolean('opted_out')->default(false);
            $table->boolean('fatigue_suppressed')->default(false);
            $table->string('linked_service_desk_case')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_campaign_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('dispatch_code')->unique();
            $table->string('campaign_code')->index();
            $table->string('customer_golden_id')->index();
            $table->string('channel'); // PUSH, IN_APP, EMAIL
            $table->string('status'); // SENT, SUPPRESSED_CAP, SUPPRESSED_FATIGUE, SUPPRESSED_OPT_OUT
            $table->decimal('cost', 15, 2)->default(0);
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_conversion_attributions', function (Blueprint $table) {
            $table->id();
            $table->string('conversion_code')->unique();
            $table->string('customer_golden_id')->index();
            $table->decimal('conversion_value', 15, 2);
            $table->string('model_type'); // FIRST_TOUCH, LAST_TOUCH, MULTI_TOUCH_LINEAR
            $table->json('channel_touchpoints');
            $table->json('attributed_weights');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_conversion_attributions');
        Schema::dropIfExists('crm_campaign_dispatches');
        Schema::dropIfExists('crm_customer_communication_profiles');
        Schema::dropIfExists('crm_marketing_campaigns');
        Schema::dropIfExists('crm_marketing_segments');
    }
};
