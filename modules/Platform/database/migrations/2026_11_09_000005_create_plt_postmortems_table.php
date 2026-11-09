<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_postmortems', function (Blueprint $table): void {
            $table->id();
            $table->string('incident_ref')->unique();
            $table->string('severity')->default('sev2_major');
            $table->string('title');
            $table->text('root_cause');
            $table->json('action_items')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_postmortems');
    }
};

