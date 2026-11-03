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
        Schema::create('htl_room_nights', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id');
            $table->uuid('room_type_id');
            $table->uuid('room_id')->nullable();
            $table->uuid('reservation_id')->nullable();
            $table->date('night_date');
            $table->string('status', 32)->default('sold'); // held, sold, out_of_order
            $table->timestamps();

            $table->foreign('property_id')
                ->references('id')
                ->on('htl_properties')
                ->cascadeOnDelete();

            $table->foreign('room_type_id')
                ->references('id')
                ->on('htl_room_types')
                ->cascadeOnDelete();

            $table->foreign('room_id')
                ->references('id')
                ->on('htl_rooms')
                ->nullOnDelete();

            $table->foreign('reservation_id')
                ->references('id')
                ->on('htl_reservations')
                ->nullOnDelete();

            $table->index(['property_id', 'room_type_id', 'night_date', 'status'], 'idx_htl_rn_search');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('htl_room_nights');
    }
};
