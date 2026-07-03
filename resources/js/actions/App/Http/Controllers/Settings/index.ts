import ProfileController from './ProfileController'
import SecurityController from './SecurityController'
import NotificationPreferenceController from './NotificationPreferenceController'
import ApiTokenController from './ApiTokenController'
const Settings = {
    ProfileController: Object.assign(ProfileController, ProfileController),
SecurityController: Object.assign(SecurityController, SecurityController),
NotificationPreferenceController: Object.assign(NotificationPreferenceController, NotificationPreferenceController),
ApiTokenController: Object.assign(ApiTokenController, ApiTokenController),
}

export default Settings