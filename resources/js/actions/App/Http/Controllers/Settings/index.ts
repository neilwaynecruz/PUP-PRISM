import ProfileController from './ProfileController'
import SecurityController from './SecurityController'
import ApiTokenController from './ApiTokenController'
const Settings = {
    ProfileController: Object.assign(ProfileController, ProfileController),
SecurityController: Object.assign(SecurityController, SecurityController),
ApiTokenController: Object.assign(ApiTokenController, ApiTokenController),
}

export default Settings