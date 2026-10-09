<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('subscription_code')->unique();
            $table->string('customer_golden_id')->index();
            $table->string('business_line');
            $table->string('plan_code');
            $table->decimal('price', 15, 2)->default(0);
            $table->string('status')->default('ACTIVE'); // ACTIVE, TRIAL, PAUSED, SUSPENDED, CANCELLED, REACTIVATED
            $table->boolean('service_active')->default(true);
            $table->boolean('benefits_active')->default(true);
            $table->string('pause_reason')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_code')->unique();
            $table->string('subscription_code')->index();
            $table->string('customer_golden_id')->index();
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('ISSUED'); // ISSUED, PAID, DUNNING, SUSPENDED, ESCALATED_COLLECTION, WRITTEN_OFF
            $table->integer('dunning_step')->default(0);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('write_off_approved_by')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_retention_playbooks', function (Blueprint $table) {
            $table->id();
            $table->string('customer_golden_id')->index();
            $table->decimal('churn_risk_score', 5, 2);
            $table->decimal('offered_discount_pct', 5, 2)->default(0);
            $table->decimal('margin_guard_min_pct', 5, 2)->default(10.00);
            $table->string('status')->default('OPEN'); // OPEN, OFFERED, PREVENTED, ESCALATED_HUMAN, LOST
            $table->boolean('prevented_churn')->default(false);
            $table->timestamps();
        });

        Schema::create('crm_winback_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('cohort_name')->index();
            $table->string('customer_golden_id')->index();
            $table->string('special_offer_code');
            $table->boolean('converted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_winback_campaigns');
        Schema::dropIfExists('crm_retention_playbooks');
        Schema::dropIfExists('crm_subscription_invoices');
        Schema::dropIfExists('crm_subscriptions');
    }
};
