<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer accounts (companies or individuals) and the portal users who can act for them.
     * Portal isolation is by customer: every client-owned record later carries customer_id.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            // CU-2026-00042; assigned right after insert from the id, so it is nullable for that instant only.
            $table->string('reference', 20)->nullable()->unique();
            $table->string('type', 20)->default('company');
            $table->string('status', 20)->default('active')->index();
            $table->string('company_name', 160);
            $table->string('contact_name', 120)->nullable();
            $table->string('email', 255)->nullable()->index();
            $table->string('phone', 30)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->string('billing_address_line1', 200)->nullable();
            $table->string('billing_address_line2', 200)->nullable();
            $table->string('billing_city', 100)->nullable();
            $table->string('billing_state', 100)->nullable();
            $table->string('billing_postal_code', 20)->nullable();
            $table->string('preferred_locale', 5)->default('en');
            $table->text('notes')->nullable();
            $table->foreignId('created_from_lead_id')->nullable()->unique()->constrained('leads')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_name');
            $table->index('created_at');
        });

        Schema::create('customer_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->timestamp('invited_at')->nullable();
            // Access to this customer was switched off; the row stays for the audit trail and can be restored.
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'user_id']);
            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_user');
        Schema::dropIfExists('customers');
    }
};
