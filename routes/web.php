<?php

use App\Http\Controllers\FileVaultController;
use App\Http\Controllers\ShareLinkController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [FileVaultController::class, 'index'])->name('dashboard');
    Route::view('profile', 'profile')->name('profile');

    Route::prefix('vault')->name('vault.')->group(function () {
        Route::post('/upload',        [FileVaultController::class, 'store'])->name('upload');
        Route::post('/restore',       [FileVaultController::class, 'requestRestoration'])->name('restore');
        Route::post('/freeze',        [FileVaultController::class, 'freeze'])->name('freeze');
        Route::delete('/delete',      [FileVaultController::class, 'destroy'])->name('delete');
        Route::get('/download',       [FileVaultController::class, 'download'])->name('download');
        Route::get('/preview',        [FileVaultController::class, 'preview'])->name('preview');
        Route::post('/share',         [FileVaultController::class, 'createShareLink'])->name('share');
        Route::post('/share/revoke',  [FileVaultController::class, 'revokeShareLink'])->name('share.revoke');
        Route::get('/share/links',    [FileVaultController::class, 'shareLinks'])->name('share.links');
        Route::post('/rename',        [FileVaultController::class, 'rename'])->name('rename');
        Route::get('/poll-status',    [FileVaultController::class, 'pollStatus'])->name('poll.status');
    });
});

Route::get('/share/{token}', [ShareLinkController::class, 'show'])->name('share.show');

require __DIR__.'/auth.php';