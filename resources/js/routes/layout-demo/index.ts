import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::frontend
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:34
 * @route '/layout-demo/frontend'
 */
export const frontend = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: frontend.url(options),
    method: 'get',
})

frontend.definition = {
    methods: ["get","head"],
    url: '/layout-demo/frontend',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::frontend
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:34
 * @route '/layout-demo/frontend'
 */
frontend.url = (options?: RouteQueryOptions) => {
    return frontend.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::frontend
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:34
 * @route '/layout-demo/frontend'
 */
frontend.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: frontend.url(options),
    method: 'get',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::frontend
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:34
 * @route '/layout-demo/frontend'
 */
frontend.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: frontend.url(options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::frontend
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:34
 * @route '/layout-demo/frontend'
 */
    const frontendForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: frontend.url(options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::frontend
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:34
 * @route '/layout-demo/frontend'
 */
        frontendForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: frontend.url(options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::frontend
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:34
 * @route '/layout-demo/frontend'
 */
        frontendForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: frontend.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    frontend.form = frontendForm
/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::auth
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:39
 * @route '/layout-demo/auth'
 */
export const auth = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: auth.url(options),
    method: 'get',
})

auth.definition = {
    methods: ["get","head"],
    url: '/layout-demo/auth',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::auth
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:39
 * @route '/layout-demo/auth'
 */
auth.url = (options?: RouteQueryOptions) => {
    return auth.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::auth
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:39
 * @route '/layout-demo/auth'
 */
auth.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: auth.url(options),
    method: 'get',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::auth
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:39
 * @route '/layout-demo/auth'
 */
auth.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: auth.url(options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::auth
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:39
 * @route '/layout-demo/auth'
 */
    const authForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: auth.url(options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::auth
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:39
 * @route '/layout-demo/auth'
 */
        authForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: auth.url(options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::auth
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:39
 * @route '/layout-demo/auth'
 */
        authForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: auth.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    auth.form = authForm
/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::fullscreen
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:44
 * @route '/layout-demo/fullscreen'
 */
export const fullscreen = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: fullscreen.url(options),
    method: 'get',
})

fullscreen.definition = {
    methods: ["get","head"],
    url: '/layout-demo/fullscreen',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::fullscreen
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:44
 * @route '/layout-demo/fullscreen'
 */
fullscreen.url = (options?: RouteQueryOptions) => {
    return fullscreen.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::fullscreen
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:44
 * @route '/layout-demo/fullscreen'
 */
fullscreen.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: fullscreen.url(options),
    method: 'get',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::fullscreen
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:44
 * @route '/layout-demo/fullscreen'
 */
fullscreen.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: fullscreen.url(options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::fullscreen
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:44
 * @route '/layout-demo/fullscreen'
 */
    const fullscreenForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: fullscreen.url(options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::fullscreen
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:44
 * @route '/layout-demo/fullscreen'
 */
        fullscreenForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: fullscreen.url(options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::fullscreen
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:44
 * @route '/layout-demo/fullscreen'
 */
        fullscreenForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: fullscreen.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    fullscreen.form = fullscreenForm
/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::bare
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:49
 * @route '/layout-demo/bare'
 */
export const bare = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: bare.url(options),
    method: 'get',
})

bare.definition = {
    methods: ["get","head"],
    url: '/layout-demo/bare',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::bare
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:49
 * @route '/layout-demo/bare'
 */
bare.url = (options?: RouteQueryOptions) => {
    return bare.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::bare
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:49
 * @route '/layout-demo/bare'
 */
bare.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: bare.url(options),
    method: 'get',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::bare
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:49
 * @route '/layout-demo/bare'
 */
bare.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: bare.url(options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::bare
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:49
 * @route '/layout-demo/bare'
 */
    const bareForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: bare.url(options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::bare
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:49
 * @route '/layout-demo/bare'
 */
        bareForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: bare.url(options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::bare
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:49
 * @route '/layout-demo/bare'
 */
        bareForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: bare.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    bare.form = bareForm
/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::gallery
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:54
 * @route '/layout-demo/gallery'
 */
export const gallery = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: gallery.url(options),
    method: 'get',
})

gallery.definition = {
    methods: ["get","head"],
    url: '/layout-demo/gallery',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::gallery
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:54
 * @route '/layout-demo/gallery'
 */
gallery.url = (options?: RouteQueryOptions) => {
    return gallery.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::gallery
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:54
 * @route '/layout-demo/gallery'
 */
gallery.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: gallery.url(options),
    method: 'get',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::gallery
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:54
 * @route '/layout-demo/gallery'
 */
gallery.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: gallery.url(options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::gallery
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:54
 * @route '/layout-demo/gallery'
 */
    const galleryForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: gallery.url(options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::gallery
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:54
 * @route '/layout-demo/gallery'
 */
        galleryForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: gallery.url(options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::gallery
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:54
 * @route '/layout-demo/gallery'
 */
        galleryForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: gallery.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    gallery.form = galleryForm
/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::youtube
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:59
 * @route '/layout-demo/youtube'
 */
export const youtube = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: youtube.url(options),
    method: 'get',
})

youtube.definition = {
    methods: ["get","head"],
    url: '/layout-demo/youtube',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::youtube
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:59
 * @route '/layout-demo/youtube'
 */
youtube.url = (options?: RouteQueryOptions) => {
    return youtube.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::youtube
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:59
 * @route '/layout-demo/youtube'
 */
youtube.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: youtube.url(options),
    method: 'get',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::youtube
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:59
 * @route '/layout-demo/youtube'
 */
youtube.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: youtube.url(options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::youtube
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:59
 * @route '/layout-demo/youtube'
 */
    const youtubeForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: youtube.url(options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::youtube
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:59
 * @route '/layout-demo/youtube'
 */
        youtubeForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: youtube.url(options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::youtube
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:59
 * @route '/layout-demo/youtube'
 */
        youtubeForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: youtube.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    youtube.form = youtubeForm
/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::music
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:64
 * @route '/layout-demo/music'
 */
export const music = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: music.url(options),
    method: 'get',
})

music.definition = {
    methods: ["get","head"],
    url: '/layout-demo/music',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::music
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:64
 * @route '/layout-demo/music'
 */
music.url = (options?: RouteQueryOptions) => {
    return music.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::music
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:64
 * @route '/layout-demo/music'
 */
music.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: music.url(options),
    method: 'get',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::music
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:64
 * @route '/layout-demo/music'
 */
music.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: music.url(options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::music
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:64
 * @route '/layout-demo/music'
 */
    const musicForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: music.url(options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::music
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:64
 * @route '/layout-demo/music'
 */
        musicForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: music.url(options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::music
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:64
 * @route '/layout-demo/music'
 */
        musicForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: music.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    music.form = musicForm
/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::backend
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:29
 * @route '/layout-demo/backend'
 */
export const backend = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: backend.url(options),
    method: 'get',
})

backend.definition = {
    methods: ["get","head"],
    url: '/layout-demo/backend',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::backend
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:29
 * @route '/layout-demo/backend'
 */
backend.url = (options?: RouteQueryOptions) => {
    return backend.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::backend
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:29
 * @route '/layout-demo/backend'
 */
backend.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: backend.url(options),
    method: 'get',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::backend
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:29
 * @route '/layout-demo/backend'
 */
backend.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: backend.url(options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::backend
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:29
 * @route '/layout-demo/backend'
 */
    const backendForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: backend.url(options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::backend
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:29
 * @route '/layout-demo/backend'
 */
        backendForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: backend.url(options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\LayoutDemoController::backend
 * @see engine/modules/Demo/app/Http/Controllers/LayoutDemoController.php:29
 * @route '/layout-demo/backend'
 */
        backendForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: backend.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    backend.form = backendForm
const layoutDemo = {
    frontend: Object.assign(frontend, frontend),
auth: Object.assign(auth, auth),
fullscreen: Object.assign(fullscreen, fullscreen),
bare: Object.assign(bare, bare),
gallery: Object.assign(gallery, gallery),
youtube: Object.assign(youtube, youtube),
music: Object.assign(music, music),
backend: Object.assign(backend, backend),
}

export default layoutDemo