<?php

namespace Tests\Feature;

use Illuminate\Support\Env;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DemoModuleRouteTest extends TestCase
{
    public function test_contact_routes_are_registered_only_when_global_and_contact_flags_are_enabled(): void
    {
        $keys = ['DEMO_MODULES_ENABLED', 'DEMO_CONTACT_DIRECTORY_ENABLED'];
        $original = [];

        foreach ($keys as $key) {
            $original[$key] = [
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'process' => getenv($key),
            ];
        }

        try {
            foreach ([[false, false], [false, true], [true, false], [true, true]] as [$modulesEnabled, $contactsEnabled]) {
                $_ENV['DEMO_MODULES_ENABLED'] = $_SERVER['DEMO_MODULES_ENABLED'] = $modulesEnabled ? 'true' : 'false';
                $_ENV['DEMO_CONTACT_DIRECTORY_ENABLED'] = $_SERVER['DEMO_CONTACT_DIRECTORY_ENABLED'] = $contactsEnabled ? 'true' : 'false';
                putenv('DEMO_MODULES_ENABLED='.$_ENV['DEMO_MODULES_ENABLED']);
                putenv('DEMO_CONTACT_DIRECTORY_ENABLED='.$_ENV['DEMO_CONTACT_DIRECTORY_ENABLED']);
                Env::enablePutenv();

                $this->refreshApplication();

                $expected = $modulesEnabled && $contactsEnabled;
                $this->assertSame($modulesEnabled, (bool) config('demo.modules_enabled'));
                $this->assertSame($contactsEnabled, (bool) config('demo.contact_directory_enabled'));
                $this->assertSame($expected, Route::has('contacts.index'));
                $this->assertSame($expected, Route::has('contacts.batches'));
                $this->assertSame($expected, Route::has('contacts.export'));
                $this->assertSame($expected, Route::has('contacts.export.xlsx'));
                $this->assertSame($expected, Route::has('contacts.export.pdf'));
                $this->assertSame($expected, Route::has('contacts.create'));
                $this->assertSame($expected, Route::has('contacts.store'));
                $this->assertSame($expected, Route::has('contacts.edit'));
                $this->assertSame($expected, Route::has('contacts.update'));
                $this->assertSame($expected, Route::has('contacts.attachments.download'));
                $this->assertSame($expected, Route::has('contacts.attachments.delete'));
                $this->assertSame($expected, Route::has('contacts.archive'));
                $this->assertSame($expected, Route::has('contacts.unarchive'));
                $this->assertSame($expected, Route::has('contacts.destroy'));
                $this->assertSame($expected, Route::has('contacts.restore'));
                $this->assertSame($expected, Route::has('contacts.force-destroy'));
                $this->assertTrue(Route::has('dashboard'));

                if ($expected) {
                    $this->assertContains('auth', Route::getRoutes()->getByName('contacts.index')->gatherMiddleware());
                    $this->assertContains('verified', Route::getRoutes()->getByName('contacts.index')->gatherMiddleware());
                    $this->assertContains('can:contacts.view', Route::getRoutes()->getByName('contacts.index')->gatherMiddleware());
                    foreach (['contacts.batches', 'contacts.export', 'contacts.export.xlsx', 'contacts.export.pdf'] as $name) {
                        $this->assertContains('can:contacts.view', Route::getRoutes()->getByName($name)->gatherMiddleware());
                    }
                    $this->assertContains('can:contacts.create', Route::getRoutes()->getByName('contacts.create')->gatherMiddleware());
                    $this->assertContains('can:contacts.update', Route::getRoutes()->getByName('contacts.edit')->gatherMiddleware());
                    $this->assertContains('can:contacts.update', Route::getRoutes()->getByName('contacts.update')->gatherMiddleware());
                    $this->assertContains('can:contacts.update', Route::getRoutes()->getByName('contacts.attachments.download')->gatherMiddleware());
                    $this->assertContains('can:contacts.update', Route::getRoutes()->getByName('contacts.attachments.delete')->gatherMiddleware());
                    $this->assertContains('can:contacts.archive', Route::getRoutes()->getByName('contacts.archive')->gatherMiddleware());
                    $this->assertContains('can:contacts.delete', Route::getRoutes()->getByName('contacts.destroy')->gatherMiddleware());
                    $this->assertContains('can:contacts.restore', Route::getRoutes()->getByName('contacts.restore')->gatherMiddleware());
                    $this->assertContains('can:contacts.force-delete', Route::getRoutes()->getByName('contacts.force-destroy')->gatherMiddleware());
                } else {
                    $this->get('/contacts')->assertNotFound();
                    $this->get('/contacts/batches')->assertNotFound();
                    $this->get('/contacts/export')->assertNotFound();
                    $this->get('/contacts/export/xlsx')->assertNotFound();
                    $this->get('/contacts/export/pdf')->assertNotFound();
                    $this->get('/contacts/create')->assertNotFound();
                    $this->post('/contacts')->assertNotFound();
                    $this->get('/contacts/1/edit')->assertNotFound();
                    $this->put('/contacts/1')->assertNotFound();
                    $this->get('/contacts/1/attachments/1')->assertNotFound();
                    $this->delete('/contacts/1/attachments/1')->assertNotFound();
                    $this->patch('/contacts/1/archive')->assertNotFound();
                    $this->patch('/contacts/1/unarchive')->assertNotFound();
                    $this->delete('/contacts/1')->assertNotFound();
                    $this->post('/contacts/1/restore')->assertNotFound();
                    $this->delete('/contacts/1/permanent')->assertNotFound();
                }
            }
        } finally {
            foreach ($original as $key => $values) {
                foreach (['env' => '_ENV', 'server' => '_SERVER'] as $source => $target) {
                    if ($values[$source] === null) {
                        unset($GLOBALS[$target][$key]);
                    } else {
                        $GLOBALS[$target][$key] = $values[$source];
                    }
                }

                if ($values['process'] === false) {
                    putenv($key);
                } else {
                    putenv($key.'='.$values['process']);
                }
            }

            Env::enablePutenv();
            $this->refreshApplication();
        }
    }

    public function test_blog_route_is_registered_only_when_global_and_blog_flags_are_enabled(): void
    {
        $keys = ['DEMO_MODULES_ENABLED', 'DEMO_BLOG_ENABLED'];
        $original = [];

        foreach ($keys as $key) {
            $original[$key] = [
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'process' => getenv($key),
            ];
        }

        try {
            foreach ([[false, false], [false, true], [true, false], [true, true]] as [$modulesEnabled, $blogEnabled]) {
                $_ENV['DEMO_MODULES_ENABLED'] = $_SERVER['DEMO_MODULES_ENABLED'] = $modulesEnabled ? 'true' : 'false';
                $_ENV['DEMO_BLOG_ENABLED'] = $_SERVER['DEMO_BLOG_ENABLED'] = $blogEnabled ? 'true' : 'false';
                putenv('DEMO_MODULES_ENABLED='.$_ENV['DEMO_MODULES_ENABLED']);
                putenv('DEMO_BLOG_ENABLED='.$_ENV['DEMO_BLOG_ENABLED']);
                Env::enablePutenv();

                $this->refreshApplication();

                $expected = $modulesEnabled && $blogEnabled;
                $this->assertSame($modulesEnabled, (bool) config('demo.modules_enabled'));
                $this->assertSame($blogEnabled, (bool) config('demo.blog_enabled'));
                $this->assertSame($expected, Route::has('articles.index'));
                $this->assertSame($expected, Route::has('articles.publish'));
                $this->assertSame($expected, Route::has('articles.unpublish'));
                $this->assertSame($expected, Route::has('articles.trash'));
                $this->assertSame($expected, Route::has('articles.archive'));
                $this->assertSame($expected, Route::has('articles.unarchive'));
                $this->assertSame($expected, Route::has('articles.destroy'));
                $this->assertSame($expected, Route::has('articles.restore'));
                $this->assertSame($expected, Route::has('articles.force-delete'));
                $this->assertSame($expected, Route::has('articles.history'));
                $this->assertSame($expected, Route::has('blog.index'));
                $this->assertSame($expected, Route::has('blog.show'));
                $this->assertSame($expected, Route::has('blog.cover'));
                $this->assertSame($expected, Route::has('articles.cover'));
                $this->assertTrue(Route::has('dashboard'));

                if ($expected) {
                    $this->assertContains('auth', Route::getRoutes()->getByName('articles.index')->gatherMiddleware());
                    $this->assertContains('verified', Route::getRoutes()->getByName('articles.index')->gatherMiddleware());
                    $this->assertContains('can:articles.view', Route::getRoutes()->getByName('articles.index')->gatherMiddleware());
                    $this->assertContains('can:articles.publish', Route::getRoutes()->getByName('articles.publish')->gatherMiddleware());
                    $this->assertContains('can:articles.unpublish', Route::getRoutes()->getByName('articles.unpublish')->gatherMiddleware());
                    $this->assertContains('can:articles.archive', Route::getRoutes()->getByName('articles.archive')->gatherMiddleware());
                    $this->assertContains('can:articles.delete', Route::getRoutes()->getByName('articles.destroy')->gatherMiddleware());
                    $this->assertContains('can:articles.view_trash', Route::getRoutes()->getByName('articles.trash')->gatherMiddleware());
                    $this->assertContains('can:articles.restore', Route::getRoutes()->getByName('articles.restore')->gatherMiddleware());
                    $this->assertContains('can:articles.force-delete', Route::getRoutes()->getByName('articles.force-delete')->gatherMiddleware());
                    $this->assertContains('can:articles.view_history', Route::getRoutes()->getByName('articles.history')->gatherMiddleware());
                    $this->assertContains('can:articles.update', Route::getRoutes()->getByName('articles.cover')->gatherMiddleware());
                } else {
                    $this->get('/articles')->assertNotFound();
                    $this->get('/articles/trash')->assertNotFound();
                    $this->patch('/articles/1/archive')->assertNotFound();
                    $this->patch('/articles/1/unarchive')->assertNotFound();
                    $this->delete('/articles/1')->assertNotFound();
                    $this->post('/articles/1/restore')->assertNotFound();
                    $this->delete('/articles/1/force-delete')->assertNotFound();
                    $this->get('/articles/1/history')->assertNotFound();
                    $this->get('/blog')->assertNotFound();
                    $this->get('/blog/1/cover')->assertNotFound();
                    $this->get('/articles/1/cover')->assertNotFound();
                    $this->post('/articles/1/publish')->assertNotFound();
                    $this->post('/articles/1/unpublish')->assertNotFound();
                }
            }
        } finally {
            foreach ($original as $key => $values) {
                foreach (['env' => '_ENV', 'server' => '_SERVER'] as $source => $target) {
                    if ($values[$source] === null) {
                        unset($GLOBALS[$target][$key]);
                    } else {
                        $GLOBALS[$target][$key] = $values[$source];
                    }
                }

                if ($values['process'] === false) {
                    putenv($key);
                } else {
                    putenv($key.'='.$values['process']);
                }
            }

            Env::enablePutenv();
            $this->refreshApplication();
        }
    }

    public function test_community_route_is_registered_only_when_global_and_community_flags_are_enabled(): void
    {
        $keys = ['DEMO_MODULES_ENABLED', 'DEMO_COMMUNITY_FEED_ENABLED'];
        $original = [];

        foreach ($keys as $key) {
            $original[$key] = ['env' => $_ENV[$key] ?? null, 'server' => $_SERVER[$key] ?? null, 'process' => getenv($key)];
        }

        try {
            foreach ([[false, false], [false, true], [true, false], [true, true]] as [$modulesEnabled, $feedEnabled]) {
                $_ENV['DEMO_MODULES_ENABLED'] = $_SERVER['DEMO_MODULES_ENABLED'] = $modulesEnabled ? 'true' : 'false';
                $_ENV['DEMO_COMMUNITY_FEED_ENABLED'] = $_SERVER['DEMO_COMMUNITY_FEED_ENABLED'] = $feedEnabled ? 'true' : 'false';
                putenv('DEMO_MODULES_ENABLED='.$_ENV['DEMO_MODULES_ENABLED']);
                putenv('DEMO_COMMUNITY_FEED_ENABLED='.$_ENV['DEMO_COMMUNITY_FEED_ENABLED']);
                Env::enablePutenv();
                $this->refreshApplication();

                $expected = $modulesEnabled && $feedEnabled;
                $this->assertSame($expected, Route::has('community.index'));
                $this->assertSame($expected, Route::has('community.media'));
                $this->assertSame($expected, Route::has('community.media.destroy'));
                $this->assertSame($expected, Route::has('community.media.store'));
                $this->assertSame($expected, Route::has('community.store'));
                $this->assertSame($expected, Route::has('community.update'));
                $this->assertSame($expected, Route::has('community.destroy'));
                $this->assertSame($expected, Route::has('community.trash'));
                $this->assertSame($expected, Route::has('community.archive'));
                $this->assertSame($expected, Route::has('community.unarchive'));
                $this->assertSame($expected, Route::has('community.restore'));
                $this->assertSame($expected, Route::has('community.force-delete'));
                $this->assertSame($expected, Route::has('community.comments'));
                $this->assertSame($expected, Route::has('community.comments.store'));
                $this->assertSame($expected, Route::has('community.comments.update'));
                $this->assertSame($expected, Route::has('community.comments.destroy'));
                $this->assertSame($expected, Route::has('community.comments.replies.store'));
                $this->assertSame($expected, Route::has('community.like'));
                $this->assertSame($expected, Route::has('community.unlike'));
                $this->assertSame($expected, Route::has('community.moderate'));
                $this->assertSame($expected, Route::has('community.comments.moderate'));

                if ($expected) {
                    $middleware = Route::getRoutes()->getByName('community.index')->gatherMiddleware();
                    $this->assertContains('auth', $middleware);
                    $this->assertContains('verified', $middleware);
                    $this->assertContains('can:community-feed.view', $middleware);
                    $this->assertContains('can:community-feed.create', Route::getRoutes()->getByName('community.store')->gatherMiddleware());
                    $this->assertContains('can:community-feed.update', Route::getRoutes()->getByName('community.update')->gatherMiddleware());
                    $this->assertContains('can:community-feed.update', Route::getRoutes()->getByName('community.media.destroy')->gatherMiddleware());
                    $this->assertContains('can:community-feed.update', Route::getRoutes()->getByName('community.media.store')->gatherMiddleware());
                    $this->assertContains('can:community-feed.delete', Route::getRoutes()->getByName('community.destroy')->gatherMiddleware());
                    $this->assertContains('can:community-feed.view_trash', Route::getRoutes()->getByName('community.trash')->gatherMiddleware());
                    $this->assertContains('can:community-feed.archive', Route::getRoutes()->getByName('community.archive')->gatherMiddleware());
                    $this->assertContains('can:community-feed.restore', Route::getRoutes()->getByName('community.restore')->gatherMiddleware());
                    $this->assertContains('can:community-feed.force-delete', Route::getRoutes()->getByName('community.force-delete')->gatherMiddleware());
                    $this->assertContains('can:community-feed.view', Route::getRoutes()->getByName('community.comments')->gatherMiddleware());
                    $this->assertContains('can:community-feed.comment', Route::getRoutes()->getByName('community.comments.store')->gatherMiddleware());
                    $this->assertContains('can:community-feed.update_comment', Route::getRoutes()->getByName('community.comments.update')->gatherMiddleware());
                    $this->assertContains('can:community-feed.delete_comment', Route::getRoutes()->getByName('community.comments.destroy')->gatherMiddleware());
                    $this->assertContains('can:community-feed.reply', Route::getRoutes()->getByName('community.comments.replies.store')->gatherMiddleware());
                    $this->assertContains('can:community-feed.like', Route::getRoutes()->getByName('community.like')->gatherMiddleware());
                    $this->assertContains('can:community-feed.like', Route::getRoutes()->getByName('community.unlike')->gatherMiddleware());
                    $this->assertContains('can:community-feed.moderate_posts', Route::getRoutes()->getByName('community.moderate')->gatherMiddleware());
                    $this->assertContains('can:community-feed.moderate_comments', Route::getRoutes()->getByName('community.comments.moderate')->gatherMiddleware());
                    $this->assertContains('can:community-feed.view', Route::getRoutes()->getByName('community.moderate')->gatherMiddleware());
                    $this->assertContains('can:community-feed.view', Route::getRoutes()->getByName('community.comments.moderate')->gatherMiddleware());
                } else {
                    $this->get('/community')->assertNotFound();
                    $this->post('/community')->assertNotFound();
                    $this->put('/community/1')->assertNotFound();
                    $this->delete('/community/1')->assertNotFound();
                    $this->delete('/community/1/media/1')->assertNotFound();
                    $this->post('/community/1/media')->assertNotFound();
                    $this->get('/community/trash')->assertNotFound();
                    $this->patch('/community/1/archive')->assertNotFound();
                    $this->post('/community/1/restore')->assertNotFound();
                    $this->delete('/community/1/force-delete')->assertNotFound();
                    $this->get('/community/1/comments')->assertNotFound();
                    $this->post('/community/1/comments')->assertNotFound();
                    $this->post('/community/1/comments/1/replies')->assertNotFound();
                    $this->post('/community/1/like')->assertNotFound();
                    $this->delete('/community/1/like')->assertNotFound();
                    $this->patch('/community/1/moderation')->assertNotFound();
                    $this->patch('/community/1/comments/1/moderation')->assertNotFound();
                }
            }
        } finally {
            foreach ($original as $key => $values) {
                foreach (['env' => '_ENV', 'server' => '_SERVER'] as $source => $target) {
                    if ($values[$source] === null) {
                        unset($GLOBALS[$target][$key]);
                    } else {
                        $GLOBALS[$target][$key] = $values[$source];
                    }
                }
                $values['process'] === false ? putenv($key) : putenv($key.'='.$values['process']);
            }
            Env::enablePutenv();
            $this->refreshApplication();
        }
    }
}
