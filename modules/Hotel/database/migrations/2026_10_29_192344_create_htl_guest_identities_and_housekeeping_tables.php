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
        Schema::create('htl_guest_identities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('reservation_id')->nullable();
            $table->string('identity_type', 16)->default('KTP');
            $table->text('identity_number_encrypted');
            $table->string('identity_number_bidx', 64)->index();
            $table->string('full_name', 128);
            $table->string('phone', 32)->nullable();
            $table->timestamps();

            $table->foreign('reservation_id')
                ->references('id')
                ->on('htl_reservations')
                ->nullOnDelete();
        });

        Schema::create('htl_housekeeping_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('room_id');
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->foreignId('changed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->foreign('room_id')
                ->references('id')
                ->on('htl_rooms')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('htl_housekeeping_logs');
        Schema::dropIfExists('htl_guest_identities');
    }
};
