import AssetController from './AssetController'
import AssetMaintenanceController from './AssetMaintenanceController'
import AssetTransferController from './AssetTransferController'
import AssetLifecycleController from './AssetLifecycleController'
import AttachmentController from './AttachmentController'
import InventoryCountController from './InventoryCountController'
import HandoverController from './HandoverController'
import BookingController from './BookingController'
import RequisitionController from './RequisitionController'
import RequisitionTemplateController from './RequisitionTemplateController'
import InventoryReportController from './InventoryReportController'
import ProductLabelController from './ProductLabelController'
import ReceivingController from './ReceivingController'
import SupplierController from './SupplierController'
import PurchaseOrderController from './PurchaseOrderController'
import ForecastController from './ForecastController'
import TrashController from './TrashController'
import StockMovementController from './StockMovementController'
import ProductController from './ProductController'
import AuditLogController from './AuditLogController'
import HandoverVerificationController from './HandoverVerificationController'
import HandoverReceiptController from './HandoverReceiptController'
const Inventory = {
    AssetController: Object.assign(AssetController, AssetController),
AssetMaintenanceController: Object.assign(AssetMaintenanceController, AssetMaintenanceController),
AssetTransferController: Object.assign(AssetTransferController, AssetTransferController),
AssetLifecycleController: Object.assign(AssetLifecycleController, AssetLifecycleController),
AttachmentController: Object.assign(AttachmentController, AttachmentController),
InventoryCountController: Object.assign(InventoryCountController, InventoryCountController),
HandoverController: Object.assign(HandoverController, HandoverController),
BookingController: Object.assign(BookingController, BookingController),
RequisitionController: Object.assign(RequisitionController, RequisitionController),
RequisitionTemplateController: Object.assign(RequisitionTemplateController, RequisitionTemplateController),
InventoryReportController: Object.assign(InventoryReportController, InventoryReportController),
ProductLabelController: Object.assign(ProductLabelController, ProductLabelController),
ReceivingController: Object.assign(ReceivingController, ReceivingController),
SupplierController: Object.assign(SupplierController, SupplierController),
PurchaseOrderController: Object.assign(PurchaseOrderController, PurchaseOrderController),
ForecastController: Object.assign(ForecastController, ForecastController),
TrashController: Object.assign(TrashController, TrashController),
StockMovementController: Object.assign(StockMovementController, StockMovementController),
ProductController: Object.assign(ProductController, ProductController),
AuditLogController: Object.assign(AuditLogController, AuditLogController),
HandoverVerificationController: Object.assign(HandoverVerificationController, HandoverVerificationController),
HandoverReceiptController: Object.assign(HandoverReceiptController, HandoverReceiptController),
}

export default Inventory