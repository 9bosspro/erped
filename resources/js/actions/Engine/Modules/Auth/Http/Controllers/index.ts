import Api from './Api'
import AuthController from './AuthController'
import Web from './Web'
const Controllers = {
    Api: Object.assign(Api, Api),
AuthController: Object.assign(AuthController, AuthController),
Web: Object.assign(Web, Web),
}

export default Controllers