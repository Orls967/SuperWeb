<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80);
            $table->unsignedInteger('version');
            $table->date('effective_from');
            $table->string('source', 200);
            $table->string('simulation_label', 8)->default('S1');
            $table->json('value');
            $table->boolean('is_fixture')->default(false);
            $table->timestamp('owner_confirmed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['code', 'version'], 'uq_syb_param_code_version');
            $table->index(['code', 'effective_from'], 'idx_syb_param_code_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_parameters');
    }
};
