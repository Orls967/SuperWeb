<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ven_ticket_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->string('name', 128);
            $table->string('tier_code', 32);
            $table->string('tier_type', 32)->default('GENERAL_ADMISSION');
            $table->bigInteger('price_idr');
            $table->integer('quota');
            $table->boolean('is_numbered_seating')->default(false);
            $table->boolean('allows_reentry')->default(false);
            $table->timestamps();

            $table->unique(['event_id', 'tier_code']);
            $table->foreign('event_id')->references('id')->on('ven_entertainment_events')->cascadeOnDelete();
            $table->foreign('zone_id')->references('id')->on('ven_entertainment_zones')->nullOnDelete();
        });

        Schema::create('ven_inventory_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('tier_id');
            $table->integer('total_quota');
            $table->integer('held_count')->default(0);
            $table->integer('sold_count')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'tier_id']);
            $table->foreign('event_id')->references('id')->on('ven_entertainment_events')->cascadeOnDelete();
            $table->foreign('tier_id')->references('id')->on('ven_ticket_tiers')->cascadeOnDelete();
        });

        Schema::create('ven_seat_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('tier_id');
            $table->string('section_code', 32);
            $table->string('row_label', 8);
            $table->integer('seat_number');
            $table->string('status', 32)->default('AVAILABLE'); // AVAILABLE, HELD, BOOKED
            $table->timestamp('held_until')->nullable();
            $table->string('hold_token', 64)->nullable()->index();
            $table->string('idempotency_key', 128)->nullable()->index();
            $table->unsignedBigInteger('buyer_user_id')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'section_code', 'row_label', 'seat_number'], 'ven_seat_claims_seat_uniq');
            $table->index(['event_id', 'status', 'held_until']);
            $table->foreign('event_id')->references('id')->on('ven_entertainment_events')->cascadeOnDelete();
            $table->foreign('tier_id')->references('id')->on('ven_ticket_tiers')->cascadeOnDelete();
        });

        Schema::create('ven_quota_holds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('tier_id');
            $table->unsignedBigInteger('buyer_user_id');
            $table->integer('quantity');
            $table->string('hold_token', 64)->unique();
            $table->string('idempotency_key', 128)->unique();
            $table->string('status', 32)->default('HELD'); // HELD, CONSUMED, EXPIRED, CANCELLED
            $table->timestamp('held_until');
            $table->timestamps();

            $table->index(['event_id', 'status', 'held_until']);
            $table->foreign('event_id')->references('id')->on('ven_entertainment_events')->cascadeOnDelete();
            $table->foreign('tier_id')->references('id')->on('ven_ticket_tiers')->cascadeOnDelete();
        });

        Schema::table('ven_entertainment_tickets', function (Blueprint $table) {
            $table->string('ticket_uuid', 36)->nullable()->after('ticket_number');
            $table->string('prev_hash', 64)->nullable()->after('ticket_hash');
            $table->string('idempotency_key', 128)->nullable()->index()->after('prev_hash');
            $table->unsignedBigInteger('tier_id')->nullable()->after('event_id');
            $table->unsignedBigInteger('seat_claim_id')->nullable()->after('tier_id');

            $table->foreign('tier_id')->references('id')->on('ven_ticket_tiers')->nullOnDelete();
            $table->foreign('seat_claim_id')->references('id')->on('ven_seat_claims')->nullOnDelete();
        });

        Schema::create('ven_gate_scans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('gate_code', 32);
            $table->string('direction', 8)->default('IN'); // IN, OUT
            $table->string('scan_result', 16)->default('ALLOWED'); // ALLOWED, DENIED
            $table->string('denial_reason', 255)->nullable();
            $table->unsignedBigInteger('scanned_by_user_id')->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('ven_entertainment_tickets')->cascadeOnDelete();
        });

        if (Schema::hasTable('ven_entertainment_events') && ! Schema::hasColumn('ven_entertainment_events', 'status')) {
            Schema::table('ven_entertainment_events', function (Blueprint $table) {
                $table->string('status', 32)->default('OPEN')->after('current_ticket_price_idr');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ven_gate_scans');

        Schema::table('ven_entertainment_tickets', function (Blueprint $table) {
            $table->dropForeign(['tier_id']);
            $table->dropForeign(['seat_claim_id']);
            $table->dropColumn(['ticket_uuid', 'prev_hash', 'idempotency_key', 'tier_id', 'seat_claim_id']);
        });

        Schema::dropIfExists('ven_quota_holds');
        Schema::dropIfExists('ven_seat_claims');
        Schema::dropIfExists('ven_inventory_counters');
        Schema::dropIfExists('ven_ticket_tiers');
    }
};
