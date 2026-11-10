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
        if (! Schema::hasTable('core_search_query_logs')) {
            Schema::create('core_search_query_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('user_id')->nullable()->index();
                $table->string('user_role', 64)->default('guest')->index();
                $table->string('raw_query_hash', 64)->index();
                $table->string('redacted_query');
                $table->unsignedInteger('results_count')->default(0);
                $table->decimal('execution_time_ms', 8, 2)->default(0.00);
                $table->string('clicked_result_id')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_search_query_logs');
    }
};
