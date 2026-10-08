<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ugc_community_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('review_code')->unique();
            $table->string('author_user_id')->index();
            $table->string('verified_order_id')->nullable(); // 282.2 & 282.4
            $table->text('review_content');
            $table->boolean('is_purchase_verified')->default(false);
            $table->boolean('contains_pii_violation')->default(false); // 282.5
            $table->string('moderation_status')->default('APPROVED'); // APPROVED, REMOVED_PII_VIOLATION, FLAGGED
            $table->integer('creator_points_awarded')->default(0); // 282.2
            $table->decimal('contributor_trust_score', 4, 2)->default(5.00); // 282.7 (0-10)
            $table->timestamps();
        });

        Schema::create('ugc_social_live_orders', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique(); // 282.4 idempotent
            $table->string('live_session_id')->index();
            $table->string('product_sku');
            $table->integer('order_quantity');
            $table->decimal('total_amount_usd', 15, 2);
            $table->decimal('host_commission_usd', 15, 2)->default(0.00); // 282.3
            $table->string('status')->default('ORDER_PLACED'); // ORDER_PLACED, AUTO_CANCELLED_OUT_OF_STOCK
            $table->boolean('refund_issued')->default(false); // 282.6
            $table->timestamps();
        });

        Schema::create('ugc_live_inventory_reserves', function (Blueprint $table) {
            $table->id();
            $table->string('product_sku')->unique();
            $table->integer('available_stock_count');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ugc_live_inventory_reserves');
        Schema::dropIfExists('ugc_social_live_orders');
        Schema::dropIfExists('ugc_community_reviews');
    }
};
