<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel pivot: sparepart yang digunakan dalam sebuah booking
        Schema::create('booking_sparepart', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sparepart_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);                 // Jumlah sparepart dipakai
            $table->decimal('unit_price', 12, 2);                    // Harga saat transaksi
            $table->decimal('subtotal', 12, 2);                      // quantity * unit_price
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_sparepart');
    }
};
