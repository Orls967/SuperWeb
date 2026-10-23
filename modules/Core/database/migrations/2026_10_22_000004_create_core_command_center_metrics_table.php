<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_command_center_metrics')) {
            Schema::create('core_command_center_metrics', function (Blueprint $table): void {
                $table->id();
                $table->string('metric_key', 64)->unique();
                $table->string('domain_code', 32);
                $table->bigInteger('metric_value_minor');
                $table->string('unit', 16);
                $table->string('status', 24)->default('HEALTHY');
                $table->timestamps();

                $table->index(['domain_code', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_command_center_metrics');
    }
};
