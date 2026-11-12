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
        Schema::create('technology_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('technology_category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technology_category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['technology_category_id', 'locale'], 'technology_category_translations_unique_locale');
        });

        Schema::create('technologies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technology_category_id')->constrained()->restrictOnDelete();
            $table->string('key', 60)->unique();
            $table->string('icon', 60)->nullable();
            $table->string('website_url', 255)->nullable();
            // Only `supported` or `expertise` are stored; "verified in projects" is computed.
            $table->string('proficiency', 30)->default('supported');
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_visible', 'technology_category_id', 'sort_order']);
            $table->index('proficiency');
        });

        Schema::create('technology_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technology_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->unique(['technology_id', 'locale']);
        });

        Schema::create('service_technology', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technology_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['service_id', 'technology_id']);
            $table->index('technology_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_technology');
        Schema::dropIfExists('technology_translations');
        Schema::dropIfExists('technologies');
        Schema::dropIfExists('technology_category_translations');
        Schema::dropIfExists('technology_categories');
    }
};
