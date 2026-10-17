<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->json('content')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['author_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
