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
        Schema::create('post_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('post_category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 100);
            $table->string('slug', 200);
            $table->string('description', 300)->nullable();
            $table->timestamps();

            $table->unique(['post_category_id', 'locale'], 'post_category_translations_unique_locale');
            $table->unique(['locale', 'slug']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('tag_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 100);
            $table->string('slug', 200);
            $table->timestamps();

            $table->unique(['tag_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('post_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->string('origin', 10)->default('human');
            $table->boolean('is_featured')->default(false);
            // Editors may publish in one language on purpose; the checklist then stops warning about the missing one.
            $table->boolean('is_single_language')->default(false);
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('review_note')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['status', 'scheduled_for']);
        });

        Schema::create('post_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 200);
            $table->string('slug', 200);
            $table->string('excerpt', 400)->nullable();
            // Sanitized HTML (App\Services\Blog\HtmlSanitizer) with heading anchors; never raw editor output.
            $table->longText('body')->nullable();
            // [{"level": 2|3, "id": "…", "text": "…"}] derived from the body's H2/H3 headings.
            $table->json('toc')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 170)->nullable();
            $table->string('focus_keyword', 100)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['post_id', 'tag_id']);
        });

        Schema::create('post_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_label', 150)->nullable();
            $table->string('reason', 30)->default('saved');
            $table->foreignId('restored_from_id')->nullable()->constrained('post_revisions')->nullOnDelete();
            $table->unsignedInteger('lock_version');
            // {"translations": {locale: {title, slug, excerpt, body, seo_title, …}}, "meta": {category, tags, …}}
            $table->json('snapshot');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['post_id', 'id']);
        });

        Schema::create('post_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('item', 40);
            $table->boolean('is_manual')->default(false);
            $table->boolean('is_passed')->default(false);
            $table->string('detail', 300)->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'item']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_checklist_items');
        Schema::dropIfExists('post_revisions');
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('post_translations');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('tag_translations');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('post_category_translations');
        Schema::dropIfExists('post_categories');
    }
};
