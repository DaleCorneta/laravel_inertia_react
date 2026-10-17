<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileMfaController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/profile', [ProfileController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('profile.show');
Route::put('/profile', [ProfileController::class, 'update'])
    ->middleware(['auth', 'verified', 'throttle:5,1'])
    ->name('profile.update');
Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
    ->middleware(['auth', 'verified', 'throttle:5,1'])
    ->name('profile.password.update');

Route::prefix('/profile/mfa')->name('profile.mfa.')->middleware(['auth', 'verified', 'mfa.enabled', 'throttle:mfa-management'])->controller(ProfileMfaController::class)->group(function () {
    Route::get('/', 'show')->name('show');
    Route::post('/enrollment', 'begin')->name('begin');
    Route::post('/confirm', 'confirm')->name('confirm');
    Route::delete('/', 'disable')->name('disable');
    Route::post('/recovery-codes', 'regenerateRecoveryCodes')->name('recovery-codes.regenerate');
});

Route::get('/settings', function () {
    return Inertia::render('Settings/Index');
})->middleware(['auth', 'verified'])->name('settings.index');

Route::get('/roles', [RoleController::class, 'index'])
    ->middleware(['auth', 'verified', 'can:roles.view'])
    ->name('roles.index');
Route::get('/roles/batches', [RoleController::class, 'batch'])
    ->middleware(['auth', 'verified', 'can:roles.view'])
    ->name('roles.batches');
Route::get('/roles/export', [RoleController::class, 'export'])
    ->middleware(['auth', 'verified', 'can:roles.view'])
    ->name('roles.export');
Route::get('/roles/export/xlsx', [RoleController::class, 'exportXlsx'])
    ->middleware(['auth', 'verified', 'can:roles.view'])
    ->name('roles.export.xlsx');
Route::get('/roles/export/pdf', [RoleController::class, 'exportPdf'])
    ->middleware(['auth', 'verified', 'can:roles.view'])
    ->name('roles.export.pdf');
Route::get('/roles/create', [RoleController::class, 'create'])
    ->middleware(['auth', 'verified', 'can:roles.manage'])
    ->name('roles.create');
Route::post('/roles', [RoleController::class, 'store'])
    ->middleware(['auth', 'verified', 'can:roles.manage'])
    ->name('roles.store');
Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])
    ->middleware(['auth', 'verified', 'can:roles.manage'])
    ->name('roles.edit');
Route::put('/roles/{role}', [RoleController::class, 'update'])
    ->middleware(['auth', 'verified', 'can:roles.manage'])
    ->name('roles.update');
Route::get('/permissions', [PermissionController::class, 'index'])
    ->middleware(['auth', 'verified', 'can:permissions.view'])
    ->name('permissions.index');
Route::get('/activity-logs', [ActivityLogController::class, 'index'])
    ->middleware(['auth', 'verified', 'can:activity-logs.view'])
    ->name('activity-logs.index');
Route::get('/activity-logs/export', [ActivityLogController::class, 'export'])
    ->middleware(['auth', 'verified', 'can:activity-logs.view'])
    ->name('activity-logs.export');
Route::get('/activity-logs/export/xlsx', [ActivityLogController::class, 'exportXlsx'])
    ->middleware(['auth', 'verified', 'can:activity-logs.view'])
    ->name('activity-logs.export.xlsx');
Route::get('/activity-logs/export/pdf', [ActivityLogController::class, 'exportPdf'])
    ->middleware(['auth', 'verified', 'can:activity-logs.view'])
    ->name('activity-logs.export.pdf');

Route::get('/users', [UserController::class, 'index'])
    ->middleware(['auth', 'verified', 'can:users.view'])
    ->name('users.index');
Route::get('/users/create', [UserController::class, 'create'])
    ->middleware(['auth', 'verified', 'can:users.create'])
    ->name('users.create');
Route::post('/users', [UserController::class, 'store'])
    ->middleware(['auth', 'verified', 'can:users.create'])
    ->name('users.store');
Route::get('/users/batches', [UserController::class, 'batch'])
    ->middleware(['auth', 'verified', 'can:users.view'])
    ->name('users.batches');
Route::get('/users/export', [UserController::class, 'export'])
    ->middleware(['auth', 'verified', 'can:users.view'])
    ->name('users.export');
Route::get('/users/export/xlsx', [UserController::class, 'exportXlsx'])
    ->middleware(['auth', 'verified', 'can:users.view'])
    ->name('users.export.xlsx');
Route::get('/users/export/pdf', [UserController::class, 'exportPdf'])
    ->middleware(['auth', 'verified', 'can:users.view'])
    ->name('users.export.pdf');
Route::get('/users/{user}/edit', [UserController::class, 'edit'])
    ->middleware(['auth', 'verified', 'can:users.update'])
    ->name('users.edit');
Route::put('/users/{user}', [UserController::class, 'update'])
    ->middleware(['auth', 'verified', 'can:users.update'])
    ->name('users.update');
Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])
    ->middleware(['auth', 'verified', 'can:users.update'])
    ->name('users.status');
Route::put('/users/{user}/roles', [UserRoleController::class, 'update'])
    ->middleware(['auth', 'verified', 'can:roles.manage'])
    ->name('users.roles.update');
Route::get('/users/{user}', [UserController::class, 'show'])
    ->middleware(['auth', 'verified', 'can:users.view'])
    ->name('users.show');

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:verification'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:verification')
        ->name('verification.send');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
    Route::post('/login/mfa', [AuthenticatedSessionController::class, 'challenge'])
        ->middleware('throttle:login')
        ->name('login.mfa');
    Route::delete('/login/mfa', [AuthenticatedSessionController::class, 'cancelChallenge'])
        ->name('login.mfa.cancel');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.store');

    if (config('auth.social_login.enabled') && config('auth.social_login.google_enabled')) {
        Route::get('/auth/google/redirect', [GoogleOAuthController::class, 'redirect'])
            ->name('oauth.google.redirect');
        Route::get('/auth/google/callback', [GoogleOAuthController::class, 'callback'])
            ->name('oauth.google.callback');
    }
});

if (config('auth.social_login.enabled') && config('auth.social_login.google_enabled')) {
    Route::post('/profile/social/google/link', [GoogleOAuthController::class, 'link'])
        ->middleware(['auth', 'verified', 'throttle:5,1'])
        ->name('profile.social.google.link');
    Route::get('/profile/social/google/callback', [GoogleOAuthController::class, 'linkCallback'])
        ->middleware(['auth', 'verified'])
        ->name('profile.social.google.callback');
}

Route::delete('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

if (config('demo.modules_enabled') && config('demo.contact_directory_enabled')) {
    require __DIR__.'/contacts.php';
}

if (config('demo.modules_enabled') && config('demo.blog_enabled')) {
    require __DIR__.'/blog.php';
}

if (config('demo.modules_enabled') && config('demo.community_feed_enabled')) {
    require __DIR__.'/community.php';
}
