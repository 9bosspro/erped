import HealthController from './HealthController'
import Settings from './Settings'
const Controllers = {
    HealthController: Object.assign(HealthController, HealthController),
Settings: Object.assign(Settings, Settings),
}

export default Controllers