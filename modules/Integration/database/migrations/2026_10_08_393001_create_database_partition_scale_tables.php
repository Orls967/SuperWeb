<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_stress_partition_switches', function (Blueprint $table) {
            $table->id();
            $table->string('partition_name')->unique();
            $table->integer('pre_switch_row_count');
            $table->integer('post_switch_row_count');
            $table->boolean('row_counts_preserved')->default(true); // 393.4
            $table->boolean('switch_rolled_back')->default(false); // 393.5 Edge case
            $table->string('switch_status'); // COMMITTED, ROLLED_BACK
            $table->timestamps();
        });

        Schema::create('global_stress_archive_legal_holds', function (Blueprint $table) {
            $table->id();
            $table->string('archive_batch_code')->unique();
            $table->boolean('is_legal_hold_active')->default(false);
            $table->boolean('archive_permitted')->default(true); // 393.6 Risk
            $table->string('archive_checksum');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_stress_archive_legal_holds');
        Schema::dropIfExists('global_stress_partition_switches');
    }
};
