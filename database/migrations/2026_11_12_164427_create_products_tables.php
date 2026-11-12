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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('icon', 40)->nullable();
            // Licensing models offered (App\Enums\PricingModel values); the first is highlighted. No prices are stored.
            $table->json('pricing_models')->nullable();
            $table->string('availability', 20)->default('available');
            $table->boolean('demo_enabled')->default(true);
            $table->string('docs_url', 500)->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'sort_order']);
        });

        Schema::create('product_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 150);
            $table->string('slug', 200);
            $table->string('tagline', 200)->nullable();
            $table->longText('description')->nullable();
            // Ordered list aligned with the other locales: [{"title": "…", "description": "…"} | null, …].
            $table->json('features')->nullable();
            $table->text('licensing_notes')->nullable();
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 170)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('product_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('version', 30);
            $table->date('released_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'version']);
            $table->index(['product_id', 'released_at']);
        });

        Schema::create('product_version_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_version_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->text('changelog');
            $table->timestamps();

            $table->unique(['product_version_id', 'locale'], 'product_version_translations_unique_locale');
        });

        Schema::create('product_technology', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technology_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['product_id', 'technology_id']);
            $table->index('technology_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_technology');
        Schema::dropIfExists('product_version_translations');
        Schema::dropIfExists('product_versions');
        Schema::dropIfExists('product_translations');
        Schema::dropIfExists('products');
    }
};
