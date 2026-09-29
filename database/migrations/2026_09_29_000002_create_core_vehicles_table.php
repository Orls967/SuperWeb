<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('core_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->nullable()->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('car_id')->nullable()->constrained('dex_cars')->nullOnDelete();
            $table->string('plate_number')->nullable()->unique();
            $table->string('vin')->nullable()->unique();
            $table->string('color')->nullable();
            $table->unsignedInteger('odometer_km')->default(0);
            $table->timestamp('acquired_at')->nullable();
            $table->nullableMorphs('acquired_via');
            $table->string('status')->default('active'); // active | sold | scrapped
            $table->timestamps();
            $table->softDeletes();
        });

        // Migrasi data: setiap baris pivot garage lama -> 1 baris core_vehicles
        if (Schema::hasTable('dex_garages')) {
            $garages = DB::table('dex_garages')->get();
            foreach ($garages as $garage) {
                DB::table('core_vehicles')->insert([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $garage->user_id,
                    'car_id' => $garage->car_id,
                    'plate_number' => $garage->plate_number,
                    'color' => $garage->color,
                    'odometer_km' => 0,
                    'acquired_at' => $garage->created_at ?? now(),
                    'acquired_via_type' => 'manual',
                    'acquired_via_id' => null,
                    'status' => 'active',
                    'created_at' => $garage->created_at ?? now(),
                    'updated_at' => $garage->updated_at ?? now(),
                ]);
            }

            // Pivot garage lama dihapus setelah data dipindah
            Schema::dropIfExists('dex_garages');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('dex_garages')) {
            Schema::create('dex_garages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('car_id')->constrained('dex_cars')->cascadeOnDelete();
                $table->string('plate_number')->nullable();
                $table->string('color')->nullable();
                $table->year('year_bought')->nullable();
                $table->string('nickname')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'car_id']);
            });

            if (Schema::hasTable('core_vehicles')) {
                $vehicles = DB::table('core_vehicles')->get();
                foreach ($vehicles as $vehicle) {
                    if ($vehicle->car_id) {
                        DB::table('dex_garages')->insert([
                            'user_id' => $vehicle->user_id,
                            'car_id' => $vehicle->car_id,
                            'plate_number' => $vehicle->plate_number,
                            'color' => $vehicle->color,
                            'created_at' => $vehicle->created_at,
                            'updated_at' => $vehicle->updated_at,
                        ]);
                    }
                }
            }
        }

        Schema::dropIfExists('core_vehicles');
    }
};
