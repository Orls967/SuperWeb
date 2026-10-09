<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 32.4 — tautan tier harga ke kontrak kerangka (ctr_contracts, Fase 28/29).
        // Nullable; tanpa FK lintas modul (kolom kait saja, konsisten dengan praktik proyek).
        Schema::table('sup_price_tiers', function (Blueprint $table) {
            $table->uuid('contract_id')->nullable()->after('item_id')
                ->comment('Kontrak kerangka aktif yang mengikat harga ini');
            $table->index(['contract_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('sup_price_tiers', function (Blueprint $table) {
            $table->dropIndex(['contract_id', 'is_active']);
            $table->dropColumn('contract_id');
        });
    }
};
