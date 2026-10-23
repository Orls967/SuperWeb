<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inv_stock_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('reference_movement_id')->nullable()->after('source_id');
            $table->foreign('reference_movement_id')->references('id')->on('inv_stock_movements')->nullOnDelete();
            $table->index('reference_movement_id');
        });
    }

    public function down(): void
    {
        Schema::table('inv_stock_movements', function (Blueprint $table) {
            $table->dropForeign(['reference_movement_id']);
            $table->dropColumn('reference_movement_id');
        });
    }
};
