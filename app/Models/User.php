<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Laragear\TwoFactor\TwoFactorAuthentication;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['prefix', 'first_name', 'middle_name', 'last_name', 'suffix', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract, TwoFactorAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, MustVerifyEmailTrait, Notifiable, TwoFactorAuthentication;

    /** @return HasMany<SocialAccount, $this> */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /** @return HasMany<Article, $this> */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'author_id');
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => $this->formatName([
            $this->prefix,
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ]));
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => $this->formatName([
            $this->first_name,
            $this->middle_initial,
            $this->last_name,
        ]));
    }

    protected function middleInitial(): Attribute
    {
        return Attribute::get(function (): ?string {
            $middleName = trim((string) $this->middle_name);
            $parts = preg_split('/\s+/u', $middleName, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            return isset($parts[0]) ? mb_substr($parts[0], 0, 1).'.' : null;
        });
    }

    /** @param array<int, string|null> $parts */
    private function formatName(array $parts): string
    {
        $normalized = array_map(
            static fn (?string $part): string => preg_replace('/\s+/u', ' ', trim((string) $part)) ?? '',
            $parts,
        );

        return implode(' ', array_filter($normalized, static fn (string $part): bool => $part !== ''));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
