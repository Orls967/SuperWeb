<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hsp_branch_inventory_lots')) {
            Schema::create('hsp_branch_inventory_lots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->constrained('hsp_pharmacy_branches')->cascadeOnDelete();
                $table->string('drug_code', 32);
                $table->string('drug_name', 128);
                $table->string('lot_number', 64);
                $table->integer('quantity');
                $table->date('expiry_date');
                $table->boolean('is_quarantined')->default(false);
                $table->timestamps();

                $table->index(['branch_id', 'drug_code', 'expiry_date']);
                $table->index(['drug_code', 'is_quarantined']);
            });
        }

        if (! Schema::hasTable('hsp_drug_allergy_matrix')) {
            Schema::create('hsp_drug_allergy_matrix', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('patient_id')->constrained('hsp_patients')->cascadeOnDelete();
                $table->string('allergen_code', 32);
                $table->string('severity', 32)->default('MODERATE');
                $table->text('reaction_description')->nullable();
                $table->timestamps();

                $table->index(['patient_id', 'allergen_code']);
            });
        }

        if (! Schema::hasTable('hsp_chronic_subscriptions')) {
            Schema::create('hsp_chronic_subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->string('subscription_code', 32)->unique();
                $table->foreignId('patient_id')->constrained('hsp_patients')->cascadeOnDelete();
                $table->foreignId('pharmacy_branch_id')->constrained('hsp_pharmacy_branches')->cascadeOnDelete();
                $table->string('drug_code', 32);
                $table->string('drug_name', 128);
                $table->integer('monthly_quantity');
                $table->bigInteger('unit_price_idr');
                $table->date('next_refill_date');
                $table->string('status', 32)->default('ACTIVE');
                $table->timestamps();

                $table->index(['status', 'next_refill_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hsp_chronic_subscriptions');
        Schema::dropIfExists('hsp_drug_allergy_matrix');
        Schema::dropIfExists('hsp_branch_inventory_lots');
    }
};
