<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Project delivery (M6.1): projects and their team, milestones with dependencies, internal notes and
     * client updates, per-project conversations with read tracking, and versioned private documents.
     * Every client-owned table carries customer_id so portal queries can scope with forCurrentCustomer().
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            // PR-2026-00042; assigned right after insert from the id, so it is nullable for that instant only.
            $table->string('reference', 20)->nullable()->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('type', 20)->default('web');
            $table->string('status', 20)->default('planning');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('project_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('budget_minor')->nullable();
            $table->char('currency', 3)->default('USD');
            // Field visibility in the portal (the client always sees name, status, dates and milestones).
            $table->boolean('show_budget_to_client')->default(true);
            $table->boolean('show_team_to_client')->default(true);
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'due_date']);
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 80)->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->char('currency', 3)->default('USD');
            // Denormalised cache, recomputed from the payments ledger in M6.2. Never written from forms.
            $table->unsignedBigInteger('paid_minor')->default(0);
            // Latest "request changes" note from the client.
            $table->text('client_feedback')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['project_id', 'sort_order']);
            $table->index(['status', 'due_date']);
        });

        Schema::create('milestone_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_id')->constrained('milestones')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['milestone_id', 'depends_on_id']);
        });

        Schema::create('project_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // internal = team only; client = shown as an update in the portal.
            $table->string('visibility', 20)->index();
            $table->text('body');
            $table->timestamps();

            $table->index(['project_id', 'visibility', 'created_at']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            // Null = the project's general thread (label rendered in the reader's language).
            $table->string('subject', 160)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'last_message_at']);
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Written by a portal user (true) or by CodeFanz staff (false); fixed when the message is posted.
            $table->boolean('from_client')->default(false);
            $table->text('body');
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });

        Schema::create('conversation_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            // Set when the file was sent as a conversation attachment.
            $table->foreignId('conversation_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->string('category', 20)->default('other');
            $table->string('visibility', 20)->index();
            // Uploading a file with an existing title in the same project adds the next version.
            $table->unsignedInteger('version')->default(1);
            $table->string('disk', 40);
            $table->string('path', 255);
            $table->string('original_name', 200);
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('uploaded_by_client')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'title', 'version']);
            $table->index(['customer_id', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('conversation_reads');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('project_notes');
        Schema::dropIfExists('milestone_dependencies');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
