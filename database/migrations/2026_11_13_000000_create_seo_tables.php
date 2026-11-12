<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Per-locale SEO settings that have no column on the entity's translation: focus keyword, canonical
        // override and noindex for content entities (seoable morph), and title/description overrides for the
        // fixed site pages (route_key, e.g. "services.index").
        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('seoable');
            $table->string('route_key', 60)->nullable();
            $table->string('locale', 10);
            $table->string('title', 70)->nullable();
            $table->string('description', 170)->nullable();
            $table->string('focus_keyword', 100)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->boolean('noindex')->default(false);
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id', 'locale']);
            $table->unique(['route_key', 'locale']);
            $table->index('noindex');
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 500)->unique();
            $table->string('to_path', 1000)->nullable();
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_automatic')->default(false);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'status_code']);
        });

        Schema::create('seo_audits', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('queued')->index();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('pages_crawled')->default(0);
            $table->unsignedTinyInteger('health_score')->nullable();
            $table->json('summary')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('seo_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_audit_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('category', 30);
            $table->string('severity', 10);
            $table->string('url', 1000);
            $table->string('locale', 10)->nullable();
            $table->nullableMorphs('seoable');
            $table->string('route_key', 60)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['seo_audit_id', 'severity']);
            $table->index(['seo_audit_id', 'category']);
        });

        Schema::create('analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('source', 10);
            $table->string('report_key', 60);
            $table->date('date');
            $table->json('dimensions')->nullable();
            $table->json('values');
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['source', 'report_key', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_snapshots');
        Schema::dropIfExists('seo_issues');
        Schema::dropIfExists('seo_audits');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('seo_meta');
    }
};
