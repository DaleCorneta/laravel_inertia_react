<?php

use App\Http\Controllers\ArticleController;
use Illuminate\Support\Facades\Route;

Route::get('/blog', [ArticleController::class, 'publicIndex'])->name('blog.index');
Route::get('/blog/{article}/cover', [ArticleController::class, 'publicCover'])->whereNumber('article')->name('blog.cover');
Route::get('/blog/{article}/inline-media/{media}', [ArticleController::class, 'publicInlineImage'])->whereNumber(['article', 'media'])->name('blog.inline-media');
Route::get('/blog/{article}', [ArticleController::class, 'publicShow'])->whereNumber('article')->name('blog.show');

Route::get('/articles', [ArticleController::class, 'index'])
    ->middleware(['auth', 'verified', 'can:articles.view'])
    ->name('articles.index');
Route::get('/articles/export', [ArticleController::class, 'export'])
    ->middleware(['auth', 'verified', 'can:articles.view'])
    ->name('articles.export');
Route::get('/articles/export/xlsx', [ArticleController::class, 'exportXlsx'])
    ->middleware(['auth', 'verified', 'can:articles.view'])
    ->name('articles.export.xlsx');
Route::get('/articles/export/pdf', [ArticleController::class, 'exportPdf'])
    ->middleware(['auth', 'verified', 'can:articles.view'])
    ->name('articles.export.pdf');
Route::get('/articles/trash', [ArticleController::class, 'trash'])
    ->middleware(['auth', 'verified', 'can:articles.view_trash'])
    ->name('articles.trash');
Route::get('/articles/{article}/history', [ArticleController::class, 'history'])
    ->middleware(['auth', 'verified', 'can:articles.view_history'])
    ->name('articles.history');
Route::get('/articles/{article}/cover', [ArticleController::class, 'privateCover'])
    ->middleware(['auth', 'verified', 'can:articles.update'])
    ->name('articles.cover');
Route::post('/articles/{article}/inline-media', [ArticleController::class, 'storeInlineImage'])
    ->middleware(['auth', 'verified', 'can:articles.update'])
    ->name('articles.inline-media.store');
Route::get('/articles/{article}/inline-media/{media}', [ArticleController::class, 'privateInlineImage'])
    ->middleware(['auth', 'verified'])
    ->whereNumber(['article', 'media'])
    ->name('articles.inline-media');
Route::post('/articles/{article}/restore', [ArticleController::class, 'restore'])
    ->middleware(['auth', 'verified', 'can:articles.restore'])
    ->name('articles.restore');
Route::delete('/articles/{article}/force-delete', [ArticleController::class, 'forceDelete'])
    ->middleware(['auth', 'verified', 'can:articles.force-delete'])
    ->name('articles.force-delete');
Route::get('/articles/create', [ArticleController::class, 'create'])
    ->middleware(['auth', 'verified', 'can:articles.create'])
    ->name('articles.create');
Route::post('/articles', [ArticleController::class, 'store'])
    ->middleware(['auth', 'verified', 'can:articles.create'])
    ->name('articles.store');
Route::get('/articles/{article}/edit', [ArticleController::class, 'edit'])
    ->middleware(['auth', 'verified', 'can:articles.update'])
    ->name('articles.edit');
Route::put('/articles/{article}', [ArticleController::class, 'update'])
    ->middleware(['auth', 'verified', 'can:articles.update'])
    ->name('articles.update');
Route::patch('/articles/{article}/archive', [ArticleController::class, 'archive'])
    ->middleware(['auth', 'verified', 'can:articles.archive'])
    ->name('articles.archive');
Route::patch('/articles/{article}/unarchive', [ArticleController::class, 'unarchive'])
    ->middleware(['auth', 'verified', 'can:articles.archive'])
    ->name('articles.unarchive');
Route::delete('/articles/{article}', [ArticleController::class, 'destroy'])
    ->middleware(['auth', 'verified', 'can:articles.delete'])
    ->name('articles.destroy');
Route::get('/articles/{article}/preview', [ArticleController::class, 'preview'])
    ->middleware(['auth', 'verified', 'can:articles.preview'])
    ->name('articles.preview');
Route::post('/articles/{article}/publish', [ArticleController::class, 'publish'])
    ->middleware(['auth', 'verified', 'can:articles.publish'])
    ->name('articles.publish');
Route::post('/articles/{article}/unpublish', [ArticleController::class, 'unpublish'])
    ->middleware(['auth', 'verified', 'can:articles.unpublish'])
    ->name('articles.unpublish');
