<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vnd_items')) {
            Schema::create('vnd_items', function (Blueprint $table) {
                $table->id();
                $table->string('item_code')->unique();
                $table->string('name');
                $table->string('category')->default('beverage');
                $table->unsignedBigInteger('base_price_idr');
                $table->unsignedBigInteger('cost_price_idr');
                $table->unsignedInteger('shelf_life_hours')->default(72);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('vnd_vending_units')) {
            Schema::table('vnd_vending_units', function (Blueprint $table) {
                if (! Schema::hasColumn('vnd_vending_units', 'latitude')) {
                    $table->bigInteger('latitude')->default(0);
                }
                if (! Schema::hasColumn('vnd_vending_units', 'longitude')) {
                    $table->bigInteger('longitude')->default(0);
                }
            });
        }

        if (Schema::hasTable('vnd_vending_transactions')) {
            Schema::table('vnd_vending_transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('vnd_vending_transactions', 'cogs_idr')) {
                    $table->bigInteger('cogs_idr')->default(0);
                }
                if (! Schema::hasColumn('vnd_vending_transactions', 'idempotency_key')) {
                    $table->string('idempotency_key')->nullable()->unique();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vnd_items');
    }
};
