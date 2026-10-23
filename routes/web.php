<?php

use App\Http\Controllers\Admin\AlertsController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\HealthController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\OperationsHealthController;
use App\Http\Controllers\Admin\OriginController;
use App\Http\Controllers\Admin\PositionController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\Inventory\AssetController;
use App\Http\Controllers\Inventory\AssetLifecycleController;
use App\Http\Controllers\Inventory\AssetMaintenanceController;
use App\Http\Controllers\Inventory\AssetTransferController;
use App\Http\Controllers\Inventory\AttachmentController;
use App\Http\Controllers\Inventory\AuditLogController;
use App\Http\Controllers\Inventory\BookingController;
use App\Http\Controllers\Inventory\ForecastController;
use App\Http\Controllers\Inventory\HandoverController;
use App\Http\Controllers\Inventory\HandoverReceiptController;
use App\Http\Controllers\Inventory\HandoverVerificationController;
use App\Http\Controllers\Inventory\InventoryCountController;
use App\Http\Controllers\Inventory\InventoryReportController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\ProductLabelController;
use App\Http\Controllers\Inventory\PurchaseOrderController;
use App\Http\Controllers\Inventory\ReceivingController;
use App\Http\Controllers\Inventory\RequisitionController;
use App\Http\Controllers\Inventory\RequisitionTemplateController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\Inventory\SupplierController;
use App\Http\Controllers\Inventory\TrashController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SessionStatusController;
use App\Support\PreventClientCaching;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    if ($request->user() !== null) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::get('session/status', SessionStatusController::class)->name('session.status');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('search', GlobalSearchController::class)->name('search');
    Route::get('session/keep-alive', fn () => PreventClientCaching::apply(response()->noContent()))
        ->name('session.keep-alive');
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])
            ->name('index');

        Route::put('{notification}/read', [NotificationController::class, 'markAsRead'])
            ->name('read');

        Route::put('read-all', [NotificationController::class, 'markAllAsRead'])
            ->name('read-all');
    });

    Route::get('admin/health', HealthController::class)
        ->middleware('role:Admin')
        ->name('admin.health');

    Route::prefix('admin')->name('admin.')->middleware('role:Admin')->group(function () {
        Route::get('operations/health', OperationsHealthController::class)
            ->name('operations.health');

        Route::get('users', [UserManagementController::class, 'index'])
            ->name('users.index');

        Route::get('users/create', [UserManagementController::class, 'create'])
            ->name('users.create');

        Route::post('users', [UserManagementController::class, 'store'])
            ->name('users.store');

        Route::get('users/{managedUser}/edit', [UserManagementController::class, 'edit'])
            ->name('users.edit');

        Route::put('users/{managedUser}', [UserManagementController::class, 'update'])
            ->name('users.update');

        Route::patch('users/{managedUser}/deactivate', [UserManagementController::class, 'deactivate'])
            ->name('users.deactivate');

        Route::resource('departments', DepartmentController::class)->except(['show']);
        Route::patch('departments/{department}/deactivate', [DepartmentController::class, 'deactivate'])
            ->name('departments.deactivate');

        Route::resource('positions', PositionController::class)->except(['show']);
        Route::patch('positions/{position}/deactivate', [PositionController::class, 'deactivate'])
            ->name('positions.deactivate');

        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::patch('categories/{category}/deactivate', [CategoryController::class, 'deactivate'])
            ->name('categories.deactivate');

        Route::resource('origins', OriginController::class)->except(['show']);
        Route::patch('origins/{origin}/deactivate', [OriginController::class, 'deactivate'])
            ->name('origins.deactivate');

        Route::resource('locations', LocationController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::prefix('admin/alerts')->name('admin.alerts.')->middleware('role:Admin|Supply Head')->group(function () {
        Route::get('/', [AlertsController::class, 'index'])
            ->name('index');

        Route::patch('{alert}/acknowledge', [AlertsController::class, 'acknowledge'])
            ->name('acknowledge');

        Route::patch('{alert}/assign', [AlertsController::class, 'assign'])
            ->name('assign');

        Route::patch('{alert}/resolve', [AlertsController::class, 'resolve'])
            ->name('resolve');
    });

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::middleware('role:Admin|Supply Head|Property Custodian')->group(function () {
            Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
            Route::get('assets/{asset}', [AssetController::class, 'show'])->whereNumber('asset')->name('assets.show');

            Route::post('maintenance', [AssetMaintenanceController::class, 'store'])->name('maintenance.store');
            Route::put('maintenance/{assetMaintenance}/status', [AssetMaintenanceController::class, 'transition'])
                ->whereNumber('assetMaintenance')
                ->name('maintenance.transition');

            Route::post('asset-transfers', [AssetTransferController::class, 'store'])->name('asset-transfers.store');
            Route::post('asset-transfers/{assetTransfer}/approve', [AssetTransferController::class, 'approve'])
                ->whereNumber('assetTransfer')
                ->name('asset-transfers.approve');
            Route::post('asset-transfers/{assetTransfer}/reject', [AssetTransferController::class, 'reject'])
                ->whereNumber('assetTransfer')
                ->name('asset-transfers.reject');
            Route::post('asset-transfers/{assetTransfer}/cancel', [AssetTransferController::class, 'cancel'])
                ->whereNumber('assetTransfer')
                ->name('asset-transfers.cancel');

            Route::post('asset-lifecycle', [AssetLifecycleController::class, 'store'])->name('asset-lifecycle.store');
            Route::post('asset-lifecycle/{assetLifecycleEvent}/approve', [AssetLifecycleController::class, 'approve'])
                ->whereNumber('assetLifecycleEvent')
                ->name('asset-lifecycle.approve');
            Route::post('asset-lifecycle/{assetLifecycleEvent}/reject', [AssetLifecycleController::class, 'reject'])
                ->whereNumber('assetLifecycleEvent')
                ->name('asset-lifecycle.reject');

            Route::post('attachments/{attachableType}/{attachableId}', [AttachmentController::class, 'store'])
                ->whereIn('attachableType', ['asset', 'maintenance', 'transfer', 'lifecycle', 'count-session'])
                ->whereNumber('attachableId')
                ->name('attachments.store');
            Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])
                ->whereNumber('attachment')
                ->name('attachments.download');
            Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])
                ->whereNumber('attachment')
                ->name('attachments.destroy');
        });

        Route::middleware('role:Admin|Supply Head|Property Custodian')->group(function () {
            Route::get('counts', [InventoryCountController::class, 'index'])->name('counts.index');
            Route::post('counts', [InventoryCountController::class, 'store'])->name('counts.store');
            Route::get('counts/{inventoryCountSession}', [InventoryCountController::class, 'show'])
                ->whereNumber('inventoryCountSession')
                ->name('counts.show');
            Route::put('counts/{inventoryCountSession}/lines/{inventoryCountLine}', [InventoryCountController::class, 'updateLine'])
                ->whereNumber(['inventoryCountSession', 'inventoryCountLine'])
                ->name('counts.lines.update');
            Route::post('counts/{inventoryCountSession}/submit', [InventoryCountController::class, 'submit'])
                ->whereNumber('inventoryCountSession')
                ->name('counts.submit');
            Route::put('counts/{inventoryCountSession}/lines/{inventoryCountLine}/review', [InventoryCountController::class, 'reviewLine'])
                ->whereNumber(['inventoryCountSession', 'inventoryCountLine'])
                ->name('counts.lines.review');
            Route::post('counts/{inventoryCountSession}/approve', [InventoryCountController::class, 'approve'])
                ->whereNumber('inventoryCountSession')
                ->name('counts.approve');
            Route::post('counts/{inventoryCountSession}/complete', [InventoryCountController::class, 'complete'])
                ->whereNumber('inventoryCountSession')
                ->name('counts.complete');
            Route::post('counts/{inventoryCountSession}/cancel', [InventoryCountController::class, 'cancel'])
                ->whereNumber('inventoryCountSession')
                ->name('counts.cancel');
        });

        Route::middleware('role:Admin|Property Custodian')->group(function () {
            Route::get('handover', [HandoverController::class, 'index'])
                ->name('handover.index');

            Route::post('handover', [HandoverController::class, 'store'])
                ->name('handover.store');

            Route::put('bookings/{booking}', [BookingController::class, 'update'])
                ->whereNumber('booking')
                ->name('bookings.update');
        });

        Route::middleware('role:Admin|Supply Head|Property Custodian')->group(function () {
            Route::get('bookings', [BookingController::class, 'index'])
                ->name('bookings.index');

            Route::post('bookings', [BookingController::class, 'store'])
                ->name('bookings.store');

            Route::get('bookings/trash', [BookingController::class, 'trash'])
                ->name('bookings.trash');

            Route::get('bookings/{booking}', [BookingController::class, 'show'])
                ->whereNumber('booking')
                ->name('bookings.show');

            Route::delete('bookings/{booking}', [BookingController::class, 'destroy'])
                ->whereNumber('booking')
                ->name('bookings.destroy');

            Route::put('bookings/{booking}/restore', [BookingController::class, 'restore'])
                ->whereNumber('booking')
                ->name('bookings.restore');

            Route::delete('bookings/{booking}/force', [BookingController::class, 'forceDelete'])
                ->whereNumber('booking')
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
                ->whereNumber('requisitionTemplate')
                ->name('requisition-templates.update');

            Route::post('requisition-templates/{requisitionTemplate}/duplicate', [RequisitionTemplateController::class, 'duplicate'])
                ->whereNumber('requisitionTemplate')
                ->name('requisition-templates.duplicate');

            Route::delete('requisition-templates/{requisitionTemplate}', [RequisitionTemplateController::class, 'destroy'])
                ->whereNumber('requisitionTemplate')
                ->name('requisition-templates.destroy');

            Route::get('requisitions/trash', [RequisitionController::class, 'trash'])
                ->name('requisitions.trash');

            Route::get('requisitions/{requisition}', [RequisitionController::class, 'show'])
                ->whereNumber('requisition')
                ->name('requisitions.show');

            Route::delete('requisitions/{requisition}', [RequisitionController::class, 'destroy'])
                ->whereNumber('requisition')
                ->name('requisitions.destroy');

            Route::put('requisitions/{requisition}/restore', [RequisitionController::class, 'restore'])
                ->whereNumber('requisition')
                ->name('requisitions.restore');

            Route::delete('requisitions/{requisition}/force', [RequisitionController::class, 'forceDelete'])
                ->whereNumber('requisition')
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

            Route::get('reports/procurement/{format}', [InventoryReportController::class, 'procurement'])
                ->whereIn('format', ['csv', 'pdf'])
                ->name('reports.procurement');

            Route::get('reports/forecasting/{format}', [InventoryReportController::class, 'forecasting'])
                ->whereIn('format', ['csv', 'pdf'])
                ->name('reports.forecasting');

            Route::get('reports/slow-moving/{format}', [InventoryReportController::class, 'slowMovingStock'])
                ->whereIn('format', ['csv', 'pdf'])
                ->name('reports.slow-moving');

            Route::get('reports/expirations/{format}', [InventoryReportController::class, 'expirations'])
                ->whereIn('format', ['csv', 'pdf'])
                ->name('reports.expirations');

            Route::get('reports/optimization/{format}', [InventoryReportController::class, 'optimization'])
                ->whereIn('format', ['csv', 'pdf'])
                ->name('reports.optimization');
        });

        Route::middleware('role:Admin|Supply Head')->group(function () {
            Route::put('requisitions/{requisition}/approve', [RequisitionController::class, 'approve'])
                ->whereNumber('requisition')
                ->name('requisitions.approve');

            Route::put('requisitions/{requisition}/reject', [RequisitionController::class, 'reject'])
                ->whereNumber('requisition')
                ->name('requisitions.reject');

            Route::put('requisitions/{requisition}/issue', [RequisitionController::class, 'issue'])
                ->whereNumber('requisition')
                ->name('requisitions.issue');

            Route::get('products/labels/sheet', [ProductLabelController::class, 'sheet'])
                ->name('products.labels.sheet');

            Route::post('products/labels/pdf', [ProductLabelController::class, 'pdf'])
                ->name('products.labels.pdf');

            // Not part of ProductController/authorizeResource, so it keeps its own guard.
            Route::get('products/{product}/label', [ProductLabelController::class, 'show'])
                ->whereNumber('product')
                ->name('products.label');

            Route::get('receiving', [ReceivingController::class, 'index'])
                ->name('receiving.index');

            Route::post('receiving', [ReceivingController::class, 'store'])
                ->name('receiving.store');

            Route::post('receiving/batch', [ReceivingController::class, 'storeBatch'])
                ->name('receiving.batch');

            Route::get('suppliers', [SupplierController::class, 'index'])
                ->name('suppliers.index');

            Route::get('suppliers/create', [SupplierController::class, 'create'])
                ->name('suppliers.create');

            Route::post('suppliers', [SupplierController::class, 'store'])
                ->name('suppliers.store');

            Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])
                ->whereNumber('supplier')
                ->name('suppliers.show');

            Route::get('suppliers/{supplier}/edit', [SupplierController::class, 'edit'])
                ->whereNumber('supplier')
                ->name('suppliers.edit');

            Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])
                ->whereNumber('supplier')
                ->name('suppliers.update');

            Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])
                ->whereNumber('supplier')
                ->name('suppliers.destroy');

            Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])
                ->name('purchase-orders.index');

            Route::get('forecasting', [ForecastController::class, 'index'])
                ->name('forecasting.index');

            Route::get('forecasting/{product}', [ForecastController::class, 'show'])
                ->whereNumber('product')
                ->name('forecasting.show');

            Route::put('forecasting/{product}/profile', [ForecastController::class, 'updateProfile'])
                ->whereNumber('product')
                ->name('forecasting.profile.update');

            Route::get('purchase-orders/create', [PurchaseOrderController::class, 'create'])
                ->name('purchase-orders.create');

            Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])
                ->name('purchase-orders.store');

            Route::post('purchase-orders/generate', [PurchaseOrderController::class, 'generate'])
                ->name('purchase-orders.generate');

            Route::post('purchase-orders/generate-from-forecasts', [PurchaseOrderController::class, 'generateFromForecasts'])
                ->name('purchase-orders.generate-from-forecasts');

            Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
                ->whereNumber('purchaseOrder')
                ->name('purchase-orders.show');

            Route::put('purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])
                ->whereNumber('purchaseOrder')
                ->name('purchase-orders.send');

            Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])
                ->whereNumber('purchaseOrder')
                ->name('purchase-orders.receive');

            Route::put('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
                ->whereNumber('purchaseOrder')
                ->name('purchase-orders.cancel');
        });

        Route::middleware('role:Admin')->group(function () {
            Route::get('trash', TrashController::class)
                ->name('trash');

            Route::get('movements', [StockMovementController::class, 'index'])
                ->name('movements.index');

            Route::post('movements/adjustments', [StockMovementController::class, 'adjust'])
                ->name('movements.adjust');

            Route::post('movements/cycle-counts', [StockMovementController::class, 'cycleCount'])
                ->name('movements.cycle-count');

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

        Route::get('products/lookup', [ProductController::class, 'lookup'])
            ->name('products.lookup');

        Route::get('products/create', [ProductController::class, 'create'])
            ->name('products.create');

        Route::post('products', [ProductController::class, 'store'])
            ->name('products.store');

        Route::get('products/trash', [ProductController::class, 'trash'])
            ->name('products.trash');

        Route::get('products/{product}', [ProductController::class, 'show'])
            ->whereNumber('product')
            ->name('products.show');

        Route::get('products/{product}/edit', [ProductController::class, 'edit'])
            ->whereNumber('product')
            ->name('products.edit');

        Route::put('products/{product}', [ProductController::class, 'update'])
            ->whereNumber('product')
            ->name('products.update');

        Route::delete('products/{product}', [ProductController::class, 'destroy'])
            ->whereNumber('product')
            ->name('products.destroy');

        Route::put('products/{product}/restore', [ProductController::class, 'restore'])
            ->whereNumber('product')
            ->name('products.restore');

        Route::delete('products/{product}/force', [ProductController::class, 'forceDelete'])
            ->whereNumber('product')
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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('inventory/handover/verify/{handoverLog}', HandoverVerificationController::class)
        ->whereNumber('handoverLog')
        ->name('inventory.handover.verify');

    Route::post('inventory/handover/verify/{handoverLog}', [HandoverController::class, 'verify'])
        ->whereNumber('handoverLog')
        ->name('inventory.handover.verify.submit');

    Route::get('inventory/handover/receipt/{handoverLog}', HandoverReceiptController::class)
        ->whereNumber('handoverLog')
        ->name('inventory.handover.receipt');
});

require __DIR__.'/settings.php';
