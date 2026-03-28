import HealthController from './HealthController'
import Api from './Api'
import Settings from './Settings'
const Controllers = {
    HealthController: Object.assign(HealthController, HealthController),
Api: Object.assign(Api, Api),
Settings: Object.assign(Settings, Settings),
}

export default Controllers