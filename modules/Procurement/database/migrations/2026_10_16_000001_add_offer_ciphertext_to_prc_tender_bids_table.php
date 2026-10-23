<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prc_tender_bids', function (Blueprint $table) {
            $table->text('offer_ciphertext')->nullable()->after('seal_hash')->comment('Isi penawaran terenkripsi sampai tender dibuka');
        });
    }

    public function down(): void
    {
        Schema::table('prc_tender_bids', function (Blueprint $table) {
            $table->dropColumn('offer_ciphertext');
        });
    }
};
