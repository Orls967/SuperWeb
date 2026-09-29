<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serve_estimates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('booking_id')->constrained('serve_bookings')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Rincian jasa + sparepart: [{type, ref_id, name, qty, unit_price, subtotal}]
            $table->json('items');
            $table->unsignedBigInteger('service_total')->default(0);
            $table->unsignedBigInteger('parts_total')->default(0);
            $table->unsignedBigInteger('total')->default(0);

            // Nilai yang dipakai saat posting ledger berikutnya (capture / extra charge)
            $table->unsignedBigInteger('final_service_total')->nullable();
            $table->unsignedBigInteger('final_parts_total')->nullable();

            $table->string('status', 32)->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Tagihan selisih bila biaya aktual melebihi estimasi yang ditahan
            $table->unsignedBigInteger('extra_amount')->nullable();
            $table->foreignId('extra_charge_intent_id')->nullable()->constrained('pay_payment_intents')->nullOnDelete();

            $table->foreignId('payment_intent_id')->nullable()->constrained('pay_payment_intents')->nullOnDelete();
            $table->foreignId('backorder_order_id')->nullable()->constrained('store_orders')->nullOnDelete();

            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serve_estimates');
    }
};
