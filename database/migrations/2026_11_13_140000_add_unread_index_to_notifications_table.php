<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M11 performance: the admin and portal layouts count the user's unread notifications on every page
 * (`notifiable_type`, `notifiable_id`, `read_at is null`); the morph index alone scans every read row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_unread_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_notifiable_unread_index');
        });
    }
};
