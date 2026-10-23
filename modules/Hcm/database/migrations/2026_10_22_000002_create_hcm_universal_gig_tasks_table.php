<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hcm_universal_gig_tasks')) {
            Schema::create('hcm_universal_gig_tasks', function (Blueprint $table): void {
                $table->id();
                $table->string('task_code', 32)->unique();
                $table->string('source_domain', 32);
                $table->string('title', 128);
                $table->text('description');
                $table->string('required_k3_certificate', 64)->nullable();
                $table->unsignedInteger('hourly_rate_idr');
                $table->unsignedSmallInteger('estimated_hours');
                $table->unsignedInteger('total_compensation_idr');
                $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('supervisor_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('status', 24)->default('OPEN');
                $table->timestamps();

                $table->index(['source_domain', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_universal_gig_tasks');
    }
};
