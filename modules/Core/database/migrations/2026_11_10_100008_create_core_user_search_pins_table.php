<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('core_user_search_pins')) {
            Schema::create('core_user_search_pins', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('user_id')->index();
                $table->string('title');
                $table->string('url');
                $table->string('category', 64)->default('Shortcut');
                $table->string('badge', 32)->nullable();
                $table->string('badge_color', 64)->nullable();
                $table->unsignedInteger('order')->default(0);
                $table->timestamps();

                $table->index(['user_id', 'order'], 'core_pins_user_order_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_user_search_pins');
    }
};
