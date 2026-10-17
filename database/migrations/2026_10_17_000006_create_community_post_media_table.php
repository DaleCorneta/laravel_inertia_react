<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_post_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path')->unique();
            $table->string('mime_type', 50);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_post_media');
    }
};
