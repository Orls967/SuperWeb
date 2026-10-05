<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 46.1–46.8 Ekosistem Agen: CRM Leads, Onboarding/Sertifikasi, Tiering, APM Brands, Kepatuhan & Fraud.
return new class extends Migration
{
    public function up(): void
    {
        // 46.1 CRM ringan: lead/prospek, pipeline, aktivitas, konversi.
        Schema::create('agy_leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agent_id')->nullable()->comment('Agen yang ditugaskan');
            $table->string('name', 160);
            $table->string('phone', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('category', 40)->default('general')->comment('general, property_mall, vehicle_store, catering_resto');
            $table->string('status', 24)->default('new')->comment('new, contacted, qualified, proposal, converted, lost');
            $table->unsignedBigInteger('estimated_value_idr')->default(0);
            $table->string('converted_order_id', 60)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agy_agents')->nullOnDelete();
            $table->index(['status', 'category']);
        });

        Schema::create('agy_lead_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('lead_id');
            $table->string('type', 32)->comment('call, meeting, survey, follow_up');
            $table->text('description');
            $table->date('activity_date');
            $table->timestamps();

            $table->foreign('lead_id')->references('id')->on('agy_leads')->cascadeOnDelete();
        });

        // 46.2 Onboarding, Sertifikasi & Lisensi Agen.
        Schema::create('agy_certifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agent_id');
            $table->string('type', 40)->comment('kyc, internal_training, insurance_license, property_license, sole_agent_cert');
            $table->string('license_number', 80)->nullable();
            $table->string('issuing_body', 120)->nullable();
            $table->date('issued_at');
            $table->date('expires_at')->nullable();
            $table->string('status', 24)->default('verified')->comment('pending, verified, expired, revoked');
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agy_agents')->cascadeOnDelete();
            $table->index(['agent_id', 'status']);
        });

        // 46.3 Tier & Gamifikasi.
        Schema::create('agy_agent_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 80);
            $table->unsignedInteger('min_sales_count')->default(0);
            $table->unsignedBigInteger('min_volume_idr')->default(0);
            $table->decimal('bonus_multiplier', 5, 2)->default(1.00);
            $table->json('perks')->nullable();
            $table->timestamps();
        });

        // Add tier_id to agy_agents
        Schema::table('agy_agents', function (Blueprint $table) {
            $table->string('tier_code', 32)->default('BRONZE')->after('status');
            $table->unsignedBigInteger('total_sales_volume_idr')->default(0)->after('tier_code');
            $table->unsignedInteger('total_deals_count')->default(0)->after('total_sales_volume_idr');
        });

        // 46.4 Agensi merek/keagenan impor (APM-style).
        Schema::create('agy_brand_agencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agent_id');
            $table->string('brand_name', 120);
            $table->string('principal_country', 4)->default('ID');
            $table->boolean('has_import_rights')->default(true);
            $table->boolean('has_warranty_service')->default(true);
            $table->string('service_network_ref', 60)->nullable()->comment('Bengkel AutoServe');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agy_agents')->cascadeOnDelete();
        });

        // 46.5 Kepatuhan agen: sanksi, suspensi komisi, banding.
        Schema::create('agy_compliance_incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agent_id');
            $table->string('violation_type', 40)->comment('unauthorized_discount, fake_claim, spam_referral, non_compete_breach');
            $table->string('severity', 24)->default('low')->comment('low, medium, high, severe');
            $table->string('sanction', 32)->default('warning')->comment('warning, commission_freeze, demotion, termination');
            $table->text('description');
            $table->string('status', 24)->default('active')->comment('active, appealed, revoked, resolved');
            $table->text('appeal_notes')->nullable();
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agy_agents')->cascadeOnDelete();
        });

        // 46.6 Deteksi kecurangan / Fraud checks.
        Schema::create('agy_fraud_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agent_id');
            $table->string('check_type', 40)->comment('self_referral, commission_spike, duplicate_customer, velocity');
            $table->unsignedSmallInteger('risk_score')->default(0)->comment('0-100');
            $table->string('decision', 24)->default('clean')->comment('clean, review, flagged, blocked');
            $table->text('reason')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agy_agents')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agy_fraud_checks');
        Schema::dropIfExists('agy_compliance_incidents');
        Schema::dropIfExists('agy_brand_agencies');
        Schema::table('agy_agents', function (Blueprint $table) {
            $table->dropColumn(['tier_code', 'total_sales_volume_idr', 'total_deals_count']);
        });
        Schema::dropIfExists('agy_agent_tiers');
        Schema::dropIfExists('agy_certifications');
        Schema::dropIfExists('agy_lead_activities');
        Schema::dropIfExists('agy_leads');
    }
};
