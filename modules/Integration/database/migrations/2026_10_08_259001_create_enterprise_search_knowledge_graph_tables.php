<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kg_graph_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('node_uid')->unique();
            $table->string('node_type'); // CUSTOMER, CONTRACT, ASSET, PROJECT, RISK, PARTY
            $table->string('label');
            $table->integer('security_clearance_level')->default(1); // 1=PUBLIC, 2=INTERNAL, 3=CONFIDENTIAL
            $table->timestamps();
        });

        Schema::create('kg_graph_edges', function (Blueprint $table) {
            $table->id();
            $table->string('from_node_uid')->index();
            $table->string('to_node_uid')->index();
            $table->string('relation_type'); // OWNS, BINDS_TO, EXPOSED_TO, OPERATES
            $table->timestamps();
        });

        Schema::create('kg_semantic_entities', function (Blueprint $table) {
            $table->id();
            $table->string('entity_canonical_id')->index();
            $table->string('alias_text');
            $table->decimal('confidence_score', 4, 3);
            $table->boolean('is_ambiguous_merge_recommended')->default(false); // 259.6
            $table->timestamps();
        });

        Schema::create('kg_knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('article_code')->unique();
            $table->string('title');
            $table->text('content_body');
            $table->integer('version')->default(1);
            $table->boolean('is_current')->default(true); // 259.7
            $table->boolean('is_stale_expired')->default(false); // 259.3, 259.7
            $table->date('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kg_knowledge_articles');
        Schema::dropIfExists('kg_semantic_entities');
        Schema::dropIfExists('kg_graph_edges');
        Schema::dropIfExists('kg_graph_nodes');
    }
};
