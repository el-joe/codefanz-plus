<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MCP server (M9): registered MCP clients (one Sanctum token each, stored hashed in personal_access_tokens with
     * the client as tokenable) and the tool-call log (sanitized arguments, outcome, latency), pruned after the
     * retention window.
     */
    public function up(): void
    {
        Schema::create('mcp_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('description', 255)->nullable();
            // The person the client acts for: every call is limited to what this user may do.
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('active')->index();
            $table->json('scopes');
            // Optional IP / CIDR allow-list; empty means any address.
            $table->json('allowed_ips')->nullable();
            $table->unsignedSmallInteger('rate_limit_per_minute')->default(60);
            $table->boolean('allow_lead_pii')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('token_issued_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('mcp_tool_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mcp_client_id')->constrained('mcp_clients')->cascadeOnDelete();
            $table->string('tool', 80)->index();
            $table->string('method', 10);
            // Sanitized: secrets and personal data redacted, long strings truncated.
            $table->json('arguments')->nullable();
            $table->string('result', 20)->index();
            $table->unsignedSmallInteger('http_status');
            $table->string('error_code', 60)->nullable();
            $table->unsignedInteger('duration_ms');
            $table->foreignId('change_request_id')->nullable()->constrained('change_requests')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_tool_calls');
        Schema::dropIfExists('mcp_clients');
    }
};
