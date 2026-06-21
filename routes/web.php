<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\AuditLogController;
use App\Http\Controllers\Inventory\BookingController;
use App\Http\Controllers\Inventory\HandoverController;
use App\Http\Controllers\Inventory\HandoverReceiptController;
use App\Http\Controllers\Inventory\HandoverVerificationController;
use App\Http\Controllers\Inventory\InventoryReportController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\ProductLabelController;
use App\Http\Controllers\Inventory\ReceivingController;
use App\Http\Controllers\Inventory\RequisitionController;
use App\Http\Controllers\Inventory\RequisitionTemplateController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\Inventory\TrashController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('session/keep-alive', fn () => response()->noContent()->header('Cache-Control', 'no-store'))
        ->name('session.keep-alive');
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::put('{notification}/read', [NotificationController::class, 'markAsRead'])
            ->name('read');

        Route::put('read-all', [NotificationController::class, 'markAllAsRead'])
            ->name('read-all');
    });

    Route::get('admin/health', fn () => response()->noContent())
        ->middleware('role:Admin')
        ->name('admin.health');

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::middleware('role:Admin|Property Custodian')->group(function () {
            Route::get('handover', [HandoverController::class, 'index'])
                ->name('handover.index');

            Route::post('handover', [HandoverController::class, 'store'])
                ->name('handover.store');

            Route::put('bookings/{booking}', [BookingController::class, 'update'])
                ->name('bookings.update');
        });

        Route::middleware('role:Admin|Supply Head|Property Custodian')->group(function () {
            Route::get('bookings', [BookingController::class, 'index'])
                ->name('bookings.index');

            Route::get('bookings/{booking}', [BookingController::class, 'show'])
                ->name('bookings.show');

            Route::post('bookings', [BookingController::class, 'store'])
                ->name('bookings.store');

            Route::delete('bookings/{booking}', [BookingController::class, 'destroy'])
                ->name('bookings.destroy');

            Route::get('bookings/trash', [BookingController::class, 'trash'])
                ->name('bookings.trash');

            Route::put('bookings/{booking}/restore', [BookingController::class, 'restore'])
                ->name('bookings.restore');

            Route::delete('bookings/{booking}/force', [BookingController::class, 'forceDelete'])
                ->name('bookings.force-delete');

            Route::post('bookings/bulk-restore', [BookingController::class, 'bulkRestore'])
                ->name('bookings.bulk-restore');

            Route::post('bookings/bulk-force-delete', [BookingController::class, 'bulkForceDelete'])
                ->name('bookings.bulk-force-delete');

            Route::post('bookings/bulk-approve', [BookingController::class, 'bulkApprove'])
                ->name('bookings.bulk-approve');

            Route::post('bookings/bulk-reject', [BookingController::class, 'bulkReject'])
                ->name('bookings.bulk-reject');

            Route::get('requisitions', [RequisitionController::class, 'index'])
                ->name('requisitions.index');

            Route::post('requisitions', [RequisitionController::class, 'store'])
                ->name('requisitions.store');

            Route::post('requisition-templates', [RequisitionTemplateController::class, 'store'])
                ->name('requisition-templates.store');

            Route::put('requisition-templates/{requisitionTemplate}', [RequisitionTemplateController::class, 'update'])
                ->name('requisition-templates.update');

            Route::post('requisition-templates/{requisitionTemplate}/duplicate', [RequisitionTemplateController::class, 'duplicate'])
                ->name('requisition-templates.duplicate');

            Route::delete('requisition-templates/{requisitionTemplate}', [RequisitionTemplateController::class, 'destroy'])
                ->name('requisition-templates.destroy');

            Route::get('requisitions/trash', [RequisitionController::class, 'trash'])
                ->name('requisitions.trash');

            Route::get('requisitions/{requisition}', [RequisitionController::class, 'show'])
                ->name('requisitions.show');

            Route::delete('requisitions/{requisition}', [RequisitionController::class, 'destroy'])
                ->name('requisitions.destroy');

            Route::put('requisitions/{requisition}/restore', [RequisitionController::class, 'restore'])
                ->name('requisitions.restore');

            Route::delete('requisitions/{requisition}/force', [RequisitionController::class, 'forceDelete'])
                ->name('requisitions.force-delete');

            Route::post('requisitions/bulk-restore', [RequisitionController::class, 'bulkRestore'])
                ->name('requisitions.bulk-restore');

            Route::post('requisitions/bulk-force-delete', [RequisitionController::class, 'bulkForceDelete'])
                ->name('requisitions.bulk-force-delete');

            Route::post('requisitions/bulk-approve', [RequisitionController::class, 'bulkApprove'])
                ->name('requisitions.bulk-approve');

            Route::post('requisitions/bulk-issue', [RequisitionController::class, 'bulkIssue'])
                ->name('requisitions.bulk-issue');

            Route::get('reports/products/{format}', [InventoryReportController::class, 'products'])
                ->whereIn('format', ['csv', 'pdf'])
                ->name('reports.products');

            Route::get('reports/bookings/{format}', [InventoryReportController::class, 'bookings'])
                ->whereIn('format', ['csv', 'pdf'])
                ->name('reports.bookings');

            Route::get('reports/requisitions/{format}', [InventoryReportController::class, 'requisitions'])
                ->whereIn('format', ['csv', 'pdf'])
                ->name('reports.requisitions');
        });

        Route::middleware('role:Admin|Supply Head')->group(function () {
            Route::put('requisitions/{requisition}/approve', [RequisitionController::class, 'approve'])
                ->name('requisitions.approve');

            Route::put('requisitions/{requisition}/reject', [RequisitionController::class, 'reject'])
                ->name('requisitions.reject');

            Route::put('requisitions/{requisition}/issue', [RequisitionController::class, 'issue'])
                ->name('requisitions.issue');

            // Not part of ProductController/authorizeResource, so it keeps its own guard.
            Route::get('products/{product}/label', [ProductLabelController::class, 'show'])
                ->name('products.label');

            Route::get('receiving', [ReceivingController::class, 'index'])
                ->name('receiving.index');

            Route::post('receiving', [ReceivingController::class, 'store'])
                ->name('receiving.store');

            Route::post('receiving/batch', [ReceivingController::class, 'storeBatch'])
                ->name('receiving.batch');
        });

        Route::middleware('role:Admin')->group(function () {
            Route::get('trash', TrashController::class)
                ->name('trash');

            Route::get('movements', [StockMovementController::class, 'index'])
                ->name('movements.index');

            Route::get('reports/movements/{format}', [InventoryReportController::class, 'stockMovements'])
                ->whereIn('format', ['csv', 'pdf'])
                ->name('reports.movements');

            Route::get('reports/assets/condition/{format}', [InventoryReportController::class, 'assetConditions'])
                ->whereIn('format', ['csv', 'pdf'])
                ->name('reports.asset-conditions');
        });

        // ProductController uses authorizeResource(Product::class, 'product'),
        // so ProductPolicy is the single source of truth for per-action access.
        Route::get('products', [ProductController::class, 'index'])
            ->name('products.index');

        Route::get('products/create', [ProductController::class, 'create'])
            ->name('products.create');

        Route::post('products', [ProductController::class, 'store'])
            ->name('products.store');

        Route::get('products/trash', [ProductController::class, 'trash'])
            ->name('products.trash');

        Route::get('products/{product}', [ProductController::class, 'show'])
            ->name('products.show');

        Route::get('products/{product}/edit', [ProductController::class, 'edit'])
            ->name('products.edit');

        Route::put('products/{product}', [ProductController::class, 'update'])
            ->name('products.update');

        Route::delete('products/{product}', [ProductController::class, 'destroy'])
            ->name('products.destroy');

        Route::put('products/{product}/restore', [ProductController::class, 'restore'])
            ->name('products.restore');

        Route::delete('products/{product}/force', [ProductController::class, 'forceDelete'])
            ->name('products.force-delete');

        Route::post('products/bulk-restore', [ProductController::class, 'bulkRestore'])
            ->name('products.bulk-restore');

        Route::post('products/bulk-force-delete', [ProductController::class, 'bulkForceDelete'])
            ->name('products.bulk-force-delete');

        Route::post('products/bulk-activate', [ProductController::class, 'bulkActivate'])
            ->name('products.bulk-activate');

        Route::post('products/bulk-deactivate', [ProductController::class, 'bulkDeactivate'])
            ->name('products.bulk-deactivate');

        Route::post('products/bulk-change-category', [ProductController::class, 'bulkChangeCategory'])
            ->name('products.bulk-change-category');

        Route::get('audit-logs', [AuditLogController::class, 'index'])
            ->name('audit-logs.index');

        Route::get('audit-logs/export', [AuditLogController::class, 'export'])
            ->name('audit-logs.export');
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
