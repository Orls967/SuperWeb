<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_synth_datasets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('domain');
            $table->integer('record_count')->default(0);
            $table->string('checksum')->unique();
            $table->json('schema_definition')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_synth_datasets');
    }
};

