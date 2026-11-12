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
        Schema::create('industries', function (Blueprint $table) {
            $table->id();
            $table->string('icon', 40)->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'sort_order']);
        });

        Schema::create('industry_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('industry_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 150);
            $table->string('slug', 200);
            $table->string('summary', 500)->nullable();
            // Ordered list of short labels, e.g. ["LMS", "School ERP"].
            $table->json('typical_solutions')->nullable();
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 170)->nullable();
            $table->timestamps();

            $table->unique(['industry_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('industry_translations');
        Schema::dropIfExists('industries');
    }
};
