<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agri_collection_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('collection_batch_id')->constrained('agri_collection_batches')->cascadeOnDelete();
            $table->unsignedBigInteger('grams_consumed');
            $table->string('idempotency_key', 128)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agri_collection_consumptions');
    }
};
