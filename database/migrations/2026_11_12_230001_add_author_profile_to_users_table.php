<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Public author profile shown on blog articles: job title and bio per locale ({"en": "…", "ar": "…"}).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('author_title')->nullable()->after('timezone');
            $table->json('author_bio')->nullable()->after('author_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['author_title', 'author_bio']);
        });
    }
};
