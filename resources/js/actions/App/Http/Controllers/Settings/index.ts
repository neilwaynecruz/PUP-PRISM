import SecurityController from './SecurityController'
import ProfileController from './ProfileController'
import NotificationPreferenceController from './NotificationPreferenceController'
import ApiTokenController from './ApiTokenController'
const Settings = {
    SecurityController: Object.assign(SecurityController, SecurityController),
ProfileController: Object.assign(ProfileController, ProfileController),
NotificationPreferenceController: Object.assign(NotificationPreferenceController, NotificationPreferenceController),
ApiTokenController: Object.assign(ApiTokenController, ApiTokenController),
}

export default Settings