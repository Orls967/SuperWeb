<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number', 32)->unique()->index();
            $table->foreignId('shipper_id')->constrained('users')->cascadeOnDelete();
            $table->string('consignee_name');
            $table->string('consignee_phone');
            $table->json('consignee_address');
            $table->foreignId('origin_location_id')->constrained('lgx_locations')->cascadeOnDelete();
            $table->foreignId('destination_location_id')->constrained('lgx_locations')->cascadeOnDelete();
            $table->string('service_level', 32)->default('regular');
            $table->string('mode', 32)->default('road');
            $table->string('incoterm', 16)->nullable();
            $table->unsignedBigInteger('declared_value_idr')->default(0);
            $table->boolean('insured')->default(false);
            $table->unsignedBigInteger('cod_amount_idr')->default(0);
            $table->string('payment_terms', 16)->default('prepaid');
            $table->string('status', 32)->default('draft')->index();
            $table->unsignedBigInteger('total_chargeable_weight_g')->default(0);
            $table->unsignedBigInteger('total_amount_idr')->default(0);
            $table->unsignedBigInteger('cancellation_fee_idr')->default(0);
            $table->unsignedBigInteger('quote_id')->nullable()->index();
            $table->foreignId('driver_id')->nullable()->constrained('lgx_drivers')->nullOnDelete();
            $table->unsignedBigInteger('invoice_id')->nullable()->index();
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lgx_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('lgx_shipments')->cascadeOnDelete();
            $table->unsignedInteger('weight_g');
            $table->unsignedInteger('length_mm');
            $table->unsignedInteger('width_mm');
            $table->unsignedInteger('height_mm');
            $table->string('description');
            $table->string('hs_code', 8)->nullable();
            $table->string('dg_un_number', 16)->nullable();
            $table->string('dg_class', 16)->nullable();
            $table->integer('temp_min_c10')->nullable();
            $table->integer('temp_max_c10')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_packages');
        Schema::dropIfExists('lgx_shipments');
    }
};
