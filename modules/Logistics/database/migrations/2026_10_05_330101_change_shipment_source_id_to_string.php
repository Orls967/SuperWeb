<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `source_id` sekarang juga menampung UUID sumber non-logistik
 * (mis. `prc_purchase_orders.id` untuk pengiriman masuk Fase 33.7),
 * sehingga bertipe string.
 *
 * Nilai lama (id numerik store order) tetap terbaca: SQLite/MySQL
 * mengonversi literal numerik saat dibandingkan dengan kolom teks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lgx_shipments', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropColumn('source_id');
        });

        Schema::table('lgx_shipments', function (Blueprint $table) {
            $table->string('source_id')->nullable()->after('source_type');
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('lgx_shipments', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropColumn('source_id');
        });

        Schema::table('lgx_shipments', function (Blueprint $table) {
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->index(['source_type', 'source_id']);
        });
    }
};
