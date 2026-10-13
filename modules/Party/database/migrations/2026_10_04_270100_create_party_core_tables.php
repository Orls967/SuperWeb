<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legal Entities (parent companies, subsidiaries)
        Schema::create('pty_legal_entities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('entity_type')->default('company'); // company, subsidiary, branch
            $table->uuid('parent_id')->nullable(); // self-referential for group structure
            $table->string('npwp', 30)->nullable()->comment('Tax ID (partially masked in app)');
            $table->string('nib', 20)->nullable()->comment('Business Registration Number');
            $table->string('functional_currency', 3)->default('IDR');
            $table->string('fiscal_year_start', 5)->default('01-01')->comment('MM-DD');
            $table->string('ledger_prefix')->nullable()->comment('prefix for entity-scoped account mapping');
            $table->json('account_map')->nullable()->comment('entity chart of accounts mapping to shared ledger');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('pty_legal_entities')->nullOnDelete();
        });

        // Core Party table (people or companies)
        Schema::create('pty_parties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('legal_entity_id')->nullable();
            $table->string('type')->default('company'); // person, company
            $table->string('name');
            $table->string('name_normalized')->comment('lowercase stripped for dedup matching');
            $table->string('short_name')->nullable();
            // Encrypted-at-app-layer identifiers (stored as hash prefix + masked value)
            $table->string('npwp_hash')->nullable()->index()->comment('SHA-256 of NPWP for dedup');
            $table->string('npwp_masked')->nullable()->comment('XX.XXX.XXX.X-XXX.XXX');
            $table->string('nik_hash')->nullable()->index()->comment('SHA-256 of NIK (person only)');
            $table->string('nik_masked')->nullable();
            $table->string('nib', 20)->nullable()->index();
            $table->string('status')->default('pending'); // pending, verified, suspended, blacklisted
            $table->string('kyb_status')->default('pending'); // pending, in_review, verified, rejected
            $table->uuid('merged_into_id')->nullable()->comment('non-null = this party was merged');
            $table->timestamp('merged_at')->nullable();
            $table->json('merge_reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('legal_entity_id')->references('id')->on('pty_legal_entities')->nullOnDelete();
        });

        // Party Roles (one party can have multiple roles)
        Schema::create('pty_party_roles', function (Blueprint $table) {
            $table->id();
            $table->uuid('party_id');
            $table->string('role', 50); // supplier, producer, distributor, agent, partner, customer, carrier, tenant, franchisee, shipper
            $table->string('scope_type', 50)->nullable(); // e.g. 'outlet', 'mall', 'entity'
            $table->string('scope_id', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('party_id')->references('id')->on('pty_parties')->cascadeOnDelete();
            $table->unique(['party_id', 'role', 'scope_type', 'scope_id']);
        });

        // Addresses
        Schema::create('pty_addresses', function (Blueprint $table) {
            $table->id();
            $table->uuid('party_id');
            $table->string('label')->default('office'); // office, warehouse, billing, shipping, domicile
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('province')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('country', 2)->default('ID');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('party_id')->references('id')->on('pty_parties')->cascadeOnDelete();
        });

        // Contacts
        Schema::create('pty_contacts', function (Blueprint $table) {
            $table->id();
            $table->uuid('party_id');
            $table->string('label')->default('main'); // main, finance, logistics, legal
            $table->string('contact_type'); // phone, email, whatsapp, fax
            $table->string('value');
            $table->string('name')->nullable()->comment('Contact person name');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('party_id')->references('id')->on('pty_parties')->cascadeOnDelete();
        });

        // Bank Accounts
        Schema::create('pty_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('party_id');
            $table->string('bank_name');
            $table->string('bank_code', 10)->nullable()->comment('BI bank code');
            $table->string('account_number_masked')->comment('last 4 digits visible');
            $table->string('account_number_hash')->index()->comment('SHA-256 for dedup');
            $table->string('account_holder_name');
            $table->string('currency', 3)->default('IDR');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->foreign('party_id')->references('id')->on('pty_parties')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pty_bank_accounts');
        Schema::dropIfExists('pty_contacts');
        Schema::dropIfExists('pty_addresses');
        Schema::dropIfExists('pty_party_roles');
        Schema::dropIfExists('pty_parties');
        Schema::dropIfExists('pty_legal_entities');
    }
};
