<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 185.1: Canonical domain registry (30 business lines freeze)
        Schema::create('gov_domain_registry', function (Blueprint $table) {
            $table->id();
            $table->string('line_code')->unique(); // e.g. L01 to L30
            $table->string('line_name');
            $table->string('primary_module_code');
            $table->string('ledger_authority'); // Single source of truth authority
            $table->string('stock_authority')->nullable();
            $table->string('version')->default('1.0.0');
            $table->boolean('is_frozen')->default(true);
            $table->timestamps();
        });

        // 185.3: Event contract governance & schema compatibility
        Schema::create('gov_event_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('event_name')->unique();
            $table->string('owning_domain');
            $table->integer('schema_version')->default(1);
            $table->json('schema_definition');
            $table->boolean('is_breaking_change_allowed')->default(false); // Additive-only enforced
            $table->timestamps();
        });

        // 185.4: Cross-domain entity resolution & master data mapping
        Schema::create('gov_master_entities', function (Blueprint $table) {
            $table->id();
            $table->string('global_entity_uuid')->unique();
            $table->string('entity_type'); // PARTY, PRODUCT, ASSET, ACCOUNT
            $table->string('owning_domain');
            $table->string('canonical_identifier');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_master_entities');
        Schema::dropIfExists('gov_event_contracts');
        Schema::dropIfExists('gov_domain_registry');
    }
};
