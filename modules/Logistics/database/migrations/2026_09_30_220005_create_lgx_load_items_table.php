<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_load_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('load_id')->constrained('lgx_loads')->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained('lgx_shipments')->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('lgx_packages')->nullOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->decimal('weight_kg', 12, 3);
            $table->unsignedInteger('volume_dm3');
            $table->string('dg_class', 16)->nullable();
            $table->boolean('is_reefer')->default(false);
            $table->dateTime('loaded_at')->nullable();
            $table->timestamps();

            $table->index(['load_id', 'sequence']);
            $table->index('shipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_load_items');
    }
};
