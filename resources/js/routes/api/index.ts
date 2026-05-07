import auth from './auth'
import demo from './demo'
import user from './user'
const api = {
    auth: Object.assign(auth, auth),
demo: Object.assign(demo, demo),
user: Object.assign(user, user),
}

export default api