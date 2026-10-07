<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sim_twin_states')) {
            Schema::create('sim_twin_states', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type')->index();
                $table->string('entity_id')->index();
                $table->json('state');
                $table->string('state_hash');
                $table->string('prev_hash')->nullable();
                $table->timestamp('valid_from');
                $table->boolean('is_sandbox')->default(false)->index();
                $table->timestamps();

                $table->index(['entity_type', 'entity_id', 'is_sandbox']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_twin_states');
    }
};
