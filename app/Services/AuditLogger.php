<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AuditLogger
{
    /** @var list<string> */
    private const EVENTS = [
        'auth.login_succeeded',
        'auth.login_failed',
        'auth.social_account_linked',
        'auth.logout',
        'auth.password_reset',
        'profile.updated',
        'profile.email_changed',
        'profile.password_changed',
        'mfa.enabled',
        'mfa.disabled',
        'mfa.recovery_codes_regenerated',
        'user.created',
        'user.updated',
        'user.activated',
        'user.deactivated',
        'user.roles_updated',
        'role.created',
        'role.updated',
        'contact.created',
        'contact.updated',
        'contact.archived',
        'contact.unarchived',
        'contact.trashed',
        'contact.restored',
        'contact.force_deleted',
        'contact.attachment_uploaded',
        'contact.attachment_deleted',
        'article.created',
        'article.updated',
        'article.published',
        'article.unpublished',
        'article.archived',
        'article.unarchived',
        'article.trashed',
        'article.restored',
        'article.force_deleted',
        'article.cover_image_uploaded',
        'article.cover_image_removed',
        'article.inline_image_uploaded',
        'article.inline_image_removed',
        'community.post_created',
        'community.post_updated',
        'community.post_trashed',
        'community.post_archived',
        'community.post_unarchived',
        'community.post_restored',
        'community.post_force_deleted',
        'community.comment_created',
        'community.comment_updated',
        'community.comment_deleted',
        'community.reply_created',
        'community.post_liked',
        'community.post_unliked',
        'community.post_moderated',
        'community.post_unmoderated',
        'community.comment_moderated',
        'community.comment_unmoderated',
        'community.media_uploaded',
        'community.media_deleted',
    ];

    /** @var list<string> */
    private const FIELDS = [
        'prefix', 'first_name', 'middle_name', 'last_name', 'suffix', 'email', 'is_active', 'name',
        'organization', 'title', 'phone', 'notes',
    ];

    /** @return list<string> */
    public static function eventNames(): array
    {
        return self::EVENTS;
    }

    /** @param array<string, mixed> $properties */
    public function log(string $event, ?Model $subject = null, array $properties = []): void
    {
        if (! in_array($event, self::EVENTS, true)) {
            throw new InvalidArgumentException('Unsupported audit event.');
        }

        if (in_array($event, config('audit.disabled_events', []), true)) {
            return;
        }

        $safeProperties = [];

        foreach (['fields', 'roles_added', 'roles_removed', 'permissions_added', 'permissions_removed'] as $key) {
            if (! isset($properties[$key]) || ! is_array($properties[$key])) {
                continue;
            }

            $values = $key === 'fields'
                ? array_values(array_intersect(self::FIELDS, array_filter($properties[$key], 'is_string')))
                : array_slice(array_values(array_unique(array_filter($properties[$key], static fn (mixed $value): bool => is_int($value) && $value > 0))), 0, 100);

            if ($values !== []) {
                $safeProperties[$key] = $values;
            }
        }

        $activity = activity('audit')
            ->event($event)
            ->withProperties($safeProperties);

        if ($subject !== null) {
            $activity->performedOn($subject);
        }

        $activity->log($event);
    }
}
