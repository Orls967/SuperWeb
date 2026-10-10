<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Profil Loyalty Member Duta Mall
        Schema::create('mall_loyalty_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('tier')->default('silver'); // silver, gold, platinum
            $table->unsignedBigInteger('lifetime_spend')->default(0);
            $table->unsignedBigInteger('current_year_spend')->default(0);
            $table->timestamp('tier_expires_at')->nullable();
            $table->timestamps();
        });

        // 2. Batch Poin Loyalitas (Untuk FIFO Expiry)
        Schema::create('mall_point_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('points_earned');
            $table->unsignedInteger('points_remaining');
            $table->string('source_type')->default('receipt_claim');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('earned_at');
            $table->timestamp('expires_at');
            $table->boolean('is_expired')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_expired', 'expires_at']);
        });

        // 3. Klaim Struk Belanja Tenant (Klaim Poin Unik)
        Schema::create('mall_receipt_claims', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('mall_tenants')->nullOnDelete();
            $table->string('receipt_number')->unique(); // Mencegah klaim ganda struk yang sama
            $table->date('receipt_date');
            $table->unsignedBigInteger('receipt_amount');
            $table->unsignedInteger('points_earned')->default(0);
            $table->string('tier_at_claim')->default('silver');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('rejection_reason')->nullable();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // 4. Katalog / Template Voucher Mall
        Schema::create('mall_voucher_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('points_required');
            $table->unsignedBigInteger('nominal_value'); // Rupiah diskon
            $table->unsignedBigInteger('min_spend')->default(0);
            $table->unsignedInteger('validity_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. Voucher Mall Terbit (Milik User)
        Schema::create('mall_vouchers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('voucher_code')->unique();
            $table->foreignId('template_id')->nullable()->constrained('mall_voucher_templates')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('mall_tenants')->nullOnDelete(); // Spesifik tenant jika ada
            $table->unsignedBigInteger('nominal_value');
            $table->unsignedBigInteger('min_spend')->default(0);
            $table->unsignedInteger('points_spent')->default(0);
            $table->string('status')->default('active'); // active, used, expired, settled
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_at_tenant_id')->nullable()->constrained('mall_tenants')->nullOnDelete();
            $table->string('used_transaction_ref')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->string('settlement_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'settled_at']);
        });

        // 6. Area Event & Atrium Mall
        Schema::create('mall_event_spaces', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('property_id')->constrained('mall_properties')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('area_sqm', 8, 2)->default(0);
            $table->unsignedBigInteger('daily_rate')->default(0);
            $table->unsignedBigInteger('hourly_rate')->default(0);
            $table->unsignedInteger('max_booths')->default(10);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 7. Booking Event & Sewa Atrium
        Schema::create('mall_event_bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('property_id')->constrained('mall_properties')->cascadeOnDelete();
            $table->foreignId('event_space_id')->constrained('mall_event_spaces')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('mall_tenants')->nullOnDelete();
            $table->string('booking_number')->unique();
            $table->string('event_name');
            $table->string('event_type')->default('bazaar'); // bazaar, exhibition, concert, product_launch, community
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('booth_count')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('status')->default('draft'); // draft, confirmed, ongoing, completed, cancelled
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['event_space_id', 'start_date', 'end_date', 'status'], 'mall_evt_bkng_space_date_stat_idx');
        });

        // 8. Manajemen Aset & Fasilitas Gedung Mall
        Schema::create('mall_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('property_id')->constrained('mall_properties')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('mall_units')->nullOnDelete();
            $table->string('asset_tag')->unique();
            $table->string('name');
            $table->string('category')->default('hvac'); // hvac, elevator, electrical, plumbing, fire_safety, civil
            $table->string('brand')->nullable();
            $table->string('model_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('installation_date')->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->string('status')->default('operational'); // operational, maintenance, broken, decommissioned
            $table->unsignedInteger('pm_frequency_days')->default(30); // Interval Preventive Maintenance
            $table->date('last_pm_date')->nullable();
            $table->date('next_pm_date')->nullable();
            $table->text('location_notes')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'status']);
            $table->index(['next_pm_date', 'status']);
        });

        // 9. Work Orders / Surat Perintah Kerja (SPK) Perawatan & Perbaikan
        Schema::create('mall_work_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('property_id')->constrained('mall_properties')->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('mall_assets')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('mall_units')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('mall_tenants')->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('type')->default('preventive'); // preventive, corrective, emergency, tenant_request
            $table->string('priority')->default('medium'); // low, medium, high, emergency
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open'); // open, in_progress, on_hold, completed, cancelled
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_date')->nullable();
            $table->unsignedInteger('sla_hours')->default(24);
            $table->boolean('sla_breached')->default(false);
            $table->unsignedBigInteger('parts_cost')->default(0);
            $table->unsignedBigInteger('labor_cost')->default(0);
            $table->unsignedBigInteger('total_cost')->default(0);
            $table->boolean('is_billable_to_tenant')->default(false);
            $table->foreignId('billed_invoice_id')->nullable()->constrained('mall_invoices')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'status']);
            $table->index(['assigned_to_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mall_work_orders');
        Schema::dropIfExists('mall_assets');
        Schema::dropIfExists('mall_event_bookings');
        Schema::dropIfExists('mall_event_spaces');
        Schema::dropIfExists('mall_vouchers');
        Schema::dropIfExists('mall_voucher_templates');
        Schema::dropIfExists('mall_receipt_claims');
        Schema::dropIfExists('mall_point_batches');
        Schema::dropIfExists('mall_loyalty_members');
    }
};
