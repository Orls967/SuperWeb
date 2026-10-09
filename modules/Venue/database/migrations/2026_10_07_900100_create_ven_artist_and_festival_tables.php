<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ven_artist_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('venue_id');
            $table->uuid('event_id');
            $table->string('contract_number')->unique();
            $table->string('artist_name');
            $table->unsignedBigInteger('advance_amount'); // minor unit IDR
            $table->decimal('door_share_percentage', 5, 2)->default(0.00); // e.g. 15.00%
            $table->unsignedBigInteger('total_door_sales')->default(0);
            $table->unsignedBigInteger('door_share_gross')->default(0);
            $table->unsignedBigInteger('net_payout_amount')->default(0); // door_share_gross - advance
            $table->string('currency', 10)->default('IDR');
            $table->string('status', 32)->default('active'); // active, settled, terminated
            $table->timestamps();
        });

        Schema::create('ven_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('membership_number')->unique();
            $table->string('tier', 32); // Sun, Moon, Infinity
            $table->unsignedBigInteger('loyalty_points')->default(0);
            $table->decimal('discount_rate', 5, 2)->default(0.10); // 10%, 15%, 20%
            $table->string('status', 32)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ven_festival_bundles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('bundle_code')->unique();
            $table->string('bundle_name');
            $table->unsignedBigInteger('total_package_price'); // IDR minor unit
            $table->json('vendor_allocations'); // [{"vendor_code": "HOTEL_RESORT", "amount": 1000000, "account": "hotel:settlement"}, ...]
            $table->string('status', 32)->default('active');
            $table->timestamps();
        });

        Schema::create('ven_bundle_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('festival_bundle_id');
            $table->uuid('customer_user_id');
            $table->string('order_number')->unique();
            $table->unsignedBigInteger('total_paid');
            $table->string('status', 32)->default('pending_settlement'); // pending_settlement, settled
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ven_bundle_orders');
        Schema::dropIfExists('ven_festival_bundles');
        Schema::dropIfExists('ven_memberships');
        Schema::dropIfExists('ven_artist_contracts');
    }
};
