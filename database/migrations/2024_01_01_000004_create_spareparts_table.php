<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spareparts', function (Blueprint $table) {
            $table->id();
            $table->string('name');               // Nama sparepart
            $table->string('code')->unique();     // Kode sparepart unik
            $table->integer('stock')->default(0); // Stok gudang
            $table->decimal('price', 12, 2);      // Harga per unit
            $table->string('unit')->default('pcs'); // Satuan (pcs, liter, set)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spareparts');
    }
};
