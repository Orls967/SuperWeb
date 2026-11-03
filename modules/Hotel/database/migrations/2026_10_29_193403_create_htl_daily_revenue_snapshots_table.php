<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('htl_daily_revenue_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id');
            $table->date('business_date');
            $table->unsignedInteger('total_rooms');
            $table->unsignedInteger('occupied_rooms');
            $table->unsignedInteger('out_of_order_rooms');
            $table->unsignedInteger('occupancy_rate_bps');
            $table->unsignedBigInteger('total_room_revenue_idr');
            $table->unsignedBigInteger('total_fnb_revenue_idr');
            $table->unsignedBigInteger('total_tax_pbjt_idr');
            $table->unsignedBigInteger('total_service_charge_idr');
            $table->unsignedBigInteger('adr_idr');
            $table->unsignedBigInteger('revpar_idr');
            $table->timestamps();

            $table->foreign('property_id')
                ->references('id')
                ->on('htl_properties')
                ->cascadeOnDelete();

            $table->unique(['property_id', 'business_date'], 'uniq_htl_snapshot_prop_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('htl_daily_revenue_snapshots');
    }
};
