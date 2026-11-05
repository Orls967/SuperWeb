<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psv_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->unique();
            $table->unsignedBigInteger('client_party_id');
            $table->string('contract_reference');
            $table->string('billing_type'); // 'time_and_materials' or 'fixed_fee'
            $table->unsignedBigInteger('total_contract_idr')->default(0);
            $table->unsignedBigInteger('estimated_cost_idr')->default(0);
            $table->string('status')->default('active'); // 'draft', 'active', 'completed', 'closed'
            $table->timestamps();
        });

        Schema::create('psv_project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('psv_projects')->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id');
            $table->string('role_name');
            $table->unsignedBigInteger('hourly_rate_idr')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['project_id', 'employee_id']);
        });

        Schema::create('psv_timesheets', function (Blueprint $table) {
            $table->id();
            $table->string('timesheet_code')->unique();
            $table->foreignId('project_id')->constrained('psv_projects')->cascadeOnDelete();
            $table->foreignId('project_member_id')->constrained('psv_project_members')->cascadeOnDelete();
            $table->date('work_date');
            $table->unsignedInteger('hours_worked');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('hourly_rate_idr')->default(0);
            $table->string('status')->default('submitted'); // 'submitted', 'approved', 'rejected'
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('is_billed')->default(false);
            $table->boolean('is_reversal')->default(false);
            $table->unsignedBigInteger('reversed_timesheet_id')->nullable();
            $table->timestamps();
        });

        Schema::create('psv_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_code')->unique();
            $table->foreignId('project_id')->constrained('psv_projects')->cascadeOnDelete();
            $table->unsignedBigInteger('client_party_id');
            $table->unsignedBigInteger('subtotal_idr');
            $table->string('idempotency_key')->unique();
            $table->string('status')->default('issued'); // 'issued', 'paid', 'cancelled'
            $table->timestamps();
        });

        Schema::create('psv_revenue_recognitions', function (Blueprint $table) {
            $table->id();
            $table->string('recognition_code')->unique();
            $table->foreignId('project_id')->constrained('psv_projects')->cascadeOnDelete();
            $table->unsignedBigInteger('cumulative_cost_idr');
            $table->unsignedBigInteger('total_estimated_cost_idr');
            $table->unsignedBigInteger('cumulative_recognized_idr');
            $table->unsignedBigInteger('period_recognized_idr');
            $table->string('certificate_sha256');
            $table->string('idempotency_key')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psv_revenue_recognitions');
        Schema::dropIfExists('psv_invoices');
        Schema::dropIfExists('psv_timesheets');
        Schema::dropIfExists('psv_project_members');
        Schema::dropIfExists('psv_projects');
    }
};
