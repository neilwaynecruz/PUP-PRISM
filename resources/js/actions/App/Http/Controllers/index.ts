import DashboardController from './DashboardController'
import Inventory from './Inventory'
import Settings from './Settings'
const Controllers = {
    DashboardController: Object.assign(DashboardController, DashboardController),
Inventory: Object.assign(Inventory, Inventory),
Settings: Object.assign(Settings, Settings),
}

export default Controllers