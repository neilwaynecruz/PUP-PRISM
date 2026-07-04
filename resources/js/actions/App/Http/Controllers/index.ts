import Api from './Api'
import DashboardController from './DashboardController'
import GlobalSearchController from './GlobalSearchController'
import NotificationController from './NotificationController'
import Admin from './Admin'
import Inventory from './Inventory'
import Settings from './Settings'
const Controllers = {
    Api: Object.assign(Api, Api),
DashboardController: Object.assign(DashboardController, DashboardController),
GlobalSearchController: Object.assign(GlobalSearchController, GlobalSearchController),
NotificationController: Object.assign(NotificationController, NotificationController),
Admin: Object.assign(Admin, Admin),
Inventory: Object.assign(Inventory, Inventory),
Settings: Object.assign(Settings, Settings),
}

export default Controllers