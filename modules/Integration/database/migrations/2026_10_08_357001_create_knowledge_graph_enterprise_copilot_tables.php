<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_graph_authoritative_queries', function (Blueprint $table) {
            $table->id();
            $table->string('query_code')->unique();
            $table->string('entity_code'); // ASSET, FINANCIAL_LEDGER, STOCK
            $table->boolean('requires_authoritative_live_value')->default(false);
            $table->boolean('is_value_stale')->default(false);
            $table->boolean('presented_stale_as_current')->default(false); // 357.4 & 357.5 Edge case
            $table->boolean('query_rejected_due_to_staleness')->default(false);
            $table->timestamps();
        });

        Schema::create('knowledge_graph_provenance_records', function (Blueprint $table) {
            $table->id();
            $table->string('provenance_code')->unique();
            $table->string('query_code')->index();
            $table->string('source_system');
            $table->boolean('provenance_complete')->default(true); // 357.4 & 357.6 Risk
            $table->boolean('answer_served')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_graph_provenance_records');
        Schema::dropIfExists('knowledge_graph_authoritative_queries');
    }
};
