<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ast_assets', 'salvage_value_idr')) {
            Schema::table('ast_assets', function (Blueprint $table) {
                $table->bigInteger('salvage_value_idr')->default(0)->after('accumulated_depreciation_idr');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ast_assets', 'salvage_value_idr')) {
            Schema::table('ast_assets', function (Blueprint $table) {
                $table->dropColumn('salvage_value_idr');
            });
        }
    }
};
