<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('model');                                // Nama model (Civic, Supra, Atto 3, dll)
            $table->string('slug')->unique();                      // URL-friendly slug
            $table->integer('year_start');                          // Tahun produksi awal
            $table->integer('year_end')->nullable();                // Tahun produksi akhir (null = masih produksi)
            $table->string('body_type')->nullable();                // Sedan, SUV, Hatchback, Coupe, MPV, Pickup, etc.
            $table->enum('fuel_type', ['gasoline', 'diesel', 'hybrid', 'electric', 'hydrogen'])->default('gasoline');
            $table->string('engine')->nullable();                   // Detail mesin (2.0L Turbo, Single Motor, etc.)
            $table->integer('horsepower')->nullable();              // Tenaga (HP)
            $table->integer('torque_nm')->nullable();               // Torsi (Nm)
            $table->string('transmission')->nullable();             // AT, MT, CVT, DCT, Single-Speed
            $table->string('drivetrain')->nullable();               // FWD, RWD, AWD, 4WD
            $table->integer('top_speed_kmh')->nullable();           // Top speed km/h
            $table->decimal('zero_to_100', 4, 1)->nullable();      // 0-100 km/h (detik)
            $table->integer('range_km')->nullable();                // Jarak tempuh EV (km)
            $table->integer('battery_kwh')->nullable();             // Kapasitas baterai EV (kWh)
            $table->decimal('price_idr', 15, 2)->nullable();        // Harga estimasi IDR
            $table->string('image_url')->nullable();                // URL gambar mobil
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['brand_id', 'year_start']);
            $table->index('body_type');
            $table->index('fuel_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
