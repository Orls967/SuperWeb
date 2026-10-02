<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lgx_quotes', function (Blueprint $table) {
            $table->unsignedBigInteger('declared_value_idr')->default(0)->after('mode');
            $table->boolean('insured')->default(false)->after('declared_value_idr');
            $table->unsignedBigInteger('cod_amount_idr')->default(0)->after('insured');
        });

        Schema::table('lgx_shipments', function (Blueprint $table) {
            $table->timestamp('revenue_recognized_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('lgx_shipments', function (Blueprint $table) {
            $table->dropColumn('revenue_recognized_at');
        });

        Schema::table('lgx_quotes', function (Blueprint $table) {
            $table->dropColumn(['declared_value_idr', 'insured', 'cod_amount_idr']);
        });
    }
};
