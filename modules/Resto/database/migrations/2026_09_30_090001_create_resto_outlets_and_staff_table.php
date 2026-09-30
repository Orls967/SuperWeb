<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resto_outlets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type', 30)->default('outlet'); // outlet, central_kitchen
            $table->string('address');
            $table->string('city', 50)->default('Banjarmasin');
            $table->string('phone', 30)->nullable();
            $table->string('mall_unit_ref')->nullable(); // string ref, no cross-module FK
            $table->integer('seats')->default(0);
            $table->time('opens_at')->default('08:00:00');
            $table->time('closes_at')->default('22:00:00');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('resto_staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->string('role', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'outlet_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resto_staff_assignments');
        Schema::dropIfExists('resto_outlets');
    }
};
