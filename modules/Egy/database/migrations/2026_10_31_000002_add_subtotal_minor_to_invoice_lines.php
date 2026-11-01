<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('egy_utility_invoice_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('subtotal_minor')->default(0)->after('rate_per_kwh_minor');
        });
    }

    public function down(): void
    {
        Schema::table('egy_utility_invoice_lines', function (Blueprint $table) {
            $table->dropColumn('subtotal_minor');
        });
    }
};
