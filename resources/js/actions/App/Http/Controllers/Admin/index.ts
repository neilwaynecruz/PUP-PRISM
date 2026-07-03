import HealthController from './HealthController'
import OperationsHealthController from './OperationsHealthController'
import UserManagementController from './UserManagementController'
import AlertsController from './AlertsController'
const Admin = {
    HealthController: Object.assign(HealthController, HealthController),
OperationsHealthController: Object.assign(OperationsHealthController, OperationsHealthController),
UserManagementController: Object.assign(UserManagementController, UserManagementController),
AlertsController: Object.assign(AlertsController, AlertsController),
}

export default Admin