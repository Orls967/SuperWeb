<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serve_bookings', function (Blueprint $table) {
            $table->string('payment_status', 32)->default('unpaid')->after('grand_total');
            $table->timestamp('paid_at')->nullable()->after('payment_status');

            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('serve_bookings', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropColumn(['payment_status', 'paid_at']);
        });
    }
};
