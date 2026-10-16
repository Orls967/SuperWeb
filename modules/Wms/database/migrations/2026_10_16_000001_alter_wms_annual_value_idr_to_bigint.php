<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wms_slottings', function (Blueprint $table) {
            $table->bigInteger('annual_value_idr')->default(0)->change();
        });
    }

    public function down(): void
    {
        // IDR is permanently stored as bigInteger (KONSEP §A2.6).
    }
};
