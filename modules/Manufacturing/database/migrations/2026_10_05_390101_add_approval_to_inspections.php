<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 39.2 Dispensasi inspeksi: tautan approval four-eyes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mfg_inspections', function (Blueprint $table) {
            $table->unsignedBigInteger('approval_id')->nullable()->after('findings');
        });
    }

    public function down(): void
    {
        Schema::table('mfg_inspections', function (Blueprint $table) {
            $table->dropColumn('approval_id');
        });
    }
};
