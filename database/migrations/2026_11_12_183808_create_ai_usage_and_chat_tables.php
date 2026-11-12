<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI Project Assistant conversations (pruned after the configured retention period) and the AI usage
     * ledger every provider call writes to (tokens, estimated cost, latency) for budgets and reporting.
     */
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            // sha256 of the random token only the visitor's browser holds; every call must present it.
            $table->char('token_hash', 64);
            $table->string('locale', 5);
            $table->string('mode', 10)->default('ai');
            $table->string('status', 20)->default('active')->index();
            $table->string('fallback_reason', 30)->nullable();
            $table->json('brief')->nullable();
            $table->unsignedSmallInteger('user_turns')->default(0);
            $table->char('ip_hash', 64)->nullable()->index();
            $table->string('user_agent', 255)->nullable();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10);
            $table->text('content');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('feature', 30)->index();
            $table->string('provider', 20);
            $table->string('model', 100);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            // US cents with four decimals: a single chat turn usually costs a fraction of a cent.
            $table->decimal('estimated_cost_minor', 14, 4)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('status', 20)->index();
            $table->string('error', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chat_session_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
    }
};
