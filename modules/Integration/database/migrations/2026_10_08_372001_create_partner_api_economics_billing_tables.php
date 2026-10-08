<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_api_billing_meterings', function (Blueprint $table) {
            $table->id();
            $table->string('meter_record_code')->unique();
            $table->string('partner_code');
            $table->string('request_id')->unique(); // 372.4 Retries not double-charged
            $table->integer('billable_calls')->default(1);
            $table->boolean('is_retry')->default(false);
            $table->timestamps();
        });

        Schema::create('partner_api_tier_quotas', function (Blueprint $table) {
            $table->id();
            $table->string('quota_code')->unique();
            $table->string('partner_code')->index();
            $table->integer('monthly_quota')->default(10000);
            $table->integer('current_usage')->default(0);
            $table->boolean('rate_limited_with_upgrade_notice')->default(false); // 372.5 Edge case
            $table->boolean('abruptly_disconnected')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_api_tier_quotas');
        Schema::dropIfExists('partner_api_billing_meterings');
    }
};
