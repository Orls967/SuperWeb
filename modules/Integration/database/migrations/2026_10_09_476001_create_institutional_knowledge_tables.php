<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_institutional_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('decision_code')->unique();
            $table->string('decision_title');
            $table->text('context_and_alternatives');
            $table->string('outcome');
            $table->date('review_date');
            $table->string('owner');
            $table->timestamps();
        });

        Schema::create('int_institutional_knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('article_code')->unique();
            $table->string('knowledge_domain'); // pir, lessons_learned, regulatory_interpretation (476.2)
            $table->string('primary_expert');
            $table->date('last_reviewed_at');
            $table->integer('view_count')->default(0); // 476.3, 476.6 usage metrics
            $table->boolean('rotation_handover_certified')->default(false); // 476.5 edge case
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_institutional_knowledge_articles');
        Schema::dropIfExists('int_institutional_decisions');
    }
};
