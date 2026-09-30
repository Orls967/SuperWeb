<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Delivery & Takeaway Orders Tracking
        Schema::create('resto_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained('resto_orders')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->string('delivery_type', 30)->default('delivery'); // takeaway, delivery, pickup
            $table->string('courier_name', 100)->nullable();
            $table->string('courier_phone', 30)->nullable();
            $table->string('tracking_number', 50)->nullable();
            $table->string('recipient_name', 100);
            $table->string('recipient_phone', 30);
            $table->text('delivery_address');
            $table->decimal('distance_km', 6, 2)->default(1.0);
            $table->unsignedBigInteger('delivery_fee')->default(0);
            $table->unsignedBigInteger('packaging_fee')->default(0);
            $table->string('status', 30)->default('preparing'); // preparing, on_the_way, delivered, failed
            $table->string('failure_reason')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['outlet_id', 'status']);
            $table->index('order_id');
        });

        // 2. Catering Packages
        Schema::create('resto_catering_packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_per_pax');
            $table->unsignedInteger('min_pax')->default(20);
            $table->json('items')->nullable(); // [{"menu_item_id": 1, "name": "Rendang Daging", "portion": 1}]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Catering Orders
        Schema::create('resto_catering_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('number', 60)->unique();
            $table->foreignId('outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('resto_catering_packages')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->date('event_date');
            $table->time('event_time')->default('11:00:00');
            $table->text('delivery_address');
            $table->unsignedInteger('pax');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('delivery_fee')->default(0);
            $table->unsignedBigInteger('grand_total')->default(0);
            $table->unsignedBigInteger('deposit_amount')->default(0); // 30% hold
            $table->foreignId('deposit_intent_id')->nullable()->constrained('pay_payment_intents')->nullOnDelete();
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('status', 30)->default('inquiry'); // inquiry, quoted, confirmed, cooking, delivered, completed, cancelled
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['outlet_id', 'event_date']);
            $table->index(['status', 'event_date']);
        });

        // 4. Franchise & Royalty Contracts
        Schema::create('resto_outlet_contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('outlet_id')->unique()->constrained('resto_outlets')->cascadeOnDelete();
            $table->foreignId('franchisee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contract_number', 50)->unique();
            $table->decimal('royalty_percent', 5, 2)->default(5.00); // 5.00%
            $table->decimal('marketing_fee_percent', 5, 2)->default(2.00); // 2.00%
            $table->unsignedBigInteger('fixed_monthly_management_fee')->default(0);
            $table->date('valid_from');
            $table->date('valid_until');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. Royalty Postings History
        Schema::create('resto_royalty_postings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('contract_id')->constrained('resto_outlet_contracts')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('gross_sales')->default(0);
            $table->unsignedBigInteger('net_sales')->default(0);
            $table->unsignedBigInteger('royalty_amount')->default(0);
            $table->unsignedBigInteger('marketing_fee_amount')->default(0);
            $table->unsignedBigInteger('franchisee_net_share')->default(0);
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['outlet_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resto_royalty_postings');
        Schema::dropIfExists('resto_outlet_contracts');
        Schema::dropIfExists('resto_catering_orders');
        Schema::dropIfExists('resto_catering_packages');
        Schema::dropIfExists('resto_deliveries');
    }
};
