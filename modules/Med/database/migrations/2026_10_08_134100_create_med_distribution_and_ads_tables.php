<?php

declare(strict_types=1);

namespace Modules\Med\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 134.1 Distribution channels & content streaming/broadcast logs
        Schema::create('med_distribution_channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('channel_code')->unique();
            $table->string('name');
            $table->string('channel_type'); // APP_STREAMING, DIGITAL_OOH, IN_VENUE_SCREEN, SOCIAL_BROADCAST
            $table->double('revenue_share_pct', 5, 2)->default(70.0); // 70% to publisher/creator
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });

        // 134.2 Advertising campaigns & yield management
        Schema::create('med_ad_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('campaign_code')->unique();
            $table->string('advertiser_entity_id');
            $table->string('agency_entity_id')->nullable(); // Agency intermediary
            $table->double('agency_commission_pct', 5, 2)->default(0.0); // e.g. 15.0%
            $table->string('channel_id');
            $table->bigInteger('target_impressions');
            $table->bigInteger('verified_impressions')->default(0);
            $table->bigInteger('floor_cpm_minor'); // yield floor: e.g. 50,000 IDR per 1k impressions
            $table->bigInteger('actual_cpm_minor');
            $table->bigInteger('total_spend_minor')->default(0);
            $table->bigInteger('agency_commission_minor')->default(0);
            $table->bigInteger('publisher_share_minor')->default(0);
            $table->bigInteger('platform_share_minor')->default(0);
            $table->string('status')->default('SCHEDULED'); // SCHEDULED, RUNNING, COMPLETED, CANCELLED
            $table->timestamps();

            $table->index(['advertiser_entity_id', 'status']);
        });

        // 134.4 Sponsorship cross-lini contracts
        Schema::create('med_sponsorship_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('package_code')->unique();
            $table->string('sponsor_brand_id');
            $table->string('event_or_entity_ref'); // VENUE-EVENT-101, ESPORTS-TEAM, HEALTH-TALK
            $table->string('package_tier'); // TITLE_SPONSOR, PLATINUM, GOLD
            $table->bigInteger('total_sponsorship_minor');
            $table->json('bundled_channels'); // ['DIGITAL_APP', 'VENUE_OOH', 'BROADCAST_SPOT']
            $table->string('status')->default('CONFIRMED');
            $table->timestamps();

            $table->index(['sponsor_brand_id', 'package_tier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_sponsorship_packages');
        Schema::dropIfExists('med_ad_campaigns');
        Schema::dropIfExists('med_distribution_channels');
    }
};
