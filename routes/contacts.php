<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::get('/contacts', [ContactController::class, 'index'])
    ->middleware(['auth', 'verified', 'can:contacts.view'])
    ->name('contacts.index');
Route::get('/contacts/batches', [ContactController::class, 'batch'])
    ->middleware(['auth', 'verified', 'can:contacts.view'])
    ->name('contacts.batches');
Route::get('/contacts/export', [ContactController::class, 'export'])
    ->middleware(['auth', 'verified', 'can:contacts.view'])
    ->name('contacts.export');
Route::get('/contacts/export/xlsx', [ContactController::class, 'exportXlsx'])
    ->middleware(['auth', 'verified', 'can:contacts.view'])
    ->name('contacts.export.xlsx');
Route::get('/contacts/export/pdf', [ContactController::class, 'exportPdf'])
    ->middleware(['auth', 'verified', 'can:contacts.view'])
    ->name('contacts.export.pdf');
Route::get('/contacts/create', [ContactController::class, 'create'])
    ->middleware(['auth', 'verified', 'can:contacts.create'])
    ->name('contacts.create');
Route::post('/contacts', [ContactController::class, 'store'])
    ->middleware(['auth', 'verified', 'can:contacts.create'])
    ->name('contacts.store');
Route::get('/contacts/{contact}/edit', [ContactController::class, 'edit'])
    ->middleware(['auth', 'verified', 'can:contacts.update'])
    ->name('contacts.edit');
Route::put('/contacts/{contact}', [ContactController::class, 'update'])
    ->middleware(['auth', 'verified', 'can:contacts.update'])
    ->name('contacts.update');
Route::get('/contacts/{contact}/attachments/{attachment}', [ContactController::class, 'downloadAttachment'])
    ->middleware(['auth', 'verified', 'can:contacts.update'])
    ->name('contacts.attachments.download');
Route::delete('/contacts/{contact}/attachments/{attachment}', [ContactController::class, 'deleteAttachment'])
    ->middleware(['auth', 'verified', 'can:contacts.update'])
    ->name('contacts.attachments.delete');
Route::patch('/contacts/{contact}/archive', [ContactController::class, 'archive'])
    ->middleware(['auth', 'verified', 'can:contacts.archive'])
    ->name('contacts.archive');
Route::patch('/contacts/{contact}/unarchive', [ContactController::class, 'unarchive'])
    ->middleware(['auth', 'verified', 'can:contacts.archive'])
    ->name('contacts.unarchive');
Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])
    ->middleware(['auth', 'verified', 'can:contacts.delete'])
    ->name('contacts.destroy');
Route::post('/contacts/{contact}/restore', [ContactController::class, 'restore'])
    ->withTrashed()
    ->middleware(['auth', 'verified', 'can:contacts.restore'])
    ->name('contacts.restore');
Route::delete('/contacts/{contact}/permanent', [ContactController::class, 'forceDestroy'])
    ->withTrashed()
    ->middleware(['auth', 'verified', 'can:contacts.force-delete'])
    ->name('contacts.force-destroy');
