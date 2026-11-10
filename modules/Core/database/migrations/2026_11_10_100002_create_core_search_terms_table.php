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
        if (! Schema::hasTable('core_search_terms')) {
            Schema::create('core_search_terms', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('search_index_id')->index();
                $table->string('term', 128)->index();
                $table->string('term_hash', 64)->index();
                $table->unsignedInteger('frequency')->default(1);
                $table->unsignedInteger('field_weight')->default(1);
                $table->timestamps();

                $table->foreign('search_index_id')
                    ->references('id')
                    ->on('core_search_indices')
                    ->onDelete('cascade');

                $table->index(['term_hash', 'search_index_id'], 'core_term_lookup_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_search_terms');
    }
};
