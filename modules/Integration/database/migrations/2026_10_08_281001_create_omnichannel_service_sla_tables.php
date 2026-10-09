<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_service_slas', function (Blueprint $table) {
            $table->id();
            $table->string('sla_code')->unique();
            $table->string('customer_segment'); // CONSUMER, SMB, ENTERPRISE, GOVERNMENT
            $table->string('source_type')->default('CONTRACT'); // CONTRACT, PUBLIC_POLICY
            $table->integer('target_response_minutes');
            $table->integer('actual_response_minutes')->nullable();
            $table->boolean('is_sla_breached')->default(false); // 281.1 & 281.4
            $table->decimal('sla_credit_issued_usd', 15, 2)->default(0.00); // 281.1 & 281.4
            $table->timestamps();
        });

        Schema::create('customer_channel_parity_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code')->unique();
            $table->decimal('web_price_usd', 15, 2);
            $table->decimal('app_price_usd', 15, 2);
            $table->decimal('store_price_usd', 15, 2);
            $table->decimal('call_center_price_usd', 15, 2);
            $table->boolean('is_parity_divergent')->default(false); // 281.2 & 281.4
            $table->timestamps();
        });

        Schema::create('customer_support_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number')->unique();
            $table->string('customer_id')->index();
            $table->string('issue_category');
            $table->string('current_tier')->default('TIER_1'); // TIER_1, TIER_2, SPECIALIST
            $table->text('warm_handoff_context_json')->nullable(); // 281.3 & 281.6
            $table->integer('repeat_contact_count')->default(1); // 281.7
            $table->boolean('supervisor_alert_triggered')->default(false); // 281.7
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_support_cases');
        Schema::dropIfExists('customer_channel_parity_items');
        Schema::dropIfExists('customer_service_slas');
    }
};
