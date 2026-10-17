<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sim_scale_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('provisioner_key', 64)->index();
            $table->string('tier', 10);
            $table->integer('seed');
            $table->bigInteger('last_processed_step')->default(0);
            $table->bigInteger('total_inserted')->default(0);
            $table->string('status', 20)->default('in_progress');
            $table->timestamps();

            $table->unique(['provisioner_key', 'tier', 'seed'], 'sim_scale_ckp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_scale_checkpoints');
    }
};
