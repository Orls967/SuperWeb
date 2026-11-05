<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('esg_carbon_issuances')) {
            Schema::create('esg_carbon_issuances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('forest_plot_ref')->index();
                $table->unsignedBigInteger('kg_co2e');
                $table->unsignedInteger('vintage_year');
                $table->string('idempotency_key')->unique();
                $table->string('status')->default('issued');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_carbon_issuances');
    }
};
