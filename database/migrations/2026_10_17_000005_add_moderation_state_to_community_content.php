<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table): void {
            $table->timestamp('moderated_at')->nullable()->index();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('community_comments', function (Blueprint $table): void {
            $table->timestamp('moderated_at')->nullable()->index();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('community_comments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropIndex(['moderated_at']);
            $table->dropColumn('moderated_at');
        });

        Schema::table('community_posts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropIndex(['moderated_at']);
            $table->dropColumn('moderated_at');
        });
    }
};
