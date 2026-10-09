<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_regulatory_obligations', function (Blueprint $table) {
            $table->id();
            $table->string('obligation_code')->unique();
            $table->string('jurisdiction');
            $table->string('title');
            $table->date('due_date');
            $table->string('status')->default('pending'); // pending, submitted, late_filed
            $table->string('approver')->nullable();
            $table->string('evidence_hash')->nullable();
            $table->boolean('is_late')->default(false);
            $table->text('root_cause_notes')->nullable(); // 404.5
            $table->decimal('penalty_amount', 15, 2)->default(0.00); // 404.5
            $table->timestamps();
        });

        Schema::create('gov_tax_provisions', function (Blueprint $table) {
            $table->id();
            $table->string('provision_code')->unique();
            $table->string('fiscal_year');
            $table->decimal('estimated_amount', 18, 2);
            $table->decimal('uncertain_tax_position_amount', 18, 2)->default(0.00);
            $table->boolean('approved_by_cfo')->default(false);
            $table->json('source_lineage')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_tax_provisions');
        Schema::dropIfExists('gov_regulatory_obligations');
    }
};
