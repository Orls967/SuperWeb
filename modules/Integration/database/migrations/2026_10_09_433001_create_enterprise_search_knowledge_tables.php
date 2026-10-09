<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_enterprise_documents', function (Blueprint $table) {
            $table->id();
            $table->string('doc_code')->unique();
            $table->string('title');
            $table->string('corpus'); // engineering, legal, hr, operations (433.1)
            $table->string('version')->default('1.0');
            $table->json('acl_allowed_roles'); // 433.1, 433.4, 433.6 ACL mirror
            $table->boolean('is_superseded')->default(false); // 433.3, 433.5
            $table->string('superseded_by_code')->nullable();
            $table->timestamps();
        });

        Schema::create('plt_search_queries_log', function (Blueprint $table) {
            $table->id();
            $table->string('query_text');
            $table->string('corpus');
            $table->integer('results_count');
            $table->decimal('relevance_score', 5, 2); // 433.2
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_search_queries_log');
        Schema::dropIfExists('plt_enterprise_documents');
    }
};
