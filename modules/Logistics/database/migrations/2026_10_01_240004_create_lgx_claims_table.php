<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number', 32)->unique();
            $table->foreignId('shipment_id')->constrained('lgx_shipments')->cascadeOnDelete();
            $table->string('claim_type', 16); // damage, loss, delay
            $table->boolean('insured')->default(false);
            $table->unsignedBigInteger('claimed_amount_idr');
            $table->unsignedBigInteger('cap_amount_idr');
            $table->unsignedBigInteger('approved_amount_idr')->nullable();
            $table->string('description', 1000);
            $table->string('status', 16)->default('draft'); // draft, submitted, approved, rejected, paid
            // Anti bayar ganda: satu klaim aktif (bukan ditolak) per resi, dijamin unique index.
            $table->string('active_key', 32)->nullable()->unique();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete(); // pembuat
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete(); // pengaju
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete(); // penyetuju
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_notes', 500)->nullable();
            $table->unsignedBigInteger('paid_amount_idr')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('shipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_claims');
    }
};
