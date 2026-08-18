<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ContractorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LineController;
use App\Http\Controllers\MemoAttachmentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceDeficiencyMemoController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'enabled', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'enabled'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    Route::get('/users/{role}/create', [UserController::class, 'create'])->name('users.create');
    Route::get('/users/{role}', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/status', [UserController::class, 'status'])->name('users.status');

    Route::post('/sdm-memos/submit', [ServiceDeficiencyMemoController::class, 'storeAndSubmit'])->name('sdm-memos.store-and-submit');
    Route::resource('sdm-memos', ServiceDeficiencyMemoController::class)->except(['update', 'destroy']);
    Route::post('/sdm-memos/{sdm_memo}', [ServiceDeficiencyMemoController::class, 'update'])->name('sdm-memos.update');
    Route::post('/sdm-memos/{sdm_memo}/submit', [ServiceDeficiencyMemoController::class, 'submit'])->name('sdm-memos.submit');
    Route::get('/sdm-memos/{sdm_memo}/attachments/{attachment}', [MemoAttachmentController::class, 'download'])->name('sdm-memos.attachments.download');
    Route::delete('/sdm-memos/{sdm_memo}/attachments/{attachment}', [MemoAttachmentController::class, 'destroy'])->name('sdm-memos.attachments.destroy');

    Route::resource('lines', LineController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('stations', StationController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('contractors', ContractorController::class)->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/auth.php';
