<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_cod_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->unique()->constrained('lgx_shipments')->cascadeOnDelete();
            $table->foreignId('shipper_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('lgx_drivers')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_idr');
            $table->unsignedBigInteger('fee_idr')->default(0);
            $table->unsignedBigInteger('net_amount_idr')->default(0);
            $table->string('status', 16)->default('collected'); // collected, deposited, settled
            $table->timestamp('collected_at');
            $table->foreignId('deposit_hub_id')->nullable()->constrained('lgx_locations')->nullOnDelete();
            $table->foreignId('deposited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deposited_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'deposited_at']);
            $table->index(['driver_id', 'status']);
            $table->index(['shipper_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_cod_collections');
    }
};
