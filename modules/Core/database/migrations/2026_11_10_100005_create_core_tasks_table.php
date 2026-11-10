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
        if (! Schema::hasTable('core_tasks')) {
            Schema::create('core_tasks', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('module', 64)->index();
                $table->string('source_entity_type', 64)->index();
                $table->string('source_entity_id', 128)->index();
                $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('assignee_role', 64)->nullable()->index();
                $table->string('status', 32)->default('pending')->index();
                $table->string('priority', 32)->default('normal')->index();
                $table->timestamp('due_at')->nullable()->index();
                $table->string('action_url')->nullable();
                $table->json('payload')->nullable();
                $table->unsignedInteger('escalation_level')->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_tasks');
    }
};
