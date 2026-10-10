<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_enterprise_financial_integrity_statements', function (Blueprint $table) {
            $table->id();
            $table->string('statement_code')->unique('int_ent_fin_integrity_stmt_code_uniq');
            $table->string('period'); // e.g. 2026-FY
            $table->integer('total_audits_evaluated');
            $table->integer('exception_count')->default(0); // 480.1 zero exceptions required
            $table->string('signatory_role')->nullable(); // 480.1, 480.5 (signing blocked if exception > 0)
            $table->string('auditor_opinion')->nullable(); // unqualified, qualified, adverse (480.3)
            $table->boolean('auditor_sample_verified')->default(false); // 480.6 risk
            $table->string('status')->default('draft'); // draft, signed, certified_unqualified
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_enterprise_financial_integrity_statements');
    }
};
