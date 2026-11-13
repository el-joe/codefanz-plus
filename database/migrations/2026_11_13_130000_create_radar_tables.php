<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Freelance Radar (M10): platforms with their honest connector status, collected projects (unique per platform
     * and external id), rule / AI evaluations, saved filter profiles, alert log (no repeat alerts for unchanged
     * projects) and AI proposal drafts (never submitted anywhere).
     */
    public function up(): void
    {
        Schema::create('radar_platforms', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name', 80);
            // api | email | none
            $table->string('connector', 20);
            // active | needs_credentials | unavailable | error (recomputed from credentials and the last run)
            $table->string('status', 30)->index();
            $table->string('status_note', 255)->nullable();
            $table->string('website_url', 255)->nullable();
            // Per-platform kill switch.
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('fetch_interval_minutes')->default(30);
            $table->unsignedSmallInteger('rate_limit_per_hour')->default(30);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->unsignedInteger('last_run_count')->default(0);
            $table->timestamps();
        });

        Schema::create('radar_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('radar_platform_id')->constrained('radar_platforms')->restrictOnDelete();
            $table->string('external_id', 120);
            $table->string('url', 500);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('budget_min_minor')->nullable();
            $table->unsignedBigInteger('budget_max_minor')->nullable();
            $table->char('currency', 3)->nullable();
            // fixed | hourly | unknown
            $table->string('budget_type', 10)->default('unknown');
            $table->json('skills')->nullable();
            $table->char('client_country', 2)->nullable();
            $table->decimal('client_rating', 3, 2)->nullable();
            $table->unsignedBigInteger('client_spent_minor')->nullable();
            $table->boolean('client_payment_verified')->nullable();
            $table->unsignedInteger('proposals_count')->nullable();
            $table->string('language', 5)->default('en')->index();
            $table->timestamp('posted_at')->nullable()->index();
            $table->timestamp('collected_at');
            $table->char('content_hash', 40);
            $table->json('source')->nullable();
            $table->string('status', 20)->default('new');
            // Latest rule score (0–100, transparent breakdown in radar_evaluations) and the optional AI score.
            $table->unsignedTinyInteger('score')->nullable();
            $table->unsignedTinyInteger('ai_score')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['radar_platform_id', 'external_id']);
            $table->index(['status', 'score']);
            $table->index('created_at');
            $table->fullText(['title', 'description']);
        });

        Schema::create('radar_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('radar_project_id')->constrained('radar_projects')->cascadeOnDelete();
            // rules | ai
            $table->string('kind', 10);
            $table->unsignedTinyInteger('score');
            $table->json('breakdown')->nullable();
            $table->text('explanation')->nullable();
            $table->string('model', 100)->nullable();
            $table->char('content_hash', 40);
            $table->char('weights_hash', 40)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['radar_project_id', 'kind', 'id']);
        });

        Schema::create('radar_filters', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->json('rules');
            $table->boolean('is_active')->default(true);
            // Alerts go only to projects matching at least one alerting filter (when any exists).
            $table->boolean('alerts_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('radar_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('radar_project_id')->constrained('radar_projects')->cascadeOnDelete();
            $table->string('channel', 20);
            // immediate | normal | digest
            $table->string('level', 20);
            $table->char('content_hash', 40);
            $table->unsignedTinyInteger('score');
            // pending (waiting for the digest) | sent | failed | skipped
            $table->string('status', 20)->default('pending');
            $table->string('error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['radar_project_id', 'channel', 'content_hash']);
            $table->index(['channel', 'level', 'status']);
        });

        Schema::create('radar_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('radar_project_id')->constrained('radar_projects')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->text('body');
            $table->json('guardrail_flags')->nullable();
            $table->foreignId('ai_generation_id')->nullable()->constrained('ai_generations')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radar_proposals');
        Schema::dropIfExists('radar_alerts');
        Schema::dropIfExists('radar_filters');
        Schema::dropIfExists('radar_evaluations');
        Schema::dropIfExists('radar_projects');
        Schema::dropIfExists('radar_platforms');
    }
};
