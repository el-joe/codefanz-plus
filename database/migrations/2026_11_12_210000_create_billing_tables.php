<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Billing (M6.2): payment links, the immutable payments ledger with its append-only event history and webhook
     * log, gap-free document numbering, invoices and receipts. Ledger rows are never soft deleted; foreign keys
     * restrict deletes so history cannot disappear with a project, milestone or customer.
     */
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('next_value')->default(1);
            $table->timestamps();

            $table->unique(['type', 'year']);
        });

        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();
            // The ulid is the unguessable public token in /pay/{ulid}.
            $table->ulid('ulid')->unique();
            $table->string('reference', 20)->nullable()->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('description', 255)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('single_use')->default(true);
            $table->json('gateways');
            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('uses')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['milestone_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('reference', 24)->nullable()->unique();
            $table->foreignId('payment_link_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('gateway', 30);
            // Gateway-side id (Stripe Checkout Session id); set once the checkout is created.
            $table->string('gateway_payment_id', 191)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('fee_minor')->nullable();
            $table->bigInteger('net_minor')->nullable();
            $table->unsignedBigInteger('refunded_minor')->default(0);
            $table->char('currency', 3);
            $table->string('status', 24)->default('pending')->index();
            $table->string('verified_via', 20)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            // Gateway ids and statuses only; never card numbers, expiry dates or CVCs.
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_payment_id']);
            $table->index(['customer_id', 'status']);
            $table->index(['milestone_id', 'status']);
            $table->index(['status', 'paid_at']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 30);
            $table->string('event_id', 191);
            $table->string('event_type', 100)->nullable();
            $table->boolean('signature_valid');
            $table->json('payload')->nullable();
            $table->unsignedInteger('attempts')->default(1);
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'event_id']);
            $table->index('created_at');
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->string('source', 20);
            $table->foreignId('webhook_event_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['payment_id', 'id']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            // INV-2026-00001, assigned when issued (drafts have none) from document_sequences.
            $table->string('number', 20)->nullable()->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_link_id')->nullable()->constrained()->restrictOnDelete();
            // Customer name, contact, address and tax id frozen at issue time.
            $table->json('customer_snapshot')->nullable();
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            // Tax rate in basis points (1500 = 15%).
            $table->unsignedInteger('tax_rate_bp')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->char('currency', 3);
            $table->string('status', 20)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_minor');
            $table->unsignedBigInteger('total_minor');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            // RCP-2026-00001 from document_sequences.
            $table->string('number', 20)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->json('customer_snapshot');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_links');
        Schema::dropIfExists('document_sequences');
    }
};
