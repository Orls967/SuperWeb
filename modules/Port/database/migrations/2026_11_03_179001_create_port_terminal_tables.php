<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prt_berths', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->integer('length_meters')->default(200);
            $table->decimal('max_draft_meters', 5, 2)->default(14.50);
            $table->string('status')->default('AVAILABLE'); // AVAILABLE, OCCUPIED, MAINTENANCE
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('prt_vessel_calls', function (Blueprint $table) {
            $table->id();
            $table->string('call_number')->unique();
            $table->string('vessel_name');
            $table->string('imo_number')->index();
            $table->unsignedBigInteger('line_party_id'); // ID shipping line / owner
            $table->timestamp('eta')->nullable();
            $table->timestamp('etd')->nullable();
            $table->timestamp('ata')->nullable();
            $table->timestamp('atd')->nullable();
            $table->foreignId('berth_id')->nullable()->constrained('prt_berths')->nullOnDelete();
            $table->string('status')->default('SCHEDULED'); // SCHEDULED, AT_BERTH, DEPARTED, CANCELLED
            $table->integer('weight')->default(1);
            $table->integer('duration_hours')->default(24);
            $table->json('schedule_certificate')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('line_party_id');
        });

        Schema::create('prt_yard_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->integer('capacity_teu')->default(500);
            $table->string('block_type')->default('STANDARD'); // STANDARD, REEFER, HAZMAT
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('prt_yard_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yard_block_id')->constrained('prt_yard_blocks')->cascadeOnDelete();
            $table->string('container_no')->index();
            $table->string('slot_coordinate'); // e.g. "B1-02-03-1"
            $table->string('status')->default('IN_YARD'); // IN_YARD, GATED_OUT, LOADED
            $table->timestamp('gate_in_at')->nullable();
            $table->timestamp('gate_out_at')->nullable();
            $table->timestamps();
        });

        Schema::create('prt_gate_passes', function (Blueprint $table) {
            $table->id();
            $table->string('pass_number')->unique();
            $table->string('container_no')->index();
            $table->string('pass_type'); // IN, OUT
            $table->string('vehicle_reg_no')->nullable();
            $table->unsignedBigInteger('line_party_id');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default('ISSUED'); // ISSUED, COMPLETED, CANCELLED
            $table->timestamps();

            $table->index('line_party_id');
        });

        Schema::create('prt_terminal_bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->unique();
            $table->foreignId('vessel_call_id')->constrained('prt_vessel_calls')->cascadeOnDelete();
            $table->unsignedBigInteger('line_party_id');
            $table->bigInteger('amount_idr')->default(0);
            $table->bigInteger('thc_amount_idr')->default(0);
            $table->bigInteger('dockage_amount_idr')->default(0);
            $table->bigInteger('storage_amount_idr')->default(0);
            $table->bigInteger('dnd_amount_idr')->default(0);
            $table->unsignedBigInteger('ledger_journal_id')->nullable();
            $table->string('status')->default('ISSUED'); // ISSUED, PAID, CANCELLED
            $table->timestamps();

            $table->index('line_party_id');
            $table->index('ledger_journal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prt_terminal_bills');
        Schema::dropIfExists('prt_gate_passes');
        Schema::dropIfExists('prt_yard_slots');
        Schema::dropIfExists('prt_yard_blocks');
        Schema::dropIfExists('prt_vessel_calls');
        Schema::dropIfExists('prt_berths');
    }
};
