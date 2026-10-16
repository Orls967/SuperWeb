<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crypto_price_ticks', function (Blueprint $table) {
            $table->bigInteger('price_idr')->change();
        });

        Schema::table('crypto_quotes', function (Blueprint $table) {
            $table->bigInteger('price_idr')->change();
            $table->bigInteger('gross_idr')->change();
            $table->bigInteger('fee_idr')->change();
        });

        Schema::table('crypto_trades', function (Blueprint $table) {
            $table->bigInteger('price_idr')->change();
            $table->bigInteger('gross_idr')->change();
            $table->bigInteger('fee_idr')->change();
        });

        Schema::table('crypto_alerts', function (Blueprint $table) {
            $table->bigInteger('target_price_idr')->change();
        });
    }

    public function down(): void
    {
        // IDR is permanently stored as bigInteger (KONSEP §A2.6).
    }
};
