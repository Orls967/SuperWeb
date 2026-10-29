<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('htl_deposits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id');
            $table->uuid('reservation_id');
            $table->foreignId('guest_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_idr');
            $table->unsignedBigInteger('applied_amount_idr')->default(0);
            $table->unsignedBigInteger('refunded_amount_idr')->default(0);
            $table->string('payment_method', 32)->default('CASH');
            $table->string('status', 32)->default('HELD');
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();

            $table->foreign('property_id')
                ->references('id')
                ->on('htl_properties')
                ->cascadeOnDelete();

            $table->foreign('reservation_id')
                ->references('id')
                ->on('htl_reservations')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('htl_deposits');
    }
};
