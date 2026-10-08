<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 194.1: Unified global search index across 30 lines with tenancy & scope security
        Schema::create('src_global_search_indices', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type'); // PRODUCT, CONTRACT, RESI, ROOM, INVOICE
            $table->string('entity_id');
            $table->string('domain_code'); // L01 - L30
            $table->string('tenant_scope'); // TENANT_SPECIFIC or PUBLIC
            $table->string('allowed_role'); // ALL, FINANCE, LEGAL, STAFF
            $table->text('searchable_text');
            $table->text('sanitized_preview'); // Guaranteed no unmasked PII
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('src_global_search_indices');
    }
};
