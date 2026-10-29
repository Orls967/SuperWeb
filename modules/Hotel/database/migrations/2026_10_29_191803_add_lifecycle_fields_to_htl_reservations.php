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
        Schema::table('htl_reservations', function (Blueprint $table) {
            $table->uuid('room_id')->nullable()->change();

            if (! Schema::hasColumn('htl_reservations', 'room_type_id')) {
                $table->uuid('room_type_id')->nullable()->after('property_id');
                $table->foreign('room_type_id')
                    ->references('id')
                    ->on('htl_room_types')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('htl_reservations', 'rate_plan_id')) {
                $table->uuid('rate_plan_id')->nullable()->after('room_type_id');
                $table->foreign('rate_plan_id')
                    ->references('id')
                    ->on('htl_rate_plans')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('htl_reservations', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->unique()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('htl_reservations', function (Blueprint $table) {
            if (Schema::hasColumn('htl_reservations', 'idempotency_key')) {
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            }
            if (Schema::hasColumn('htl_reservations', 'rate_plan_id')) {
                $table->dropForeign(['rate_plan_id']);
                $table->dropColumn('rate_plan_id');
            }
            if (Schema::hasColumn('htl_reservations', 'room_type_id')) {
                $table->dropForeign(['room_type_id']);
                $table->dropColumn('room_type_id');
            }
        });
    }
};
