<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI Content Studio (M8.1): the brand voice every prompt carries, the versioned prompt library, the generation
     * history (inputs, output, tokens, cost, guardrail flags and what the result became) and the link from an
     * AI change request back to the generation that produced it.
     */
    public function up(): void
    {
        Schema::create('brand_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->boolean('is_default')->default(false)->index();
            // {locale: text} for tone and audience; {locale: [rules]} for the do / don't lists.
            $table->json('tone')->nullable();
            $table->json('audience')->nullable();
            $table->json('do_rules')->nullable();
            $table->json('dont_rules')->nullable();
            // Phrases the output must never contain (any language), e.g. "guaranteed", "#1".
            $table->json('banned_claims')->nullable();
            // [{en, ar}] term pairs used for writing and EN → AR translation.
            $table->json('glossary')->nullable();
            // Extra hosts links may point to besides the site itself.
            $table->json('allowed_domains')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name', 120);
            $table->string('purpose', 30)->index();
            // Null = works for any output language.
            $table->string('locale', 5)->nullable();
            $table->string('description', 255)->nullable();
            $table->text('system_template');
            $table->text('user_template');
            // [{name, label, required, max}]
            $table->json('variables');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prompt_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('system_template');
            $table->text('user_template');
            $table->json('variables');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['prompt_template_id', 'version']);
        });

        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('prompt_template_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('template_version')->nullable();
            $table->string('purpose', 30)->index();
            $table->string('mode', 20)->default('write');
            $table->nullableMorphs('target');
            $table->json('locales');
            $table->json('inputs');
            $table->json('output')->nullable();
            $table->json('guardrail_flags')->nullable();
            $table->string('provider', 20)->nullable();
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('estimated_cost_minor', 14, 4)->default(0);
            $table->string('status', 20)->index();
            $table->boolean('is_dry_run')->default(false);
            $table->string('error', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::table('change_requests', function (Blueprint $table) {
            $table->foreignId('ai_generation_id')->nullable()->after('requested_by')->constrained()->nullOnDelete();
            $table->json('guardrail_flags')->nullable()->after('proposed');
            $table->index(['origin', 'status']);
            $table->index('target_type');
        });
    }

    public function down(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_generation_id');
            $table->dropColumn('guardrail_flags');
            $table->dropIndex(['origin', 'status']);
            $table->dropIndex(['target_type']);
        });

        Schema::dropIfExists('ai_generations');
        Schema::dropIfExists('prompt_template_versions');
        Schema::dropIfExists('prompt_templates');
        Schema::dropIfExists('brand_profiles');
    }
};
