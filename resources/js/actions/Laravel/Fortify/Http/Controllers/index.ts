import AuthenticatedSessionController from './AuthenticatedSessionController'
import ConfirmablePasswordController from './ConfirmablePasswordController'
import ConfirmedPasswordStatusController from './ConfirmedPasswordStatusController'
const Controllers = {
    AuthenticatedSessionController: Object.assign(AuthenticatedSessionController, AuthenticatedSessionController),
ConfirmablePasswordController: Object.assign(ConfirmablePasswordController, ConfirmablePasswordController),
ConfirmedPasswordStatusController: Object.assign(ConfirmedPasswordStatusController, ConfirmedPasswordStatusController),
}

export default Controllers