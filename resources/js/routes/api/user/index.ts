import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \Engine\Modules\User\Http\Controllers\UserController::index
 * @see engine/modules/User/app/Http/Controllers/UserController.php:15
 * @route '/api/v1/users'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/v1/users',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\User\Http\Controllers\UserController::index
 * @see engine/modules/User/app/Http/Controllers/UserController.php:15
 * @route '/api/v1/users'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\User\Http\Controllers\UserController::index
 * @see engine/modules/User/app/Http/Controllers/UserController.php:15
 * @route '/api/v1/users'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \Engine\Modules\User\Http\Controllers\UserController::index
 * @see engine/modules/User/app/Http/Controllers/UserController.php:15
 * @route '/api/v1/users'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \Engine\Modules\User\Http\Controllers\UserController::index
 * @see engine/modules/User/app/Http/Controllers/UserController.php:15
 * @route '/api/v1/users'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\User\Http\Controllers\UserController::index
 * @see engine/modules/User/app/Http/Controllers/UserController.php:15
 * @route '/api/v1/users'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\User\Http\Controllers\UserController::index
 * @see engine/modules/User/app/Http/Controllers/UserController.php:15
 * @route '/api/v1/users'
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
* @see \Engine\Modules\User\Http\Controllers\UserController::store
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/v1/users',
} satisfies RouteDefinition<["post"]>

/**
* @see \Engine\Modules\User\Http\Controllers\UserController::store
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\User\Http\Controllers\UserController::store
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \Engine\Modules\User\Http\Controllers\UserController::store
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \Engine\Modules\User\Http\Controllers\UserController::store
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \Engine\Modules\User\Http\Controllers\UserController::show
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
export const show = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/v1/users/{user}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\User\Http\Controllers\UserController::show
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
show.url = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    user: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        user: args.user,
                }

    return show.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Engine\Modules\User\Http\Controllers\UserController::show
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
show.get = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \Engine\Modules\User\Http\Controllers\UserController::show
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
show.head = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \Engine\Modules\User\Http\Controllers\UserController::show
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
    const showForm = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\User\Http\Controllers\UserController::show
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
        showForm.get = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\User\Http\Controllers\UserController::show
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
        showForm.head = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \Engine\Modules\User\Http\Controllers\UserController::update
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
export const update = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/api/v1/users/{user}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \Engine\Modules\User\Http\Controllers\UserController::update
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
update.url = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    user: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        user: args.user,
                }

    return update.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Engine\Modules\User\Http\Controllers\UserController::update
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
update.put = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
/**
* @see \Engine\Modules\User\Http\Controllers\UserController::update
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
update.patch = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \Engine\Modules\User\Http\Controllers\UserController::update
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
    const updateForm = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \Engine\Modules\User\Http\Controllers\UserController::update
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
        updateForm.put = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \Engine\Modules\User\Http\Controllers\UserController::update
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
        updateForm.patch = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \Engine\Modules\User\Http\Controllers\UserController::destroy
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
export const destroy = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/v1/users/{user}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \Engine\Modules\User\Http\Controllers\UserController::destroy
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
destroy.url = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    user: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        user: args.user,
                }

    return destroy.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Engine\Modules\User\Http\Controllers\UserController::destroy
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
destroy.delete = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \Engine\Modules\User\Http\Controllers\UserController::destroy
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
    const destroyForm = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \Engine\Modules\User\Http\Controllers\UserController::destroy
 * @see engine/modules/User/app/Http/Controllers/UserController.php:0
 * @route '/api/v1/users/{user}'
 */
        destroyForm.delete = (args: { user: string | number } | [user: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const user = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
show: Object.assign(show, show),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
}

export default user