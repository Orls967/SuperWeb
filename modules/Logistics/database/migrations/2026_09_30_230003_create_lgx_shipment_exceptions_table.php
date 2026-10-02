<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_shipment_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('lgx_shipments')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('severity', 16);
            $table->string('status', 16)->default('open'); // open, resolved
            $table->string('dedupe_key', 120)->nullable()->unique(); // idempotensi deteksi otomatis
            $table->string('description', 500);
            $table->foreignId('location_id')->nullable()->constrained('lgx_locations')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_notes', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index(['shipment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_shipment_exceptions');
    }
};
