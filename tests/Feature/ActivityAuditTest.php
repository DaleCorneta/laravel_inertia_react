<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logger_keeps_only_allowlisted_metadata_and_records_actor_and_subject(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        app(AuditLogger::class)->log('user.updated', $user, [
            'fields' => ['email', 'password', ['first_name'], 'unknown'],
            'roles_added' => [2, '3', 0, -1],
            'email' => 'private@example.test',
            'password' => 'NeverStoreThisPassword',
            'access_token' => 'NeverStoreThisToken',
        ]);

        $activity = Activity::query()->where('event', 'user.updated')->sole();

        $this->assertSame($user->id, $activity->causer_id);
        $this->assertSame($user->id, $activity->subject_id);
        $this->assertSame([
            'fields' => ['email'],
            'roles_added' => [2],
        ], $activity->properties->toArray());
        $this->assertStringNotContainsString('private@example.test', $activity->toJson());
        $this->assertStringNotContainsString('NeverStoreThisPassword', $activity->toJson());
        $this->assertStringNotContainsString('NeverStoreThisToken', $activity->toJson());
    }

    public function test_failed_login_is_recorded_without_credentials_or_actor(): void
    {
        $this->post(route('login.store'), [
            'email' => 'private@example.test',
            'password' => 'NeverStoreThisPassword',
        ])->assertSessionHasErrors('email');

        $activity = Activity::query()->where('event', 'auth.login_failed')->sole();

        $this->assertNull($activity->causer_id);
        $this->assertNull($activity->subject_id);
        $this->assertSame([], $activity->properties->toArray());
        $this->assertStringNotContainsString('private@example.test', $activity->toJson());
        $this->assertStringNotContainsString('NeverStoreThisPassword', $activity->toJson());
    }

    public function test_known_audit_events_are_enabled_by_default_and_can_be_disabled_individually(): void
    {
        $this->assertSame([], config('audit.disabled_events'));
        $logger = app(AuditLogger::class);
        $logger->log('auth.logout');
        $this->assertDatabaseHas('activity_log', ['event' => 'auth.logout']);

        config(['audit.disabled_events' => ['auth.logout']]);
        $logger->log('auth.logout');
        $this->assertDatabaseCount('activity_log', 1);
    }

    public function test_disabling_events_does_not_bypass_the_audit_event_allowlist(): void
    {
        config(['audit.disabled_events' => ['unknown.event']]);

        $this->expectException(\InvalidArgumentException::class);
        app(AuditLogger::class)->log('unknown.event');
    }

    public function test_disabled_events_are_read_as_a_comma_separated_environment_list(): void
    {
        $key = 'AUDIT_DISABLED_EVENTS';
        $original = ['env' => $_ENV[$key] ?? null, 'server' => $_SERVER[$key] ?? null, 'process' => getenv($key)];

        try {
            $_ENV[$key] = $_SERVER[$key] = 'auth.logout, community.post_updated';
            putenv($key.'=auth.logout, community.post_updated');
            Env::enablePutenv();
            $config = require base_path('config/audit.php');
            $this->assertSame(['auth.logout', 'community.post_updated'], $config['disabled_events']);
        } finally {
            foreach (['env' => '_ENV', 'server' => '_SERVER'] as $source => $target) {
                if ($original[$source] === null) {
                    unset($GLOBALS[$target][$key]);
                } else {
                    $GLOBALS[$target][$key] = $original[$source];
                }
            }
            $original['process'] === false ? putenv($key) : putenv($key.'='.$original['process']);
            Env::enablePutenv();
        }
    }

    public function test_activity_retention_can_be_overridden_from_the_environment(): void
    {
        $key = 'ACTIVITYLOG_CLEAN_AFTER_DAYS';
        $original = ['env' => $_ENV[$key] ?? null, 'server' => $_SERVER[$key] ?? null, 'process' => getenv($key)];

        try {
            $_ENV[$key] = $_SERVER[$key] = '45';
            putenv($key.'=45');
            Env::enablePutenv();
            $config = require base_path('config/activitylog.php');
            $this->assertSame(45, $config['clean_after_days']);

            $_ENV[$key] = $_SERVER[$key] = '-1';
            putenv($key.'=-1');
            $config = require base_path('config/activitylog.php');
            $this->assertSame(1, $config['clean_after_days']);
        } finally {
            foreach (['env' => '_ENV', 'server' => '_SERVER'] as $source => $target) {
                if ($original[$source] === null) {
                    unset($GLOBALS[$target][$key]);
                } else {
                    $GLOBALS[$target][$key] = $original[$source];
                }
            }
            $original['process'] === false ? putenv($key) : putenv($key.'='.$original['process']);
            Env::enablePutenv();
        }
    }
}
