<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 207.1: Regulatory Change Feed & Impact Analysis Tasks
        Schema::create('erm_regulatory_change_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('change_code')->unique();
            $table->string('jurisdiction');
            $table->string('affected_domain');
            $table->text('regulatory_summary');
            $table->string('assigned_team');
            $table->string('status')->default('OPEN'); // OPEN, IMPLEMENTED
            $table->timestamps();
        });

        // 207.2: Policy Lifecycle with Employee Acknowledgments
        Schema::create('erm_policy_lifecycles', function (Blueprint $table) {
            $table->id();
            $table->string('policy_code')->unique();
            $table->string('policy_title');
            $table->string('version')->default('1.0');
            $table->string('legal_approval_by')->nullable();
            $table->string('status')->default('DRAFT'); // DRAFT, PUBLISHED
            $table->timestamps();
        });

        Schema::create('erm_policy_acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->string('policy_code');
            $table->string('employee_id');
            $table->timestamp('acknowledged_at');
            $table->unique(['policy_code', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erm_policy_acknowledgments');
        Schema::dropIfExists('erm_policy_lifecycles');
        Schema::dropIfExists('erm_regulatory_change_tasks');
    }
};
