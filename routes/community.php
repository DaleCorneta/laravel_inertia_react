<?php

use App\Http\Controllers\CommunityFeedController;
use Illuminate\Support\Facades\Route;

Route::get('/community', [CommunityFeedController::class, 'index'])
    ->middleware(['auth', 'verified', 'can:community-feed.view'])
    ->name('community.index');

Route::get('/community/trash', [CommunityFeedController::class, 'trash'])
    ->middleware(['auth', 'verified', 'can:community-feed.view_trash'])
    ->name('community.trash');

Route::get('/community/{post}/media/{media}', [CommunityFeedController::class, 'media'])
    ->middleware(['auth', 'verified', 'can:community-feed.view'])
    ->name('community.media');

Route::delete('/community/{post}/media/{media}', [CommunityFeedController::class, 'deleteMedia'])
    ->middleware(['auth', 'verified', 'can:community-feed.update'])
    ->name('community.media.destroy');

Route::post('/community/{post}/media', [CommunityFeedController::class, 'storeMedia'])
    ->middleware(['auth', 'verified', 'can:community-feed.update'])
    ->name('community.media.store');

Route::post('/community/{post}/like', [CommunityFeedController::class, 'like'])
    ->middleware(['auth', 'verified', 'can:community-feed.like'])
    ->name('community.like');

Route::delete('/community/{post}/like', [CommunityFeedController::class, 'unlike'])
    ->middleware(['auth', 'verified', 'can:community-feed.like'])
    ->name('community.unlike');

Route::patch('/community/{post}/moderation', [CommunityFeedController::class, 'moderatePost'])
    ->middleware(['auth', 'verified', 'can:community-feed.view', 'can:community-feed.moderate_posts'])
    ->name('community.moderate');

Route::get('/community/{post}/comments', [CommunityFeedController::class, 'comments'])
    ->middleware(['auth', 'verified', 'can:community-feed.view'])
    ->name('community.comments');

Route::post('/community/{post}/comments', [CommunityFeedController::class, 'storeComment'])
    ->middleware(['auth', 'verified', 'can:community-feed.comment'])
    ->name('community.comments.store');

Route::post('/community/{post}/comments/{comment}/replies', [CommunityFeedController::class, 'storeReply'])
    ->middleware(['auth', 'verified', 'can:community-feed.reply'])
    ->name('community.comments.replies.store');

Route::patch('/community/{post}/comments/{comment}/moderation', [CommunityFeedController::class, 'moderateComment'])
    ->middleware(['auth', 'verified', 'can:community-feed.view', 'can:community-feed.moderate_comments'])
    ->name('community.comments.moderate');

Route::put('/community/{post}/comments/{comment}', [CommunityFeedController::class, 'updateComment'])
    ->middleware(['auth', 'verified', 'can:community-feed.update_comment'])
    ->name('community.comments.update');

Route::delete('/community/{post}/comments/{comment}', [CommunityFeedController::class, 'destroyComment'])
    ->middleware(['auth', 'verified', 'can:community-feed.delete_comment'])
    ->name('community.comments.destroy');

Route::post('/community', [CommunityFeedController::class, 'store'])
    ->middleware(['auth', 'verified', 'can:community-feed.create'])
    ->name('community.store');

Route::put('/community/{post}', [CommunityFeedController::class, 'update'])
    ->middleware(['auth', 'verified', 'can:community-feed.update'])
    ->name('community.update');

Route::delete('/community/{post}', [CommunityFeedController::class, 'destroy'])
    ->middleware(['auth', 'verified', 'can:community-feed.delete'])
    ->name('community.destroy');

Route::patch('/community/{post}/archive', [CommunityFeedController::class, 'archive'])
    ->middleware(['auth', 'verified', 'can:community-feed.archive'])
    ->name('community.archive');

Route::patch('/community/{post}/unarchive', [CommunityFeedController::class, 'unarchive'])
    ->middleware(['auth', 'verified', 'can:community-feed.archive'])
    ->name('community.unarchive');

Route::post('/community/{post}/restore', [CommunityFeedController::class, 'restore'])
    ->middleware(['auth', 'verified', 'can:community-feed.restore'])
    ->name('community.restore');

Route::delete('/community/{post}/force-delete', [CommunityFeedController::class, 'forceDelete'])
    ->middleware(['auth', 'verified', 'can:community-feed.force-delete'])
    ->name('community.force-delete');
