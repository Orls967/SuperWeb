<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Periksa data orphan sebelum foreign key diterapkan
        $orphans = [];

        // 1. htl_folio_items.folio_id -> htl_folios.id
        if (Schema::hasTable('htl_folio_items') && Schema::hasTable('htl_folios')) {
            $folioOrphans = DB::table('htl_folio_items')
                ->whereNotNull('folio_id')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('htl_folios')
                        ->whereColumn('htl_folios.id', 'htl_folio_items.folio_id');
                })
                ->count();

            if ($folioOrphans > 0) {
                $orphans['htl_folio_items.folio_id'] = $folioOrphans;
            }
        }

        // 2. htl_reservations.property_id -> htl_properties.id
        // 3. htl_reservations.room_id -> htl_rooms.id
        if (Schema::hasTable('htl_reservations')) {
            if (Schema::hasTable('htl_properties')) {
                $propOrphans = DB::table('htl_reservations')
                    ->whereNotNull('property_id')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('htl_properties')
                            ->whereColumn('htl_properties.id', 'htl_reservations.property_id');
                    })
                    ->count();

                if ($propOrphans > 0) {
                    $orphans['htl_reservations.property_id'] = $propOrphans;
                }
            }

            if (Schema::hasTable('htl_rooms')) {
                $roomOrphans = DB::table('htl_reservations')
                    ->whereNotNull('room_id')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('htl_rooms')
                            ->whereColumn('htl_rooms.id', 'htl_reservations.room_id');
                    })
                    ->count();

                if ($roomOrphans > 0) {
                    $orphans['htl_reservations.room_id'] = $roomOrphans;
                }
            }
        }

        // 4. htl_timeshare_investors.timeshare_unit_id -> htl_timeshare_units.id
        if (Schema::hasTable('htl_timeshare_investors') && Schema::hasTable('htl_timeshare_units')) {
            $timeshareOrphans = DB::table('htl_timeshare_investors')
                ->whereNotNull('timeshare_unit_id')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('htl_timeshare_units')
                        ->whereColumn('htl_timeshare_units.id', 'htl_timeshare_investors.timeshare_unit_id');
                })
                ->count();

            if ($timeshareOrphans > 0) {
                $orphans['htl_timeshare_investors.timeshare_unit_id'] = $timeshareOrphans;
            }
        }

        if (! empty($orphans)) {
            $details = [];
            foreach ($orphans as $col => $cnt) {
                $details[] = "{$col}: {$cnt}";
            }
            $message = 'Foreign key migration aborted due to orphan records found: ['.implode(', ', $details)."]. Run 'php artisan hotel:report-orphans' to inspect orphan rows before applying constraints.";
            throw new RuntimeException($message);
        }

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
