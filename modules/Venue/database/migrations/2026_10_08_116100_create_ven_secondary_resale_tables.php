<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 116.1 Official Ticket Resale Marketplace
        Schema::create('ven_ticket_resales', function (Blueprint $table) {
            $table->id();
            $table->string('resale_code', 32)->unique();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('seller_user_id');
            $table->unsignedBigInteger('buyer_user_id')->nullable();
            $table->bigInteger('original_face_value_idr');
            $table->bigInteger('resale_price_idr'); // Capped at 120% of original face value
            $table->bigInteger('platform_fee_idr')->default(0);
            $table->bigInteger('entertainment_tax_idr')->default(0);
            $table->integer('transfer_count')->default(0); // Max 1 transfer permitted
            $table->string('new_ticket_hash', 64)->nullable();
            $table->string('status', 32)->default('LISTED'); // LISTED, SOLD, CANCELLED
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('ven_entertainment_tickets')->cascadeOnDelete();
        });

        // 116.4 Waitlists & Timed Seat Releases
        Schema::create('ven_ticket_waitlists', function (Blueprint $table) {
            $table->id();
            $table->string('waitlist_code', 32)->unique();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('zone_id');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('offer_expires_at')->nullable(); // 15-minute timer
            $table->string('status', 32)->default('WAITING'); // WAITING, OFFERED, CLAIMED, EXPIRED
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('ven_entertainment_events')->cascadeOnDelete();
            $table->foreign('zone_id')->references('id')->on('ven_entertainment_zones')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ven_ticket_waitlists');
        Schema::dropIfExists('ven_ticket_resales');
    }
};
