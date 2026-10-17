<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_post_likes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['community_post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_post_likes');
    }
};
