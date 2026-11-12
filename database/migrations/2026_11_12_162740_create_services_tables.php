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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('tier', 20)->default('core');
            $table->string('icon', 40)->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'tier', 'sort_order']);
        });

        Schema::create('service_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 200);
            $table->string('slug', 200);
            $table->string('summary', 300)->nullable();
            $table->longText('body')->nullable();
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 170)->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('service_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('service_feature_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_feature_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 200);
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->unique(['service_feature_id', 'locale'], 'service_feature_translations_unique_locale');
        });

        Schema::create('service_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('service_faq_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_faq_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('question', 300);
            $table->text('answer')->nullable();
            $table->timestamps();

            $table->unique(['service_faq_id', 'locale'], 'service_faq_translations_unique_locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_faq_translations');
        Schema::dropIfExists('service_faqs');
        Schema::dropIfExists('service_feature_translations');
        Schema::dropIfExists('service_features');
        Schema::dropIfExists('service_translations');
        Schema::dropIfExists('services');
    }
};
