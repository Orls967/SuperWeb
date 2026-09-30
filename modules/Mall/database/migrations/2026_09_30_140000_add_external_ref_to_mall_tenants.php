<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Referensi tenant ke sistem internal lain (mis. kode outlet Resto "DM-01"
     * atau kode cabang bengkel). Sengaja berupa string, BUKAN foreign key,
     * supaya modul Mall tidak bergantung pada tabel modul lain.
     */
    public function up(): void
    {
        Schema::table('mall_tenants', function (Blueprint $table) {
            $table->string('external_ref', 50)->nullable()->unique()->after('npwp');
        });
    }

    public function down(): void
    {
        Schema::table('mall_tenants', function (Blueprint $table) {
            $table->dropUnique(['external_ref']);
            $table->dropColumn('external_ref');
        });
    }
};
