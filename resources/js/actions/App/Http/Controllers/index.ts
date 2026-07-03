import Api from './Api'
import DashboardController from './DashboardController'
import NotificationController from './NotificationController'
import Admin from './Admin'
import Inventory from './Inventory'
import Settings from './Settings'
const Controllers = {
    Api: Object.assign(Api, Api),
DashboardController: Object.assign(DashboardController, DashboardController),
NotificationController: Object.assign(NotificationController, NotificationController),
Admin: Object.assign(Admin, Admin),
Inventory: Object.assign(Inventory, Inventory),
Settings: Object.assign(Settings, Settings),
}

export default Controllers