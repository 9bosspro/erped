import Api from './Api'
import Web from './Web'
const Controllers = {
    Api: Object.assign(Api, Api),
Web: Object.assign(Web, Web),
}

export default Controllers