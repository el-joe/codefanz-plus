<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * n8n integration (M10): outbound workflows (signed webhooks for domain events) and inbound endpoints
     * (signed requests that run one whitelisted action), plus the run log used for retries, idempotency and
     * replay protection.
     */
    public function up(): void
    {
        Schema::create('automation_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name', 120);
            // outbound | inbound
            $table->string('direction', 10)->index();
            $table->string('url', 500)->nullable();
            // Encrypted HMAC secret, one per workflow.
            $table->text('secret');
            // Outbound: subscribed events. Inbound: the single whitelisted action.
            $table->json('events')->nullable();
            $table->string('action', 40)->nullable();
            // Inbound actions run with this staff member's permissions.
            $table->foreignId('acts_as_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('include_personal_data')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 20)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('automation_workflow_id')->constrained('automation_workflows')->cascadeOnDelete();
            $table->string('direction', 10);
            $table->string('event', 60)->index();
            // Outbound: our delivery id. Inbound: the sender's delivery id (replay protection).
            $table->string('idempotency_key', 100);
            $table->char('payload_hash', 64);
            $table->json('payload')->nullable();
            // pending | succeeded | failed | rejected
            $table->string('status', 20)->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('response', 1000)->nullable();
            $table->string('error', 500)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['automation_workflow_id', 'idempotency_key']);
            $table->index(['automation_workflow_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_workflows');
    }
};
