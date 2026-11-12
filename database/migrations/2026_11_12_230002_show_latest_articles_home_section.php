<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The "latest articles" homepage section was created hidden until the blog existed (Milestone 7).
     * It only renders when published articles exist, so showing it is safe.
     */
    public function up(): void
    {
        DB::table('page_sections')->where('type', 'latest_articles')->update(['is_visible' => true, 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('page_sections')->where('type', 'latest_articles')->update(['is_visible' => false, 'updated_at' => now()]);
    }
};
