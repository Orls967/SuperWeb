<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('crypto_assets')->cascadeOnDelete();
            $table->string('condition'); // 'above' | 'below'
            $table->decimal('target_price_idr', 36, 18);
            $table->boolean('is_triggered')->default(false);
            $table->timestamp('triggered_at')->nullable();
            $table->timestamps();

            $table->index(['is_triggered', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_alerts');
    }
};
