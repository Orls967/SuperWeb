<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sim_seeder_checkpoints')) {
            Schema::create('sim_seeder_checkpoints', function (Blueprint $table) {
                $table->id();
                $table->string('seeder_name');
                $table->string('stage');
                $table->unsignedBigInteger('last_processed_id')->default(0);
                $table->unsignedBigInteger('batch_number')->default(0);
                $table->json('metrics')->nullable();
                $table->boolean('completed')->default(false);
                $table->timestamps();

                $table->unique(['seeder_name', 'stage']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_seeder_checkpoints');
    }
};
