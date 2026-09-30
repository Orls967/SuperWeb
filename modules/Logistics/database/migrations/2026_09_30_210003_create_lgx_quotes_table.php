<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('shipper_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('origin_location_id')->constrained('lgx_locations')->cascadeOnDelete();
            $table->foreignId('destination_location_id')->constrained('lgx_locations')->cascadeOnDelete();
            $table->string('service_level', 32);
            $table->string('mode', 32);
            $table->json('packages_payload');
            $table->decimal('actual_weight_kg', 10, 4);
            $table->decimal('chargeable_weight_kg', 10, 4);
            $table->unsignedBigInteger('base_freight_idr');
            $table->json('surcharges_breakdown');
            $table->unsignedBigInteger('total_surcharges_idr')->default(0);
            $table->unsignedBigInteger('subtotal_idr');
            $table->decimal('vat_rate', 6, 4)->default(0.1100);
            $table->unsignedBigInteger('vat_amount_idr');
            $table->unsignedBigInteger('total_amount_idr');
            $table->string('payload_hash', 64)->index();
            $table->timestamp('expires_at')->index();
            $table->boolean('is_booked')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_quotes');
    }
};
