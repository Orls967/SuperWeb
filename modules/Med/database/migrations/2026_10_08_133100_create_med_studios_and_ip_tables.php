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
        // 133.1 & 133.5 Studios & production facilities
        Schema::create('med_studios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('studio_code')->unique();
            $table->string('name');
            $table->string('facility_type'); // SOUND_STAGE, VIRTUAL_PRODUCTION, PODCAST_ROOM, EDITING_SUITE
            $table->string('location_city');
            $table->bigInteger('hourly_rate_minor');
            $table->bigInteger('full_day_rate_minor');
            $table->string('status')->default('AVAILABLE');
            $table->timestamps();
        });

        // Studio Bookings for scheduling & conflict prevention
        Schema::create('med_studio_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('booking_code')->unique();
            $table->string('studio_id');
            $table->string('project_id')->nullable();
            $table->string('client_entity_id');
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->bigInteger('total_price_minor');
            $table->string('status')->default('CONFIRMED'); // CONFIRMED, CANCELLED, COMPLETED
            $table->timestamps();

            $table->index(['studio_id', 'start_time', 'end_time']);
        });

        // 133.2 Projects & production lifecycle
        Schema::create('med_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('project_code')->unique();
            $table->string('title');
            $table->string('genre'); // MOVIE, SERIES, COMMERCIAL, PODCAST, DOCS
            $table->string('phase')->default('BRIEF'); // BRIEF, PRE_PROD, SHOOT, POST, DELIVERED, COMPLETED
            $table->bigInteger('budget_limit_minor');
            $table->bigInteger('crew_cost_minor')->default(0);
            $table->bigInteger('vendor_cost_minor')->default(0);
            $table->bigInteger('studio_cost_minor')->default(0);
            $table->bigInteger('total_production_cost_minor')->default(0);
            $table->bigInteger('box_office_revenue_minor')->default(0);
            $table->boolean('is_capitalized_as_asset')->default(false);
            $table->timestamps();

            $table->index(['genre', 'phase']);
        });

        // 133.3 Talent & creator contracts
        Schema::create('med_talent_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contract_code')->unique();
            $table->string('project_id');
            $table->string('talent_party_id');
            $table->string('role_name'); // ACTOR, DIRECTOR, CREATOR, COMPOSER
            $table->bigInteger('upfront_fee_minor');
            $table->double('backend_percentage', 5, 2)->default(0.0); // e.g. 5.00%
            $table->bigInteger('calculated_royalty_minor')->default(0);
            $table->string('payout_status')->default('HOLD'); // HOLD, APPROVED, PAID
            $table->timestamps();

            $table->index(['project_id', 'talent_party_id']);
        });

        // 133.4 IP registry & monetization licenses
        Schema::create('med_ip_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ip_code')->unique();
            $table->string('title');
            $table->string('ip_type'); // TRADEMARK, SONG, SHOW_FORMAT, CHARACTER
            $table->string('owner_entity_id');
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('med_ip_licenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('license_code')->unique();
            $table->string('ip_id');
            $table->string('licensee_entity_id');
            $table->string('channel'); // VENUE, HOTEL_IN_ROOM, STORE_MERCH, STREAMING
            $table->string('territory'); // ID, SG, GLOBAL
            $table->date('start_date');
            $table->date('end_date');
            $table->double('royalty_rate_pct', 5, 2);
            $table->bigInteger('minimum_guarantee_minor')->default(0);
            $table->string('status')->default('ACTIVE');
            $table->timestamps();

            $table->index(['ip_id', 'channel', 'territory', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_ip_licenses');
        Schema::dropIfExists('med_ip_assets');
        Schema::dropIfExists('med_talent_contracts');
        Schema::dropIfExists('med_projects');
        Schema::dropIfExists('med_studio_bookings');
        Schema::dropIfExists('med_studios');
    }
};
