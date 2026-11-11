<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_boundary_violations', function (Blueprint $table): void {
            $table->id();
            $table->string('rule_id', 16);
            $table->string('category', 32);
            $table->string('file_path', 255);
            $table->unsignedInteger('line_number');
            $table->string('signature', 255);
            $table->string('client_module', 64);
            $table->string('owner_module', 64);
            $table->string('status', 32)->default('detected');
            $table->string('contract_name', 255)->nullable();
            $table->string('adr_anchor', 255)->nullable();
            $table->text('waiver_reason')->nullable();
            $table->string('waived_by', 64)->nullable();
            $table->dateTime('waived_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['rule_id', 'file_path', 'signature'], 'uq_boundary_violation');
            $table->index('status');
            $table->index('client_module');
            $table->index('owner_module');
        });

        Schema::create('core_boundary_violation_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('violation_id')->constrained('core_boundary_violations')->cascadeOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('actor', 64);
            $table->text('reason');
            $table->string('commit_sha', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index('violation_id');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_boundary_violation_transitions');
        Schema::dropIfExists('core_boundary_violations');
    }
};
