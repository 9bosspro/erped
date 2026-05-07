import Auth from './Auth'
import Demo from './Demo'
import User from './User'
const Modules = {
    Auth: Object.assign(Auth, Auth),
Demo: Object.assign(Demo, Demo),
User: Object.assign(User, User),
}

export default Modules