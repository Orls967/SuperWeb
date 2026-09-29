<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code')->unique();          // Kode booking unik (AUTO-XXXXXX)
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mechanic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            // Data kendaraan
            $table->string('plate_number');                    // Plat nomor kendaraan
            $table->string('vehicle_brand');                   // Merk kendaraan
            $table->string('vehicle_model')->nullable();       // Model kendaraan
            $table->integer('vehicle_year')->nullable();       // Tahun kendaraan

            // Detail perbaikan
            $table->text('complaint');                         // Keluhan pelanggan
            $table->text('mechanic_notes')->nullable();        // Catatan mekanik
            $table->date('booking_date');                      // Tanggal booking
            $table->time('booking_time')->nullable();          // Jam booking

            // Status flow: pending -> confirmed -> in_progress -> completed -> invoiced
            $table->enum('status', ['pending', 'confirmed', 'in_progress', 'completed', 'invoiced'])->default('pending');

            // Biaya
            $table->decimal('service_cost', 12, 2)->default(0);   // Biaya jasa
            $table->decimal('sparepart_cost', 12, 2)->default(0); // Total biaya sparepart
            $table->decimal('grand_total', 12, 2)->default(0);    // Grand total

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
