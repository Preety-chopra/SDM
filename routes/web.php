<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ContractorController;
use App\Http\Controllers\DashboardChartMemosController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\LineController;
use App\Http\Controllers\LineManagerDashboardMemosController;
use App\Http\Controllers\MemoApprovalAttachmentController;
use App\Http\Controllers\MemoAttachmentController;
use App\Http\Controllers\MemoPenaltyLifecycleController;
use App\Http\Controllers\MemoPrivateDocumentController;
use App\Http\Controllers\MemoWorkflowController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServiceContractController;
use App\Http\Controllers\ServiceDeficiencyMemoController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\StationHierarchyController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'enabled', 'verified'])
    ->name('dashboard');

Route::get('/dashboard/line-sdms', LineManagerDashboardMemosController::class)
    ->middleware(['auth', 'enabled', 'verified'])
    ->name('dashboard.line-sdms');

Route::get('/dashboard/chart-sdms', DashboardChartMemosController::class)
    ->middleware(['auth', 'enabled', 'verified'])
    ->name('dashboard.chart-sdms');

Route::middleware(['auth', 'enabled'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    Route::resource('roles', RoleController::class)->except('show');
    Route::resource('designations', DesignationController::class)->only(['index', 'store', 'destroy']);

    Route::get('/sdm-hierarchy', [StationHierarchyController::class, 'index'])->name('hierarchies.index');
    Route::put('/sdm-hierarchy', [StationHierarchyController::class, 'update'])->name('hierarchies.update');

    Route::get('/users/create', [UserController::class, 'createAny'])->name('users.create_any');
    Route::get('/users/{role}/create', [UserController::class, 'create'])->name('users.create');
    Route::get('/users/{role}', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/status', [UserController::class, 'status'])->name('users.status');

    Route::post('/sdm-memos/submit', [ServiceDeficiencyMemoController::class, 'storeAndSubmit'])->name('sdm-memos.store-and-submit');
    Route::post('/sdm-memos/bulk-forward', [MemoWorkflowController::class, 'bulkForward'])->name('sdm-memos.bulk-forward');
    Route::post('/sdm-memos/bulk-cmc-action', [MemoWorkflowController::class, 'bulkCmcAction'])->name('sdm-memos.bulk-cmc-action');
    Route::get('/sdm-memos/drafts', [ServiceDeficiencyMemoController::class, 'drafts'])->name('sdm-memos.drafts');
    Route::resource('sdm-memos', ServiceDeficiencyMemoController::class)->except('update');
    Route::post('/sdm-memos/{sdm_memo}', [ServiceDeficiencyMemoController::class, 'update'])->name('sdm-memos.update');
    Route::patch('/sdm-memos/{sdm_memo}/station-manager', [ServiceDeficiencyMemoController::class, 'updateStationManager'])->name('sdm-memos.station-manager.update');
    Route::patch('/sdm-memos/{sdm_memo}/line-manager', [ServiceDeficiencyMemoController::class, 'updateLineManager'])->name('sdm-memos.line-manager.update');
    Route::post('/sdm-memos/{sdm_memo}/submit', [ServiceDeficiencyMemoController::class, 'submit'])->name('sdm-memos.submit');
    Route::post('/sdm-memos/{sdm_memo}/workflow', [MemoWorkflowController::class, 'store'])->name('sdm-memos.workflow.store');
    Route::patch('/sdm-memos/{sdm_memo}/cmc-team-lead', [MemoWorkflowController::class, 'reassignCmcTeamLead'])->name('sdm-memos.cmc-team-lead.update');
    Route::patch('/sdm-memos/{sdm_memo}/cmc-assignment', [MemoWorkflowController::class, 'reassign'])->name('sdm-memos.cmc-assignment.update');
    Route::post('/sdm-memos/{sdm_memo}/penalty-letter', [MemoPenaltyLifecycleController::class, 'letter'])->name('sdm-memos.penalty-letter.store');
    Route::post('/sdm-memos/{sdm_memo}/objection', [MemoPenaltyLifecycleController::class, 'objection'])->name('sdm-memos.objection.store');
    Route::post('/sdm-memos/{sdm_memo}/no-objection', [MemoPenaltyLifecycleController::class, 'noObjection'])->name('sdm-memos.no-objection.store');
    Route::post('/sdm-memos/{sdm_memo}/deduction', [MemoPenaltyLifecycleController::class, 'deduction'])->name('sdm-memos.deduction.store');
    Route::get('/sdm-memos/{sdm_memo}/staff-signature', [MemoPrivateDocumentController::class, 'acknowledgement'])->name('sdm-memos.staff-signature.download');
    Route::get('/sdm-memos/{sdm_memo}/penalty-letters/{letter}', [MemoPrivateDocumentController::class, 'letter'])->name('sdm-memos.penalty-letter.download');
    Route::get('/sdm-memos/{sdm_memo}/objections/{objection}', [MemoPrivateDocumentController::class, 'objection'])->name('sdm-memos.objection.download');
    Route::get('/sdm-memos/{sdm_memo}/workflow-evidence/{attachment}', [MemoApprovalAttachmentController::class, 'download'])->name('sdm-memos.workflow-evidence.download');
    Route::get('/sdm-memos/{sdm_memo}/attachments/{attachment}', [MemoAttachmentController::class, 'download'])->name('sdm-memos.attachments.download');
    Route::delete('/sdm-memos/{sdm_memo}/attachments/{attachment}', [MemoAttachmentController::class, 'destroy'])->name('sdm-memos.attachments.destroy');

    Route::resource('lines', LineController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('stations', StationController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('contractors', ContractorController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('/contracts/{contract}/publish', [ServiceContractController::class, 'publish'])->name('contracts.publish');
    Route::post('/contracts/{contract}/revise', [ServiceContractController::class, 'revise'])->name('contracts.revise');
    Route::post('/contracts/{contract}/retire', [ServiceContractController::class, 'retire'])->name('contracts.retire');
    Route::get('/contracts/{contract}/source', [ServiceContractController::class, 'source'])->name('contracts.source');
    Route::resource('contracts', ServiceContractController::class);
});

require __DIR__.'/auth.php';
