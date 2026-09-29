<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot: Mobil yang dimiliki user (My Garage)
        Schema::create('garages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->string('plate_number')->nullable();        // Plat nomor (opsional, untuk integrasi booking)
            $table->string('color')->nullable();               // Warna mobil user
            $table->integer('year_bought')->nullable();         // Tahun pembelian
            $table->string('nickname')->nullable();            // Nama panggilan user untuk mobilnya
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'car_id', 'plate_number']); // Satu user bisa punya >1 unit model sama, beda plat
            $table->index('user_id');
        });

        // Pivot: Wishlist mobil impian user
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->integer('priority')->default(0);           // Prioritas wishlist (0 = biasa, 1-5 = ranked)
            $table->text('notes')->nullable();                 // Catatan impian
            $table->timestamps();

            $table->unique(['user_id', 'car_id']);             // Satu user hanya bisa wishlist satu model sekali
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('garages');
    }
};
