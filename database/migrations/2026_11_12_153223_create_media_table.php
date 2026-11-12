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
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('original_disk', 20);
            $table->string('original_path');
            $table->string('original_name');
            $table->string('mime_type', 60);
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->boolean('is_animated')->default(false);
            $table->json('alt')->nullable();
            $table->string('folder', 60)->nullable();
            $table->string('status', 20)->default('processing');
            $table->string('error', 255)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index('checksum');
        });

        Schema::create('media_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->string('format', 10);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('disk', 20);
            $table->string('path');
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->unique(['media_id', 'format', 'width']);
        });

        Schema::create('mediables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained()->restrictOnDelete();
            $table->morphs('mediable');
            $table->string('collection', 40);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['media_id', 'mediable_type', 'mediable_id', 'collection'], 'mediables_unique_usage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mediables');
        Schema::dropIfExists('media_variants');
        Schema::dropIfExists('media');
    }
};
