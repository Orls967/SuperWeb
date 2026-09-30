<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resto_orders', function (Blueprint $table) {
            $table->string('parking_ticket_number', 50)->nullable()->after('payment_method');
            $table->integer('parking_validation_hours')->default(0)->after('parking_ticket_number');
            $table->string('mall_voucher_code', 50)->nullable()->after('parking_validation_hours');
            $table->unsignedBigInteger('mall_voucher_discount')->default(0)->after('mall_voucher_code');
            $table->unsignedInteger('loyalty_points_earned')->default(0)->after('mall_voucher_discount');
        });
    }

    public function down(): void
    {
        Schema::table('resto_orders', function (Blueprint $table) {
            $table->dropColumn([
                'parking_ticket_number',
                'parking_validation_hours',
                'mall_voucher_code',
                'mall_voucher_discount',
                'loyalty_points_earned',
            ]);
        });
    }
};
