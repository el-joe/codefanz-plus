<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CRM leads (quote form, contact form, manual, later AI assistant / MCP / radar), their activity timeline,
     * private attachments and the services/technologies they asked about.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            // LD-2026-00042; assigned right after insert from the id, so it is nullable for that instant only.
            $table->string('reference', 20)->nullable()->unique();
            $table->string('source', 20)->index();
            $table->string('status', 20)->default('new');
            $table->string('priority', 10)->default('normal');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name', 120);
            $table->string('email', 255)->index();
            $table->string('phone', 30)->nullable();
            $table->string('company', 160)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('preferred_locale', 5);
            $table->string('preferred_contact', 20)->default('email');

            $table->json('platforms')->nullable();
            $table->text('description')->nullable();
            $table->text('features')->nullable();
            $table->text('goals')->nullable();
            $table->string('existing_url', 500)->nullable();
            $table->string('budget_range', 20)->nullable();
            $table->string('timeline', 20)->nullable();
            $table->date('deadline')->nullable();
            // Structured intake (form answers, AI assistant summary) that does not deserve its own column.
            $table->json('brief')->nullable();

            $table->unsignedBigInteger('estimated_value_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->dateTime('follow_up_at')->nullable()->index();
            $table->string('lost_reason', 500)->nullable();

            $table->foreignId('contact_message_id')->nullable()->unique()->constrained('contact_messages')->nullOnDelete();
            // Dedupe hint only: another open lead with the same email existed when this one arrived.
            $table->foreignId('possible_duplicate_of_id')->nullable()->constrained('leads')->nullOnDelete();

            $table->string('locale', 5)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('consent_at')->nullable();

            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'assigned_to']);
            $table->index('created_at');
        });

        Schema::create('lead_service', function (Blueprint $table) {
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->primary(['lead_id', 'service_id']);
        });

        Schema::create('lead_technology', function (Blueprint $table) {
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technology_id')->constrained()->cascadeOnDelete();
            $table->primary(['lead_id', 'technology_id']);
        });

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index();
            $table->text('body')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
        });

        Schema::create('lead_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 40);
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->string('mime', 150);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_attachments');
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('lead_technology');
        Schema::dropIfExists('lead_service');
        Schema::dropIfExists('leads');
    }
};
