<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('prefix', 20)->nullable();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 20)->nullable();
            $table->string('organization', 160)->nullable();
            $table->string('title', 100)->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
