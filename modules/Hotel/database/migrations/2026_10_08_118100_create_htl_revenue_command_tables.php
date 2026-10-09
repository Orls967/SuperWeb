<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 118.1 Global Revenue Command Metrics
        Schema::create('htl_revenue_command_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('metric_code', 32)->unique();
            $table->string('region_city', 64);
            $table->integer('reporting_year');
            $table->integer('reporting_month');
            $table->bigInteger('adr_idr'); // Average Daily Rate
            $table->decimal('occupancy_percentage', 5, 2);
            $table->bigInteger('revpar_idr'); // RevPAR = ADR * occupancy
            $table->bigInteger('total_venue_ticket_gmv_idr')->default(0);
            $table->bigInteger('total_travel_bundle_gmv_idr')->default(0);
            $table->timestamps();
        });

        // 118.3 Syndication & JV Property Token Yield Distribution
        Schema::create('htl_syndication_investors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('syndication_code', 32)->unique();
            $table->uuid('property_id');
            $table->unsignedBigInteger('investor_user_id');
            $table->integer('fractional_tokens_held');
            $table->decimal('ownership_percentage', 5, 2);
            $table->bigInteger('distributed_yield_idr')->default(0);
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('htl_properties')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('htl_syndication_investors');
        Schema::dropIfExists('htl_revenue_command_metrics');
    }
};
