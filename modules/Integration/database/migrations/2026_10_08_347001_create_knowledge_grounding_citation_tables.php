<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_grounding_retrieval_queries', function (Blueprint $table) {
            $table->id();
            $table->string('query_code')->unique();
            $table->string('user_query');
            $table->boolean('found_in_grounded_corpus')->default(false);
            $table->text('generated_answer');
            $table->boolean('refused_due_to_empty_corpus')->default(false); // 347.5 Edge case
            $table->timestamps();
        });

        Schema::create('knowledge_retrieval_citation_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('verification_code')->unique();
            $table->string('query_code')->index();
            $table->string('cited_document_id');
            $table->string('document_version');
            $table->boolean('is_stale_version')->default(false); // 347.2 & 347.4
            $table->boolean('citation_supports_claim')->default(true); // 347.3 & 347.4
            $table->boolean('citation_rejected_or_rephrased')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_retrieval_citation_verifications');
        Schema::dropIfExists('knowledge_grounding_retrieval_queries');
    }
};
