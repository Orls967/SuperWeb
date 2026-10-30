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
        Schema::table('htl_folio_items', function (Blueprint $table) {
            $table->foreign('folio_id')
                ->references('id')
                ->on('htl_folios')
                ->cascadeOnDelete();
        });

        Schema::table('htl_reservations', function (Blueprint $table) {
            $table->foreign('property_id')
                ->references('id')
                ->on('htl_properties')
                ->cascadeOnDelete();

            $table->foreign('room_id')
                ->references('id')
                ->on('htl_rooms')
                ->nullOnDelete();
        });

        Schema::table('htl_timeshare_investors', function (Blueprint $table) {
            $table->foreign('timeshare_unit_id')
                ->references('id')
                ->on('htl_timeshare_units')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('htl_timeshare_investors', function (Blueprint $table) {
            $table->dropForeign(['timeshare_unit_id']);
        });

        Schema::table('htl_reservations', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->dropForeign(['property_id']);
        });

        Schema::table('htl_folio_items', function (Blueprint $table) {
            $table->dropForeign(['folio_id']);
        });
    }
};
