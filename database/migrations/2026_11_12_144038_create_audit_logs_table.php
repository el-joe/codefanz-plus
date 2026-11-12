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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_label', 120)->nullable();
            $table->string('operation', 100);
            $table->nullableMorphs('auditable');
            $table->foreignId('change_request_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('result', 20)->default('success');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['operation', 'created_at']);
            $table->index(['actor_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
