<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_sync_conflict_ledgers')) {
            Schema::create('core_sync_conflict_ledgers', function (Blueprint $table): void {
                $table->id();
                $table->string('device_uuid', 64);
                $table->string('entity_type', 128);
                $table->string('entity_id', 64);
                $table->integer('server_version')->default(1);
                $table->integer('client_version')->default(1);
                $table->string('resolution_strategy', 64);
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('audit_hash', 64);
                $table->timestamps();

                $table->index(['device_uuid', 'entity_type', 'entity_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_sync_conflict_ledgers');
    }
};
