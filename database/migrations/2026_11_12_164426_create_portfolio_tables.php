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
        Schema::create('portfolio_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('portfolio_category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 100);
            $table->string('slug', 200);
            $table->timestamps();

            $table->unique(['portfolio_category_id', 'locale'], 'portfolio_category_translations_unique_locale');
            $table->unique(['locale', 'slug']);
        });

        Schema::create('portfolio_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('industry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('web');
            $table->string('client_name', 150)->nullable();
            $table->boolean('is_client_public')->default(false);
            $table->date('project_date')->nullable();
            $table->string('live_url', 500)->nullable();
            $table->string('android_url', 500)->nullable();
            $table->string('ios_url', 500)->nullable();
            $table->string('github_url', 500)->nullable();
            $table->string('video_url', 500)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('visibility', 20)->default('public');
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'visibility', 'sort_order']);
            $table->index(['status', 'is_featured']);
        });

        Schema::create('portfolio_project_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_project_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 200);
            $table->string('slug', 200);
            $table->string('summary', 300)->nullable();
            $table->text('challenge')->nullable();
            $table->text('solution')->nullable();
            $table->longText('results')->nullable();
            // Ordered list aligned with the other locales: [{"label": "…", "value": "…"} | null, …].
            $table->json('key_facts')->nullable();
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 170)->nullable();
            $table->timestamps();

            $table->unique(['portfolio_project_id', 'locale'], 'portfolio_project_translations_unique_locale');
            $table->unique(['locale', 'slug']);
        });

        Schema::create('portfolio_project_technology', function (Blueprint $table) {
            $table->foreignId('portfolio_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technology_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['portfolio_project_id', 'technology_id']);
            $table->index('technology_id');
        });

        Schema::create('portfolio_project_service', function (Blueprint $table) {
            $table->foreignId('portfolio_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['portfolio_project_id', 'service_id']);
            $table->index('service_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_project_service');
        Schema::dropIfExists('portfolio_project_technology');
        Schema::dropIfExists('portfolio_project_translations');
        Schema::dropIfExists('portfolio_projects');
        Schema::dropIfExists('portfolio_category_translations');
        Schema::dropIfExists('portfolio_categories');
    }
};
