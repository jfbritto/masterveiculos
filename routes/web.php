<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/sync/tenants', [SyncController::class, 'syncAll'])->name('sync.tenants');

    Route::resource('tenants', TenantController::class);
    Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('tenants/{tenant}/reactivate', [TenantController::class, 'reactivate'])->name('tenants.reactivate');
    Route::post('tenants/{tenant}/refresh-stats', [TenantController::class, 'refreshStats'])->name('tenants.refresh-stats');
    Route::post('tenants/{tenant}/regenerate-token', [TenantController::class, 'regenerateToken'])->name('tenants.regenerate-token');
    Route::post('tenants/{tenant}/activate-billing', [TenantController::class, 'activateBilling'])->name('tenants.activate-billing');
    Route::post('tenants/{tenant}/cancel-billing', [TenantController::class, 'cancelBilling'])->name('tenants.cancel-billing');

    Route::resource('payments', PaymentController::class)->only(['index', 'create', 'store']);
    Route::post('payments/{payment}/mark-paid', [PaymentController::class, 'markPaid'])->name('payments.mark-paid');
    Route::post('payments/{payment}/cancel', [PaymentController::class, 'cancel'])->name('payments.cancel');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
