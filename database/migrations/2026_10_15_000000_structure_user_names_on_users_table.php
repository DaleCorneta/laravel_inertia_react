<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('prefix')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('suffix')->nullable();
        });

        DB::table('users')->select(['id', 'name'])->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $parts = preg_split('/\s+/u', trim($user->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $firstName = array_shift($parts) ?: 'Unknown';
                $lastName = count($parts) > 0 ? array_pop($parts) : '';

                DB::table('users')->where('id', $user->id)->update([
                    'first_name' => $firstName,
                    'middle_name' => $parts === [] ? null : implode(' ', $parts),
                    'last_name' => $lastName,
                ]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable(false)->change();
            $table->string('last_name')->nullable(false)->change();
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable();
        });

        DB::table('users')->select(['id', 'prefix', 'first_name', 'middle_name', 'last_name', 'suffix'])
            ->orderBy('id')
            ->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    $parts = array_map(
                        static fn (?string $part): string => trim((string) $part),
                        [$user->prefix, $user->first_name, $user->middle_name, $user->last_name, $user->suffix],
                    );
                    $name = implode(' ', array_filter($parts, static fn (string $part): bool => $part !== ''));

                    DB::table('users')->where('id', $user->id)->update(['name' => $name]);
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->dropColumn(['prefix', 'first_name', 'middle_name', 'last_name', 'suffix']);
        });
    }
};
