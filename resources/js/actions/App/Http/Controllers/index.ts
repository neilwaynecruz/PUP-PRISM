import Api from './Api'
import DashboardController from './DashboardController'
import Inventory from './Inventory'
import Settings from './Settings'
const Controllers = {
    Api: Object.assign(Api, Api),
DashboardController: Object.assign(DashboardController, DashboardController),
Inventory: Object.assign(Inventory, Inventory),
Settings: Object.assign(Settings, Settings),
}

export default Controllers