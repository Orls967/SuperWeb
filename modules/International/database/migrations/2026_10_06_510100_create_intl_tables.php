<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 51.1 Master Entitas Mitra Asing
        Schema::create('intl_foreign_entities', function (Blueprint $table) {
            $table->id();
            $table->string('entity_code', 32)->unique();
            $table->string('legal_name');
            $table->string('jurisdiction_country', 3); // ISO-3 or ISO-2
            $table->string('registration_number');
            $table->string('functional_currency', 3)->default('USD');
            $table->string('arbitration_jurisdiction')->default('SIAC'); // SIAC, ICC, BANI
            $table->boolean('has_apostille')->default(false);
            $table->boolean('aml_screened')->default(false);
            $table->string('tax_residence_country', 3)->default('SG');
            $table->string('status', 20)->default('active'); // active, suspended
            $table->timestamps();
        });

        // 51.2 Joint Venture Management (Equity JV vs Contractual JV)
        Schema::create('intl_joint_ventures', function (Blueprint $table) {
            $table->id();
            $table->string('jv_code', 32)->unique();
            $table->string('name');
            $table->foreignId('foreign_entity_id')->constrained('intl_foreign_entities')->cascadeOnDelete();
            $table->string('jv_type', 20); // equity, contractual
            $table->decimal('local_share_percent', 5, 2);
            $table->decimal('foreign_share_percent', 5, 2);
            $table->bigInteger('total_committed_capital_idr')->default(0);
            $table->bigInteger('paid_in_capital_idr')->default(0);
            $table->boolean('minority_veto_rights')->default(true);
            $table->string('status', 20)->default('active'); // active, terminated
            $table->timestamps();
        });

        // 51.3 Lisensi HKI & Royalti Internasional
        Schema::create('intl_technology_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_code', 32)->unique();
            $table->string('title');
            $table->foreignId('foreign_entity_id')->constrained('intl_foreign_entities')->cascadeOnDelete();
            $table->string('license_type', 20); // brand, patent, software, franchise
            $table->string('territory')->default('INDONESIA');
            $table->boolean('is_exclusive')->default(false);
            $table->decimal('royalty_rate_percent', 5, 2)->default(5.00);
            $table->bigInteger('minimum_annual_guarantee_idr')->default(0);
            $table->date('start_date');
            $table->date('expiry_date');
            $table->string('status', 20)->default('active'); // active, expired, terminated
            $table->timestamps();
        });

        // 51.4 OEM/ODM Contract Manufacturing
        Schema::create('intl_oem_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number', 32)->unique();
            $table->foreignId('foreign_entity_id')->constrained('intl_foreign_entities')->cascadeOnDelete();
            $table->string('type', 10); // OEM, ODM
            $table->string('product_design_name');
            $table->bigInteger('tolling_fee_per_unit_idr')->default(0);
            $table->boolean('consignment_materials_tracked')->default(true);
            $table->boolean('nda_signed')->default(true);
            $table->string('qa_standard')->default('ISO9001');
            $table->string('status', 20)->default('active'); // active, completed
            $table->timestamps();
        });

        // 51.5 Alih Teknologi & Milestone Delivery
        Schema::create('intl_tech_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_code', 32)->unique();
            $table->string('package_title');
            $table->foreignId('foreign_entity_id')->constrained('intl_foreign_entities')->cascadeOnDelete();
            $table->string('current_milestone')->default('BLUEPRINT_HANDOVER');
            $table->bigInteger('total_value_idr')->default(0);
            $table->bigInteger('accepted_value_idr')->default(0);
            $table->boolean('derivative_ip_co_owned')->default(true);
            $table->boolean('signoff_completed')->default(false);
            $table->string('status', 20)->default('in_progress'); // in_progress, completed
            $table->timestamps();
        });

        // 51.7 Perjanjian Penghindaran Pajak Berganda (P3B / Tax Treaty & Withholding Tax)
        Schema::create('intl_tax_treaties', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 3)->unique(); // SG, JP, US, CN, DE
            $table->string('country_name');
            $table->decimal('standard_wht_rate', 5, 2)->default(20.00); // Domestic PPh 26
            $table->decimal('treaty_royalty_rate', 5, 2)->default(10.00);
            $table->decimal('treaty_interest_rate', 5, 2)->default(10.00);
            $table->decimal('treaty_dividend_rate', 5, 2)->default(10.00);
            $table->decimal('treaty_services_rate', 5, 2)->default(10.00);
            $table->boolean('dgt_form_required')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intl_tax_treaties');
        Schema::dropIfExists('intl_tech_transfers');
        Schema::dropIfExists('intl_oem_contracts');
        Schema::dropIfExists('intl_technology_licenses');
        Schema::dropIfExists('intl_joint_ventures');
        Schema::dropIfExists('intl_foreign_entities');
    }
};
