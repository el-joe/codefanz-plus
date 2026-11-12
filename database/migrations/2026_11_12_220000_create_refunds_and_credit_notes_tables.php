<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Billing (M6.3): four-eyes refunds with their gateway outcome, gap-free credit notes, and the seller's details
     * frozen on invoices, receipts and credit notes at issue time.
     */
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            // RF-2026-00001, from the id after insert.
            $table->string('reference', 24)->nullable()->unique();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('gateway', 30);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 24)->default('requested')->index();
            $table->string('reason', 500);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->string('gateway_refund_id', 191)->nullable();
            $table->string('failure_reason', 255)->nullable();
            // External reference recorded when a refund had to be completed outside the gateway API.
            $table->string('manual_reference', 191)->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_refund_id']);
            $table->index(['payment_id', 'status']);
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            // CN-2026-00001 from document_sequences (gap-free).
            $table->string('number', 20)->unique();
            $table->foreignId('refund_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->json('customer_snapshot');
            $table->json('seller_snapshot');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('reason', 500);
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->json('seller_snapshot')->nullable()->after('customer_snapshot');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->json('seller_snapshot')->nullable()->after('customer_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', fn (Blueprint $table) => $table->dropColumn('seller_snapshot'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('seller_snapshot'));
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('refunds');
    }
};
