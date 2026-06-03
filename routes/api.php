<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RequisitionController;
use App\Http\Controllers\Api\StockMovementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are prefixed with /api and use the sanctum guard.
| Rate limiting is applied via the "throttle:api" middleware.
|
*/

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('products', [ProductController::class, 'index'])->name('api.products.index');
    Route::get('products/{product}', [ProductController::class, 'show'])->name('api.products.show');

    Route::get('assets', [AssetController::class, 'index'])->name('api.assets.index');
    Route::get('assets/{asset}', [AssetController::class, 'show'])->name('api.assets.show');

    Route::get('stock-movements', [StockMovementController::class, 'index'])->name('api.stock-movements.index');

    Route::post('requisitions', [RequisitionController::class, 'store'])->name('api.requisitions.store');
    Route::get('requisitions', [RequisitionController::class, 'index'])->name('api.requisitions.index');
    Route::get('requisitions/{requisition}', [RequisitionController::class, 'show'])->name('api.requisitions.show');
});
