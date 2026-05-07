import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::index
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:11
 * @route '/api/v1/auths'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/v1/auths',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::index
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:11
 * @route '/api/v1/auths'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::index
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:11
 * @route '/api/v1/auths'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::index
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:11
 * @route '/api/v1/auths'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::index
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:11
 * @route '/api/v1/auths'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::index
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:11
 * @route '/api/v1/auths'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::index
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:11
 * @route '/api/v1/auths'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::store
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:21
 * @route '/api/v1/auths'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/v1/auths',
} satisfies RouteDefinition<["post"]>

/**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::store
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:21
 * @route '/api/v1/auths'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::store
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:21
 * @route '/api/v1/auths'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::store
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:21
 * @route '/api/v1/auths'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::store
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:21
 * @route '/api/v1/auths'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::show
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:23
 * @route '/api/v1/auths/{auth}'
 */
export const show = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/v1/auths/{auth}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::show
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:23
 * @route '/api/v1/auths/{auth}'
 */
show.url = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { auth: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    auth: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        auth: args.auth,
                }

    return show.definition.url
            .replace('{auth}', parsedArgs.auth.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::show
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:23
 * @route '/api/v1/auths/{auth}'
 */
show.get = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::show
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:23
 * @route '/api/v1/auths/{auth}'
 */
show.head = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::show
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:23
 * @route '/api/v1/auths/{auth}'
 */
    const showForm = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::show
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:23
 * @route '/api/v1/auths/{auth}'
 */
        showForm.get = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::show
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:23
 * @route '/api/v1/auths/{auth}'
 */
        showForm.head = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::update
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:33
 * @route '/api/v1/auths/{auth}'
 */
export const update = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/api/v1/auths/{auth}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::update
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:33
 * @route '/api/v1/auths/{auth}'
 */
update.url = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { auth: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    auth: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        auth: args.auth,
                }

    return update.definition.url
            .replace('{auth}', parsedArgs.auth.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::update
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:33
 * @route '/api/v1/auths/{auth}'
 */
update.put = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
/**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::update
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:33
 * @route '/api/v1/auths/{auth}'
 */
update.patch = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::update
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:33
 * @route '/api/v1/auths/{auth}'
 */
    const updateForm = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::update
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:33
 * @route '/api/v1/auths/{auth}'
 */
        updateForm.put = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::update
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:33
 * @route '/api/v1/auths/{auth}'
 */
        updateForm.patch = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::destroy
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:35
 * @route '/api/v1/auths/{auth}'
 */
export const destroy = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/v1/auths/{auth}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::destroy
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:35
 * @route '/api/v1/auths/{auth}'
 */
destroy.url = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { auth: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    auth: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        auth: args.auth,
                }

    return destroy.definition.url
            .replace('{auth}', parsedArgs.auth.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::destroy
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:35
 * @route '/api/v1/auths/{auth}'
 */
destroy.delete = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::destroy
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:35
 * @route '/api/v1/auths/{auth}'
 */
    const destroyForm = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \Engine\Modules\Auth\Http\Controllers\AuthController::destroy
 * @see engine/modules/Auth/app/Http/Controllers/AuthController.php:35
 * @route '/api/v1/auths/{auth}'
 */
        destroyForm.delete = (args: { auth: string | number } | [auth: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const auth = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
show: Object.assign(show, show),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
}

export default auth