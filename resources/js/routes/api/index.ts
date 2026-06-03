import products from './products'
import assets from './assets'
import stockMovements from './stock-movements'
import requisitions from './requisitions'
const api = {
    products: Object.assign(products, products),
assets: Object.assign(assets, assets),
stockMovements: Object.assign(stockMovements, stockMovements),
requisitions: Object.assign(requisitions, requisitions),
}

export default api