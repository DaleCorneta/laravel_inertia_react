<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_comments', function (Blueprint $table): void {
            $table->foreignId('parent_comment_id')->nullable()->after('community_post_id')
                ->constrained('community_comments')->cascadeOnDelete();
            $table->index(['parent_comment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('community_comments', function (Blueprint $table): void {
            $table->dropForeign(['parent_comment_id']);
            $table->dropIndex(['parent_comment_id', 'created_at']);
            $table->dropColumn('parent_comment_id');
        });
    }
};
