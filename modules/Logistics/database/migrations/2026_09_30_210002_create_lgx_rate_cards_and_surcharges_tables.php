<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_rate_cards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('origin_location_id')->nullable()->constrained('lgx_locations')->nullOnDelete();
            $table->foreignId('destination_location_id')->nullable()->constrained('lgx_locations')->nullOnDelete();
            $table->string('origin_zone', 64)->nullable()->index();
            $table->string('destination_zone', 64)->nullable()->index();
            $table->string('service_level', 32)->index();
            $table->string('mode', 32)->index();
            $table->unsignedBigInteger('min_charge_idr')->default(0);
            $table->date('valid_from')->index();
            $table->date('valid_to')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('lgx_rate_brackets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rate_card_id')->constrained('lgx_rate_cards')->cascadeOnDelete();
            $table->decimal('min_weight_kg', 10, 2)->default(0);
            $table->decimal('max_weight_kg', 10, 2)->nullable();
            $table->unsignedBigInteger('rate_per_kg_idr')->default(0);
            $table->unsignedBigInteger('flat_rate_idr')->default(0);
            $table->boolean('is_flat')->default(false);
            $table->timestamps();
        });

        Schema::create('lgx_surcharges', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('type', 16)->default('percentage'); // 'percentage' or 'flat'
            $table->decimal('rate', 10, 4)->default(0);        // e.g. 0.0500 for 5%
            $table->unsignedBigInteger('flat_amount_idr')->default(0);
            $table->unsignedBigInteger('min_amount_idr')->default(0);
            $table->string('applies_to_service_level', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_surcharges');
        Schema::dropIfExists('lgx_rate_brackets');
        Schema::dropIfExists('lgx_rate_cards');
    }
};
