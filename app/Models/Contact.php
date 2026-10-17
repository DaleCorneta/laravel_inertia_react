<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['owner_id', 'prefix', 'first_name', 'middle_name', 'last_name', 'suffix', 'organization', 'title', 'email', 'phone', 'notes'])]
class Contact extends Model
{
    use SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<ContactAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ContactAttachment::class);
    }

    /** @param Builder<Contact> $query */
    public function scopeSearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $query) use ($search): void {
            $query->where('first_name', 'like', '%'.$search.'%')
                ->orWhere('middle_name', 'like', '%'.$search.'%')
                ->orWhere('last_name', 'like', '%'.$search.'%')
                ->orWhere('organization', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%');
        });
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->prefix, $this->first_name, $this->middle_name, $this->last_name, $this->suffix])
            ->map(static fn (?string $part): string => preg_replace('/\s+/u', ' ', trim((string) $part)) ?? '')
            ->filter()
            ->implode(' ');
    }
}
