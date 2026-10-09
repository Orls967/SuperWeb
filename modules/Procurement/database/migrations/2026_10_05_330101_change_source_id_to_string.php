<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom source_id menyimpan UUID prc_requisitions/prc_purchase_orders,
 * sehingga harus bertipe string (bukan numerik).
 *
 * Tabel masih kosong pada saat migrasi ini ditulis, jadi drop+add aman
 * dan tidak membutuhkan doctrine/dbal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prc_budget_encumbrances', function (Blueprint $table) {
            $table->dropUnique(['source_type', 'source_id']);
            $table->dropColumn('source_id');
        });

        Schema::table('prc_budget_encumbrances', function (Blueprint $table) {
            $table->string('source_id')->after('source_type');
            $table->unique(['source_type', 'source_id'], 'prc_encumbrance_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('prc_budget_encumbrances', function (Blueprint $table) {
            $table->dropUnique('prc_encumbrance_source_unique');
            $table->dropColumn('source_id');
        });

        Schema::table('prc_budget_encumbrances', function (Blueprint $table) {
            $table->unsignedBigInteger('source_id')->after('source_type');
            $table->unique(['source_type', 'source_id']);
        });
    }
};
