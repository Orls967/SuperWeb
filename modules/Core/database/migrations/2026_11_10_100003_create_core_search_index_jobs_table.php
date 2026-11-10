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
        if (! Schema::hasTable('core_search_index_jobs')) {
            Schema::create('core_search_index_jobs', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('job_type', 64)->index();
                $table->string('module', 64)->nullable()->index();
                $table->string('status', 32)->default('pending')->index();
                $table->unsignedInteger('records_total')->default(0);
                $table->unsignedInteger('records_processed')->default(0);
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_search_index_jobs');
    }
};
