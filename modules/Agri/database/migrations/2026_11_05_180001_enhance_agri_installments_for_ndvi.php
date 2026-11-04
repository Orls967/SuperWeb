<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agri_financing_installments')) {
            Schema::table('agri_financing_installments', function (Blueprint $table) {
                if (! Schema::hasColumn('agri_financing_installments', 'consecutive_failures')) {
                    $table->unsignedInteger('consecutive_failures')->default(0)->after('status');
                }
                if (! Schema::hasColumn('agri_financing_installments', 'farmer_party_id')) {
                    $table->unsignedBigInteger('farmer_party_id')->nullable()->after('plot_code');
                }
                if (! Schema::hasColumn('agri_financing_installments', 'evaluated_ndvi_numerator')) {
                    $table->integer('evaluated_ndvi_numerator')->nullable()->after('evaluated_ndvi');
                }
                if (! Schema::hasColumn('agri_financing_installments', 'evaluated_ndvi_denominator')) {
                    $table->integer('evaluated_ndvi_denominator')->nullable()->after('evaluated_ndvi_numerator');
                }
            });
        }
    }

    public function down(): void {}
};
