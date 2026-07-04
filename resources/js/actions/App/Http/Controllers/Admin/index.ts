import HealthController from './HealthController'
import OperationsHealthController from './OperationsHealthController'
import UserManagementController from './UserManagementController'
import DepartmentController from './DepartmentController'
import PositionController from './PositionController'
import CategoryController from './CategoryController'
import OriginController from './OriginController'
import AlertsController from './AlertsController'
const Admin = {
    HealthController: Object.assign(HealthController, HealthController),
OperationsHealthController: Object.assign(OperationsHealthController, OperationsHealthController),
UserManagementController: Object.assign(UserManagementController, UserManagementController),
DepartmentController: Object.assign(DepartmentController, DepartmentController),
PositionController: Object.assign(PositionController, PositionController),
CategoryController: Object.assign(CategoryController, CategoryController),
OriginController: Object.assign(OriginController, OriginController),
AlertsController: Object.assign(AlertsController, AlertsController),
}

export default Admin