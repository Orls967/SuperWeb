<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 114.1 & 114.4 MICE & Wedding Contracts
        Schema::create('htl_mice_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_code', 32)->unique();
            $table->uuid('property_id');
            $table->string('event_type', 32); // CONFERENCE, EXHIBITION, CORPORATE_RETREAT, WEDDING
            $table->string('client_name', 128);
            $table->date('event_date');
            $table->bigInteger('contract_total_value_idr');
            $table->bigInteger('actual_banquet_cost_idr')->default(0);
            $table->integer('current_milestone_index')->default(1); // 1 = 20%, 2 = 30%, 3 = 40%, 4 = 10%
            $table->string('status', 32)->default('QUOTED'); // QUOTED, CONFIRMED, COMPLETED, CANCELLED
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('htl_properties')->cascadeOnDelete();
        });

        // 114.2 Room Block Allotment
        Schema::create('htl_room_block_allotments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('block_code', 32)->unique();
            $table->uuid('mice_contract_id');
            $table->date('check_in_date');
            $table->date('release_deadline_date'); // H-30 release cutoff
            $table->integer('allotted_rooms_count');
            $table->integer('confirmed_rooms_count')->default(0);
            $table->integer('released_rooms_count')->default(0);
            $table->string('status', 32)->default('HELD'); // HELD, RELEASED, SETTLED
            $table->timestamps();

            $table->foreign('mice_contract_id')->references('id')->on('htl_mice_contracts')->cascadeOnDelete();
        });

        // 114.3 Banquet Production Sheet (BOM event)
        Schema::create('htl_banquet_production_sheets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('mice_contract_id');
            $table->string('menu_package_name', 128);
            $table->integer('pax_count');
            $table->bigInteger('fnb_materials_cost_idr');
            $table->bigInteger('av_equipment_vendor_cost_idr');
            $table->bigInteger('decor_florist_vendor_cost_idr');
            $table->bigInteger('total_production_cost_idr');
            $table->timestamps();

            $table->foreign('mice_contract_id')->references('id')->on('htl_mice_contracts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('htl_banquet_production_sheets');
        Schema::dropIfExists('htl_room_block_allotments');
        Schema::dropIfExists('htl_mice_contracts');
    }
};
