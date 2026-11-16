<?php

namespace App\Providers;

use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
use App\Models\AssetMaintenance;
use App\Models\AssetTransfer;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Department;
use App\Models\HandoverLog;
use App\Models\InventoryAlert;
use App\Models\InventoryCountSession;
use App\Models\Location;
use App\Models\NotificationPreference;
use App\Models\Origin;
use App\Models\Position;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\RequisitionTemplate;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Policies\AssetLifecycleEventPolicy;
use App\Policies\AssetMaintenancePolicy;
use App\Policies\AssetPolicy;
use App\Policies\AssetTransferPolicy;
use App\Policies\AttachmentPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\BookingPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\HandoverLogPolicy;
use App\Policies\InventoryAlertPolicy;
use App\Policies\InventoryCountSessionPolicy;
use App\Policies\LocationPolicy;
use App\Policies\NotificationPreferencePolicy;
use App\Policies\OriginPolicy;
use App\Policies\PositionPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\RequisitionPolicy;
use App\Policies\RequisitionTemplatePolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Gate::policy(NotificationPreference::class, NotificationPreferencePolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(Requisition::class, RequisitionPolicy::class);
        Gate::policy(RequisitionTemplate::class, RequisitionTemplatePolicy::class);
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(InventoryAlert::class, InventoryAlertPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Position::class, PositionPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Origin::class, OriginPolicy::class);
        Gate::policy(AssetLifecycleEvent::class, AssetLifecycleEventPolicy::class);
        Gate::policy(AssetMaintenance::class, AssetMaintenancePolicy::class);
        Gate::policy(AssetTransfer::class, AssetTransferPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(HandoverLog::class, HandoverLogPolicy::class);
        Gate::policy(InventoryCountSession::class, InventoryCountSessionPolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
    }
}
