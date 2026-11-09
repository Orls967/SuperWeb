<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_sandbox_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sandbox_id')->constrained('plt_sandboxes')->cascadeOnDelete();
            $table->string('from_status');
            $table->string('to_status');
            $table->string('reason')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sandbox_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_sandbox_transitions');
    }
};
