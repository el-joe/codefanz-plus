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
        Schema::create('careers', function (Blueprint $table) {
            $table->id();
            $table->string('employment_type', 20)->default('full_time');
            $table->boolean('is_remote')->default(false);
            $table->string('status', 20)->default('draft');
            $table->date('closes_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'closes_at', 'sort_order']);
        });

        Schema::create('career_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 150);
            $table->string('slug', 200);
            // Department and location are shown to visitors, so they are translated too.
            $table->string('department', 100)->nullable();
            $table->string('location', 150)->nullable();
            $table->string('summary', 300)->nullable();
            // Ordered lists of short lines, e.g. ["Build REST APIs", "Review code"].
            $table->json('responsibilities')->nullable();
            $table->json('requirements')->nullable();
            $table->text('body')->nullable();
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 170)->nullable();
            $table->timestamps();

            $table->unique(['career_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('career_translations');
        Schema::dropIfExists('careers');
    }
};
