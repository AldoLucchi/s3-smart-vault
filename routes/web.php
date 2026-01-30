<?php

use App\Http\Controllers\FileVaultController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');
Route::get('dashboard', [FileVaultController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    
    Route::get('dashboard', [FileVaultController::class, 'index'])->name('dashboard');
    Route::view('profile', 'profile')->name('profile');

    Route::prefix('vault')->name('vault.')->group(function () {
        Route::post('/upload', [FileVaultController::class, 'store'])->name('upload');
        Route::post('/restore', [FileVaultController::class, 'requestRestoration'])->name('restore');
        Route::post('/freeze', [FileVaultController::class, 'freeze'])->name('freeze');
        Route::delete('/delete', [FileVaultController::class, 'destroy'])->name('delete');
        Route::get('/download', [FileVaultController::class, 'download'])->name('download');
    });
});

require __DIR__.'/auth.php';