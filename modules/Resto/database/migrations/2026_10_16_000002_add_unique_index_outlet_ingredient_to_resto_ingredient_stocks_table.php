<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resto_ingredient_stocks', function (Blueprint $table) {
            $table->unique(['outlet_id', 'ingredient_id'], 'resto_ing_stocks_outlet_ing_unique');
        });
    }

    public function down(): void
    {
        Schema::table('resto_ingredient_stocks', function (Blueprint $table) {
            $table->dropUnique('resto_ing_stocks_outlet_ing_unique');
        });
    }
};
