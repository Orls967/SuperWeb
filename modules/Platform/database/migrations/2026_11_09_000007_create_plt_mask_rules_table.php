<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_mask_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('domain');
            $table->string('column_pattern');
            $table->string('mask_strategy'); // email, phone, nik, hash, redact
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_mask_rules');
    }
};

