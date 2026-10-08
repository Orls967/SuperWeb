<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 182.1: Omnichannel store inventory per size/color
        Schema::create('fsh_store_inventories', function (Blueprint $table) {
            $table->id();
            $table->string('sku_code')->unique();
            $table->string('store_code');
            $table->integer('stock_on_hand');
            $table->timestamps();
        });

        // 182.3: Textile circular take-back & customer credit issuance (issued once)
        Schema::create('fsh_textile_takebacks', function (Blueprint $table) {
            $table->id();
            $table->string('takeback_code')->unique();
            $table->unsignedBigInteger('customer_id');
            $table->string('garment_type');
            $table->string('grading'); // RESALE, REPAIR, RECYCLE
            $table->decimal('loyalty_credit_idr', 18, 2);
            $table->boolean('credit_issued')->default(false);
            $table->timestamps();
        });

        // 182.2: Made-to-measure orders with privacy consent gating
        Schema::create('fsh_custom_tailorings', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->unique();
            $table->unsignedBigInteger('customer_id');
            $table->boolean('measurement_consent_granted')->default(false);
            $table->string('design_spec');
            $table->string('status')->default('IN_PRODUCTION');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fsh_custom_tailorings');
        Schema::dropIfExists('fsh_textile_takebacks');
        Schema::dropIfExists('fsh_store_inventories');
    }
};
