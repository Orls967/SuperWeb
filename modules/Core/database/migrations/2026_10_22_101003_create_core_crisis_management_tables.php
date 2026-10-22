<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_crisis_incidents')) {
            Schema::create('core_crisis_incidents', function (Blueprint $table): void {
                $table->id();
                $table->string('incident_code', 64)->unique();
                $table->string('severity_level', 32);
                $table->string('title', 255);
                $table->foreignId('initiator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->integer('affected_branches_count')->default(0);
                $table->integer('total_units_quarantined')->default(0);
                $table->string('emergency_state', 48)->default('ACTIVE_QUARANTINE_LOCKED');
                $table->timestamp('activated_at')->nullable();
                $table->timestamps();

                $table->index(['severity_level', 'emergency_state']);
            });
        }

        if (! Schema::hasTable('core_crisis_affected_items')) {
            Schema::create('core_crisis_affected_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('incident_id')->constrained('core_crisis_incidents')->cascadeOnDelete();
                $table->string('sku', 64);
                $table->string('lot_number', 64);
                $table->integer('quarantined_quantity')->default(0);
                $table->string('storage_zone', 128);
                $table->boolean('is_sale_blocked')->default(true);
                $table->string('quarantine_token', 64);
                $table->timestamps();

                $table->index(['incident_id', 'sku', 'lot_number']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_crisis_affected_items');
        Schema::dropIfExists('core_crisis_incidents');
    }
};
