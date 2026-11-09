<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_audit_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('run_uuid')->unique();
            $table->string('source_a_status');
            $table->string('source_b_status');
            $table->integer('discrepancy_count')->default(0);
            $table->json('findings')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_audit_runs');
    }
};

