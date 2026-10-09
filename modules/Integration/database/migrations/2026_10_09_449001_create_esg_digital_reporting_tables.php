<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_digital_disclosures', function (Blueprint $table) {
            $table->id();
            $table->string('disclosure_tag')->unique(); // e.g. esg-ifrs:Scope1Emissions
            $table->string('topic');
            $table->decimal('reported_datapoint_value', 18, 4);
            $table->decimal('tagged_xbrl_value', 18, 4); // 449.1, 449.5 matching check
            $table->string('audit_evidence_ref'); // 449.1, 449.4 unsupported figure blocked
            $table->boolean('assurance_findings_unresolved')->default(false); // 449.2, 449.6
            $table->string('assurance_opinion')->default('UNQUALIFIED'); // UNQUALIFIED, QUALIFIED
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_digital_disclosures');
    }
};
