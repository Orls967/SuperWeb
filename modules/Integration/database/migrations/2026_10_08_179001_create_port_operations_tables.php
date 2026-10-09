<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 179.1: Berth window reservations & vessel calls
        Schema::create('prt_berth_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reservation_code')->unique();
            $table->string('berth_number'); // BERTH-01, BERTH-02
            $table->string('vessel_name');
            $table->timestamp('window_start');
            $table->timestamp('window_end');
            $table->boolean('has_overlap_conflict')->default(false);
            $table->timestamps();
        });

        // 179.3: Yard slot allocations & container capacity
        Schema::create('prt_yard_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('allocation_code')->unique();
            $table->string('yard_block_id'); // BLOCK-A, BLOCK-B
            $table->string('container_number')->unique();
            $table->integer('max_block_capacity');
            $table->boolean('is_reefer')->default(false);
            $table->decimal('reefer_temp_c', 5, 2)->nullable();
            $table->boolean('reefer_temp_excursion')->default(false);
            $table->timestamps();
        });

        // 179.4: Terminal billing & port dues
        Schema::create('prt_terminal_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_code')->unique();
            $table->string('reservation_code');
            $table->integer('container_moves');
            $table->decimal('rate_per_move_idr', 18, 2);
            $table->decimal('port_dues_idr', 18, 2);
            $table->decimal('total_amount_idr', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prt_terminal_invoices');
        Schema::dropIfExists('prt_yard_allocations');
        Schema::dropIfExists('prt_berth_reservations');
    }
};
