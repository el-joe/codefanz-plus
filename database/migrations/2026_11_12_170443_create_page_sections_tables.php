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
        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            // Section-managed page key; only "home" today (CMS pages keep their Markdown body).
            $table->string('page', 30)->default('home');
            $table->string('type', 30);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            // Per-type options, e.g. {"limit": 6, "cta_url": "https://…"}.
            $table->json('settings')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['page', 'type']);
            $table->index(['page', 'sort_order']);
        });

        Schema::create('page_section_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_section_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('eyebrow', 80)->nullable();
            $table->string('heading', 200)->nullable();
            $table->string('subheading', 500)->nullable();
            $table->string('cta_label', 60)->nullable();
            // Short check-list lines (hero highlights, delivery promise).
            $table->json('points')->nullable();
            // Ordered [{"title": …, "text": …} | null] rows aligned across locales (process steps, reasons).
            $table->json('items')->nullable();
            $table->timestamps();

            $table->unique(['page_section_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_section_translations');
        Schema::dropIfExists('page_sections');
    }
};
