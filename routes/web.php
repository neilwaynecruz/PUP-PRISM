<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\BookingController;
use App\Http\Controllers\Inventory\HandoverController;
use App\Http\Controllers\Inventory\HandoverReceiptController;
use App\Http\Controllers\Inventory\HandoverVerificationController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\ProductLabelController;
use App\Http\Controllers\Inventory\ReceivingController;
use App\Http\Controllers\Inventory\RequisitionController;
use App\Http\Controllers\Inventory\StockMovementController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('admin/health', fn () => response()->noContent())
        ->middleware('role:Admin')
        ->name('admin.health');

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('handover', [HandoverController::class, 'index'])
            ->middleware('role:Admin|Property Custodian')
            ->name('handover.index');

        Route::post('handover', [HandoverController::class, 'store'])
            ->middleware('role:Admin|Property Custodian')
            ->name('handover.store');

        //

        Route::get('bookings', [BookingController::class, 'index'])
            ->middleware('role:Admin|Supply Head|Property Custodian')
            ->name('bookings.index');

        Route::post('bookings', [BookingController::class, 'store'])
            ->middleware('role:Admin|Supply Head|Property Custodian')
            ->name('bookings.store');

        Route::put('bookings/{booking}', [BookingController::class, 'update'])
            ->middleware('role:Admin|Property Custodian')
            ->name('bookings.update');

        Route::get('requisitions', [RequisitionController::class, 'index'])
            ->middleware('role:Admin|Supply Head|Property Custodian')
            ->name('requisitions.index');

        Route::post('requisitions', [RequisitionController::class, 'store'])
            ->middleware('role:Admin|Supply Head|Property Custodian')
            ->name('requisitions.store');

        Route::get('requisitions/{requisition}', [RequisitionController::class, 'show'])
            ->middleware('role:Admin|Supply Head|Property Custodian')
            ->name('requisitions.show');

        Route::put('requisitions/{requisition}/approve', [RequisitionController::class, 'approve'])
            ->middleware('role:Admin|Supply Head')
            ->name('requisitions.approve');

        Route::put('requisitions/{requisition}/reject', [RequisitionController::class, 'reject'])
            ->middleware('role:Admin|Supply Head')
            ->name('requisitions.reject');

        Route::put('requisitions/{requisition}/issue', [RequisitionController::class, 'issue'])
            ->middleware('role:Admin|Supply Head')
            ->name('requisitions.issue');

        // ProductController uses authorizeResource(Product::class, 'product'),
        // so ProductPolicy is the single source of truth for per-action access.
        Route::get('products', [ProductController::class, 'index'])
            ->name('products.index');

        Route::get('products/create', [ProductController::class, 'create'])
            ->name('products.create');

        Route::post('products', [ProductController::class, 'store'])
            ->name('products.store');

        Route::get('products/{product}', [ProductController::class, 'show'])
            ->name('products.show');

        Route::get('products/{product}/edit', [ProductController::class, 'edit'])
            ->name('products.edit');

        Route::put('products/{product}', [ProductController::class, 'update'])
            ->name('products.update');

        Route::delete('products/{product}', [ProductController::class, 'destroy'])
            ->name('products.destroy');

        // Not part of ProductController/authorizeResource, so it keeps its own guard.
        Route::get('products/{product}/label', [ProductLabelController::class, 'show'])
            ->middleware('role:Admin|Supply Head')
            ->name('products.label');

        Route::get('receiving', [ReceivingController::class, 'index'])
            ->middleware('role:Admin|Supply Head')
            ->name('receiving.index');

        Route::post('receiving', [ReceivingController::class, 'store'])
            ->middleware('role:Admin|Supply Head')
            ->name('receiving.store');

        Route::get('movements', [StockMovementController::class, 'index'])
            ->middleware('role:Admin')
            ->name('movements.index');
    });
});

Route::middleware(['auth'])->group(function () {
    Route::get('inventory/handover/verify/{handoverLog}', HandoverVerificationController::class)
        ->name('inventory.handover.verify');

    Route::post('inventory/handover/verify/{handoverLog}', [HandoverController::class, 'verify'])
        ->name('inventory.handover.verify.submit');

    Route::get('inventory/handover/receipt/{handoverLog}', HandoverReceiptController::class)
        ->name('inventory.handover.receipt');
});

require __DIR__.'/settings.php';
