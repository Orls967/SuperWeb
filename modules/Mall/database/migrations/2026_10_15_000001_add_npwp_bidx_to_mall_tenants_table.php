<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mall_tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('mall_tenants', 'npwp_bidx')) {
                $table->char('npwp_bidx', 64)->nullable()->index()->after('npwp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mall_tenants', function (Blueprint $table) {
            if (Schema::hasColumn('mall_tenants', 'npwp_bidx')) {
                $table->dropColumn('npwp_bidx');
            }
        });
    }
};
