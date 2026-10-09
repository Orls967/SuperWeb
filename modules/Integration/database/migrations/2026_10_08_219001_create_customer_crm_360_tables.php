<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 219.1 & 219.6: Customer golden records & reversible merges
        Schema::create('crm_customer_golden_records', function (Blueprint $table) {
            $table->id();
            $table->string('golden_id')->unique();
            $table->string('hashed_identifier')->unique(); // e.g. SHA-256 of NIK or Email
            $table->string('primary_name');
            $table->json('merged_child_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 219.4: Consent & preference center gating cross-module data sharing
        Schema::create('crm_customer_consents', function (Blueprint $table) {
            $table->id();
            $table->string('consent_code')->unique();
            $table->string('golden_id');
            $table->string('purpose'); // MARKETING, CROSS_LINE_DATA_SHARE, ANALYTICS
            $table->boolean('is_granted')->default(false);
            $table->timestamp('consented_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customer_consents');
        Schema::dropIfExists('crm_customer_golden_records');
    }
};
