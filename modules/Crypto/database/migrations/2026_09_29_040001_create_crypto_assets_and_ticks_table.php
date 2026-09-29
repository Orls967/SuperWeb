<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_assets', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 10)->unique();
            $table->string('name');
            $table->unsignedInteger('decimals')->default(8);
            $table->double('volatility')->default(0.02);
            $table->boolean('is_active')->default(true);
            $table->string('icon')->nullable();
            $table->timestamps();
        });

        Schema::create('crypto_price_ticks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('crypto_assets')->cascadeOnDelete();
            $table->decimal('price_idr', 36, 18);
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            $table->index(['asset_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_price_ticks');
        Schema::dropIfExists('crypto_assets');
    }
};
