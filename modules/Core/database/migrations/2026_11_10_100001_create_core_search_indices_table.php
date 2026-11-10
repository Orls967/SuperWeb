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
        if (! Schema::hasTable('core_search_indices')) {
            Schema::create('core_search_indices', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('module', 64)->index();
                $table->string('entity_type', 64)->index();
                $table->string('entity_id', 128)->index();
                $table->string('title');
                $table->text('content');
                $table->json('metadata')->nullable();
                $table->string('required_permission', 128)->nullable()->index();
                $table->string('scope_type', 64)->nullable()->index();
                $table->string('scope_id', 128)->nullable()->index();
                $table->unsignedInteger('document_length')->default(0);
                $table->timestamp('indexed_at')->nullable();
                $table->timestamps();

                $table->unique(['module', 'entity_type', 'entity_id'], 'core_search_module_entity_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_search_indices');
    }
};
