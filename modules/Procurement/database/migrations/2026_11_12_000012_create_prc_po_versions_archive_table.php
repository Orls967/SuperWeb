<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prc_po_versions_archive', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('archive_run_ref');
            $table->uuid('po_ref');
            $table->unsignedInteger('version');
            $table->string('change_summary', 500);
            $table->json('snapshot');
            $table->string('status', 16);
            $table->timestamp('original_created_at')->nullable();
            $table->timestamp('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prc_po_versions_archive');
    }
};

