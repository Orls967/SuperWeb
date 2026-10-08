<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cx_voc_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('feedback_code')->unique();
            $table->string('customer_id')->index();
            $table->string('business_line')->index();
            $table->integer('nps_score'); // 0 to 10
            $table->string('sentiment'); // POSITIVE, NEUTRAL, NEGATIVE
            $table->string('theme'); // SERVICE_SPEED, PRODUCT_QUALITY, PRICING, STAFF_COURTESY
            $table->boolean('closed_loop_case_opened')->default(false); // 250.6
            $table->string('closed_loop_status')->default('NOT_REQUIRED'); // NOT_REQUIRED, OPEN, IN_PROGRESS, RESOLVED
            $table->timestamps();
        });

        Schema::create('cx_journey_steps', function (Blueprint $table) {
            $table->id();
            $table->string('journey_code'); // JOURNEY_HEAL, JOURNEY_STAY, JOURNEY_BUY
            $table->string('step_name');
            $table->integer('visitors_count');
            $table->integer('drop_off_count');
            $table->decimal('drop_off_rate_pct', 5, 2);
            $table->timestamps();
        });

        Schema::create('cx_personalization_interactions', function (Blueprint $table) {
            $table->id();
            $table->string('interaction_code')->unique();
            $table->string('customer_id')->index();
            $table->string('touchpoint'); // MOBILE_APP, WEB_PORTAL, EMAIL, SMS
            $table->boolean('user_consent_granted')->default(true); // 250.3, 250.5
            $table->boolean('frequency_cap_exceeded')->default(false);
            $table->string('message_payload');
            $table->timestamps();
        });

        Schema::create('cx_financial_correlation_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_code')->unique();
            $table->string('metric_pair'); // NPS_VS_ANNUAL_SPEND, CSAT_VS_RETENTION_RATE
            $table->decimal('pearson_r_coefficient', 5, 3);
            $table->boolean('is_correlation_explicitly_labeled')->default(true); // 250.7
            $table->text('disclaimer_text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cx_financial_correlation_models');
        Schema::dropIfExists('cx_personalization_interactions');
        Schema::dropIfExists('cx_journey_steps');
        Schema::dropIfExists('cx_voc_feedback');
    }
};
