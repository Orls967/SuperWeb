<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Faktur konsinyasi tidak merujuk order (43.4) → order_id nullable.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('dist_invoices', 'order_id')) {
            Schema::table('dist_invoices', function (Blueprint $table) {
                $table->dropForeign(['order_id']);
            });
            Schema::table('dist_invoices', function (Blueprint $table) {
                $table->uuid('order_id')->nullable()->change();
                $table->foreign('order_id')->references('id')->on('dist_orders')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Struktur kolom sementara — tidak perlu dibalik.
    }
};
