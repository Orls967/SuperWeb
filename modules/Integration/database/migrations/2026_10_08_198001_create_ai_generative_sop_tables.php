<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 198.1 & 198.4: Knowledge Assistant answers strictly grounded with citations and query-verified numbers
        Schema::create('ai_assistant_responses', function (Blueprint $table) {
            $table->id();
            $table->string('response_code')->unique();
            $table->text('user_question');
            $table->text('generated_answer');
            $table->string('source_citation_doc'); // Mandatory source document citation
            $table->boolean('has_system_query_grounding')->default(true);
            $table->timestamps();
        });

        // 198.2: SOP execution checklists with proof of steps
        Schema::create('ai_sop_checklists', function (Blueprint $table) {
            $table->id();
            $table->string('sop_code')->unique();
            $table->string('sop_title');
            $table->integer('total_steps');
            $table->integer('completed_steps')->default(0);
            $table->json('proof_records'); // Hashes/signatures of completed steps
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });

        // 198.3: Controlled generative drafts requiring human approval and hash verification
        Schema::create('ai_generative_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('draft_code')->unique();
            $table->string('doc_type'); // CONTRACT, INCIDENT_REPORT, MINUTES
            $table->text('draft_content');
            $table->string('content_sha256');
            $table->string('status')->default('DRAFT'); // DRAFT, PUBLISHED
            $table->string('approved_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generative_drafts');
        Schema::dropIfExists('ai_sop_checklists');
        Schema::dropIfExists('ai_assistant_responses');
    }
};
