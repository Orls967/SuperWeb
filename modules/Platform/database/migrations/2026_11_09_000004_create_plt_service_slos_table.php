<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_service_slos', function (Blueprint $table): void {
            $table->id();
            $table->string('service_name')->unique();
            $table->string('tier')->default('tier_2_high');
            $table->integer('target_sli_bps')->default(9990); // 99.90%
            $table->integer('error_budget_mins')->default(43); // menit per bulan
            $table->decimal('burn_rate_1h', 6, 2)->default(0.00);
            $table->decimal('burn_rate_6h', 6, 2)->default(0.00);
            $table->string('status')->default('healthy');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_service_slos');
    }
};
