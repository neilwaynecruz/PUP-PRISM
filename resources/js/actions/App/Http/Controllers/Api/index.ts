import ProductController from './ProductController'
import AssetController from './AssetController'
import StockMovementController from './StockMovementController'
import RequisitionController from './RequisitionController'
const Api = {
    ProductController: Object.assign(ProductController, ProductController),
AssetController: Object.assign(AssetController, AssetController),
StockMovementController: Object.assign(StockMovementController, StockMovementController),
RequisitionController: Object.assign(RequisitionController, RequisitionController),
}

export default Api