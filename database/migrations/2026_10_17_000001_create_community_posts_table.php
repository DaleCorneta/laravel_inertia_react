<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['author_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_posts');
    }
};
