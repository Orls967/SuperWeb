<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Perluasan tabel ven_ticket_resales untuk Fase 116
        Schema::table('ven_ticket_resales', function (Blueprint $table) {
            $table->bigInteger('promoter_royalty_idr')->default(0)->after('entertainment_tax_idr');
            $table->bigInteger('seller_payout_idr')->default(0)->after('promoter_royalty_idr');
            $table->bigInteger('tax_pbjt_idr')->default(0)->after('seller_payout_idr');
            $table->dateTime('escrow_held_at')->nullable()->after('status');
            $table->dateTime('settled_at')->nullable()->after('escrow_held_at');
        });

        // 2. Paket Dynamic Bundling (Event + Hotel + Transport/Shuttle + F&B)
        Schema::create('ven_dynamic_bundle_packages', function (Blueprint $table) {
            $table->id();
            $table->string('package_code', 32)->unique();
            $table->unsignedBigInteger('event_id');
            $table->string('package_name', 150);
            $table->bigInteger('base_price_idr');
            $table->bigInteger('floor_cost_idr');
            $table->bigInteger('final_price_idr');
            $table->integer('surge_bps')->default(0);
            $table->integer('lead_time_discount_bps')->default(0);
            $table->string('status', 32)->default('DRAFT'); // DRAFT, PUBLISHED, SUSPENDED, ARCHIVED
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('ven_entertainment_events')->cascadeOnDelete();
        });

        // 3. Komponen rincian dalam Dynamic Bundle
        Schema::create('ven_dynamic_bundle_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bundle_package_id');
            $table->string('component_type', 32); // TICKET, HOTEL, SHUTTLE, DINING
            $table->string('name', 150);
            $table->bigInteger('base_price_idr');
            $table->bigInteger('floor_cost_idr');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('bundle_package_id')->references('id')->on('ven_dynamic_bundle_packages')->cascadeOnDelete();
        });

        // 4. Reservasi kursi antrean waitlist (15-minute reservation timer)
        Schema::create('ven_waitlist_reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('waitlist_id');
            $table->string('token', 64)->unique();
            $table->dateTime('offered_at');
            $table->dateTime('expires_at');
            $table->dateTime('claimed_at')->nullable();
            $table->string('status', 32)->default('ACTIVE'); // ACTIVE, CLAIMED, EXPIRED
            $table->timestamps();

            $table->foreign('waitlist_id')->references('id')->on('ven_ticket_waitlists')->cascadeOnDelete();
        });

        // 5. Log deteksi bot dan velocity scalper (Anti-Scalping)
        Schema::create('ven_scalper_blacklist_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('reason_code', 64);
            $table->integer('detected_velocity_bps')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->string('device_fingerprint', 64)->nullable();
            $table->string('action_taken', 32)->default('BLOCKED'); // THROTTLED, BLOCKED
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // 6. Buku besar khusus pasar sekunder (Secondary Market Double-Entry Ledger)
        Schema::create('ven_secondary_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_code', 32)->unique();
            $table->unsignedBigInteger('resale_id')->nullable();
            $table->string('debit_account', 64);
            $table->string('credit_account', 64);
            $table->bigInteger('amount_idr');
            $table->string('description', 255);
            $table->timestamps();

            $table->foreign('resale_id')->references('id')->on('ven_ticket_resales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ven_secondary_ledger_entries');
        Schema::dropIfExists('ven_scalper_blacklist_logs');
        Schema::dropIfExists('ven_waitlist_reservations');
        Schema::dropIfExists('ven_dynamic_bundle_components');
        Schema::dropIfExists('ven_dynamic_bundle_packages');

        Schema::table('ven_ticket_resales', function (Blueprint $table) {
            $table->dropColumn([
                'promoter_royalty_idr',
                'seller_payout_idr',
                'tax_pbjt_idr',
                'escrow_held_at',
                'settled_at',
            ]);
        });
    }
};
