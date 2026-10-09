<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_cross_line_service_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('bundle_booking_code')->unique();
            $table->decimal('total_package_price_usd', 12, 2);
            $table->decimal('vendor_hotel_share_usd', 12, 2);
            $table->decimal('vendor_fleet_share_usd', 12, 2);
            $table->boolean('settlement_sums_balanced')->default(true); // 390.4
            $table->boolean('partial_fulfillment_refunded')->default(false); // 390.2 & 390.5 Edge case
            $table->decimal('refund_amount_usd', 12, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_cross_line_service_bundles');
    }
};
