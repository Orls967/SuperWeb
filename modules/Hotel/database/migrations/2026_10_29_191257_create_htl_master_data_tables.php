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
        Schema::table('htl_properties', function (Blueprint $table) {
            if (! Schema::hasColumn('htl_properties', 'tax_pbjt_bps')) {
                $table->unsignedInteger('tax_pbjt_bps')->default(1000)->after('total_rooms');
            }
            if (! Schema::hasColumn('htl_properties', 'service_charge_bps')) {
                $table->unsignedInteger('service_charge_bps')->default(1000)->after('tax_pbjt_bps');
            }
        });

        Schema::create('htl_room_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id');
            $table->string('code', 32);
            $table->string('name', 128);
            $table->unsignedInteger('base_capacity')->default(2);
            $table->unsignedInteger('overbooking_max_percent')->default(0);
            $table->timestamps();

            $table->foreign('property_id')
                ->references('id')
                ->on('htl_properties')
                ->cascadeOnDelete();

            $table->unique(['property_id', 'code'], 'uniq_htl_rt_prop_code');
        });

        Schema::table('htl_rooms', function (Blueprint $table) {
            if (! Schema::hasColumn('htl_rooms', 'room_type_id')) {
                $table->uuid('room_type_id')->nullable()->after('property_id');
                $table->foreign('room_type_id')
                    ->references('id')
                    ->on('htl_room_types')
                    ->nullOnDelete();
            }
        });

        Schema::create('htl_rate_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id');
            $table->string('code', 32);
            $table->string('name', 128);
            $table->string('plan_type', 32)->default('BAR');
            $table->unsignedInteger('free_cancellation_hours')->default(24);
            $table->unsignedInteger('penalty_nights')->default(1);
            $table->boolean('requires_deposit')->default(false);
            $table->timestamps();

            $table->foreign('property_id')
                ->references('id')
                ->on('htl_properties')
                ->cascadeOnDelete();

            $table->unique(['property_id', 'code'], 'uniq_htl_rp_prop_code');
        });

        Schema::create('htl_rate_calendar', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id');
            $table->uuid('room_type_id');
            $table->uuid('rate_plan_id');
            $table->date('calendar_date');
            $table->unsignedBigInteger('rate_idr');
            $table->timestamps();

            $table->foreign('property_id')
                ->references('id')
                ->on('htl_properties')
                ->cascadeOnDelete();

            $table->foreign('room_type_id')
                ->references('id')
                ->on('htl_room_types')
                ->cascadeOnDelete();

            $table->foreign('rate_plan_id')
                ->references('id')
                ->on('htl_rate_plans')
                ->cascadeOnDelete();

            $table->unique(['property_id', 'room_type_id', 'rate_plan_id', 'calendar_date'], 'uniq_htl_rate_cal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('htl_rate_calendar');
        Schema::dropIfExists('htl_rate_plans');

        Schema::table('htl_rooms', function (Blueprint $table) {
            if (Schema::hasColumn('htl_rooms', 'room_type_id')) {
                $table->dropForeign(['room_type_id']);
                $table->dropColumn('room_type_id');
            }
        });

        Schema::dropIfExists('htl_room_types');

        Schema::table('htl_properties', function (Blueprint $table) {
            if (Schema::hasColumn('htl_properties', 'service_charge_bps')) {
                $table->dropColumn('service_charge_bps');
            }
            if (Schema::hasColumn('htl_properties', 'tax_pbjt_bps')) {
                $table->dropColumn('tax_pbjt_bps');
            }
        });
    }
};
