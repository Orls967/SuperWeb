<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corporate_tax_provisions', function (Blueprint $table) {
            $table->id();
            $table->string('provision_code')->unique();
            $table->string('jurisdiction_country_code', 2);
            $table->string('tax_year_period'); // e.g. 2026-FY
            $table->decimal('pretax_accounting_income_usd', 15, 2);
            $table->decimal('effective_tax_rate_pct', 5, 2);
            $table->decimal('pillar_two_top_up_tax_usd', 15, 2)->default(0.00); // 274.1 & 274.4
            $table->decimal('total_tax_provision_usd', 15, 2);
            $table->boolean('is_tax_director_approved')->default(false); // 274.1 & 274.6
            $table->string('approved_by_director_id')->nullable();
            $table->boolean('accounting_close_blocked')->default(true); // 274.6 Block close until approved
            $table->timestamps();
        });

        Schema::create('corporate_tax_lineage_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_code')->unique();
            $table->string('provision_code')->index();
            $table->string('source_gl_journal_ref');
            $table->decimal('taxable_amount_usd', 15, 2);
            $table->string('evidence_pack_doc'); // 274.2
            $table->integer('rule_version')->default(1);
            $table->boolean('is_retroactive_recomputed')->default(false); // 274.5
            $table->timestamps();
        });

        Schema::create('corporate_tax_controversies', function (Blueprint $table) {
            $table->id();
            $table->string('case_code')->unique();
            $table->string('tax_authority_name'); // e.g. DJP, IRS, IRAS
            $table->string('dispute_issue_category'); // TRANSFER_PRICING, PERMANENT_ESTABLISHMENT
            $table->decimal('contested_amount_usd', 15, 2);
            $table->string('defense_pack_doc'); // 274.3 & 274.7
            $table->string('outcome_status')->default('DEFENSE_PREPARED'); // DEFENSE_PREPARED, WON, SETTLED_PARTIAL, LOST
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_tax_controversies');
        Schema::dropIfExists('corporate_tax_lineage_vouchers');
        Schema::dropIfExists('corporate_tax_provisions');
    }
};
