import HealthController from './HealthController'
import UserManagementController from './UserManagementController'
const Admin = {
    HealthController: Object.assign(HealthController, HealthController),
UserManagementController: Object.assign(UserManagementController, UserManagementController),
}

export default Admin