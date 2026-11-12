<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Social media (M8.2): connected / manual accounts with encrypted OAuth tokens, posts with one variant per
     * account (platform text, hashtags, first comment, delivery state, idempotency key, platform response) and
     * metric snapshots pulled from the platforms' APIs.
     */
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 30)->index();
            $table->string('name', 150);
            $table->string('handle', 150)->nullable();
            $table->string('profile_url', 500)->nullable();
            // Organization URN id, page id, Instagram user id or X user id. Null for manual accounts.
            $table->string('external_id', 100)->nullable();
            $table->string('status', 30)->index();
            // Encrypted casts: tokens never leave the server in plain text.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('refresh_expires_at')->nullable();
            $table->json('scopes')->nullable();
            // Provider extras, e.g. the Facebook page id an Instagram account publishes through.
            $table->json('meta')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['platform', 'external_id']);
        });

        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('title', 150);
            $table->string('campaign', 80)->nullable()->index();
            $table->string('locale', 5)->default('en');
            $table->string('status', 30)->default('draft')->index();
            $table->string('origin', 10)->default('human');
            $table->string('link_url', 500)->nullable();
            // {medium, campaign, content}; utm_source is the platform of each variant.
            $table->json('utm')->nullable();
            // {media_id: alt text} for the images attached through mediables (collection "social").
            $table->json('media_alt')->nullable();
            // Suggested time while the post is still being written or reviewed (shown as "pending approval").
            $table->timestamp('planned_at')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            // Who ran the AI generation: they can never approve the post.
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('review_note')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('social_post_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained()->restrictOnDelete();
            $table->string('platform', 30)->index();
            $table->text('text');
            $table->string('hashtags', 1000)->nullable();
            $table->string('first_comment', 1000)->nullable();
            $table->boolean('include_media')->default(true);
            $table->foreignId('ai_generation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('pending')->index();
            // Sent with every attempt and kept on the row, so a retry can never create a second post.
            $table->uuid('idempotency_key')->unique();
            $table->unsignedSmallInteger('attempts')->default(0);
            // Intermediate ids (e.g. an Instagram media container) reused by retries.
            $table->json('platform_state')->nullable();
            $table->string('external_id', 191)->nullable();
            $table->string('published_url', 500)->nullable();
            $table->json('platform_response')->nullable();
            $table->string('last_error', 1000)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('exported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('exported_at')->nullable();
            $table->string('metrics_error', 500)->nullable();
            $table->timestamp('metrics_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['social_post_id', 'social_account_id']);
        });

        Schema::create('social_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_post_variant_id')->constrained()->cascadeOnDelete();
            // Null = the platform did not report this metric (never zero-filled).
            $table->unsignedBigInteger('impressions')->nullable();
            $table->unsignedBigInteger('engagements')->nullable();
            $table->unsignedBigInteger('clicks')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('captured_at')->index();
            $table->timestamps();

            $table->index(['social_post_variant_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_metric_snapshots');
        Schema::dropIfExists('social_post_variants');
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('social_accounts');
    }
};
