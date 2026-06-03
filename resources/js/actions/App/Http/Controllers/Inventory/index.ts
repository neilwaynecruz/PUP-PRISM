import HandoverController from './HandoverController'
import BookingController from './BookingController'
import RequisitionController from './RequisitionController'
import InventoryReportController from './InventoryReportController'
import ProductLabelController from './ProductLabelController'
import ReceivingController from './ReceivingController'
import TrashController from './TrashController'
import StockMovementController from './StockMovementController'
import ProductController from './ProductController'
import HandoverVerificationController from './HandoverVerificationController'
import HandoverReceiptController from './HandoverReceiptController'
const Inventory = {
    HandoverController: Object.assign(HandoverController, HandoverController),
BookingController: Object.assign(BookingController, BookingController),
RequisitionController: Object.assign(RequisitionController, RequisitionController),
InventoryReportController: Object.assign(InventoryReportController, InventoryReportController),
ProductLabelController: Object.assign(ProductLabelController, ProductLabelController),
ReceivingController: Object.assign(ReceivingController, ReceivingController),
TrashController: Object.assign(TrashController, TrashController),
StockMovementController: Object.assign(StockMovementController, StockMovementController),
ProductController: Object.assign(ProductController, ProductController),
HandoverVerificationController: Object.assign(HandoverVerificationController, HandoverVerificationController),
HandoverReceiptController: Object.assign(HandoverReceiptController, HandoverReceiptController),
}

export default Inventory