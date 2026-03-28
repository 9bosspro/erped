import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\BackendProxyController::get
 * @see app/Http/Controllers/Api/BackendProxyController.php:27
 * @route '/api/v1/proxy/{endpoint}'
 */
export const get = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: get.url(args, options),
    method: 'get',
})

get.definition = {
    methods: ["get","head"],
    url: '/api/v1/proxy/{endpoint}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\BackendProxyController::get
 * @see app/Http/Controllers/Api/BackendProxyController.php:27
 * @route '/api/v1/proxy/{endpoint}'
 */
get.url = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { endpoint: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    endpoint: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        endpoint: args.endpoint,
                }

    return get.definition.url
            .replace('{endpoint}', parsedArgs.endpoint.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\BackendProxyController::get
 * @see app/Http/Controllers/Api/BackendProxyController.php:27
 * @route '/api/v1/proxy/{endpoint}'
 */
get.get = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: get.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Api\BackendProxyController::get
 * @see app/Http/Controllers/Api/BackendProxyController.php:27
 * @route '/api/v1/proxy/{endpoint}'
 */
get.head = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: get.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Api\BackendProxyController::get
 * @see app/Http/Controllers/Api/BackendProxyController.php:27
 * @route '/api/v1/proxy/{endpoint}'
 */
    const getForm = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: get.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Api\BackendProxyController::get
 * @see app/Http/Controllers/Api/BackendProxyController.php:27
 * @route '/api/v1/proxy/{endpoint}'
 */
        getForm.get = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: get.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Api\BackendProxyController::get
 * @see app/Http/Controllers/Api/BackendProxyController.php:27
 * @route '/api/v1/proxy/{endpoint}'
 */
        getForm.head = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: get.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    get.form = getForm
/**
* @see \App\Http\Controllers\Api\BackendProxyController::post
 * @see app/Http/Controllers/Api/BackendProxyController.php:42
 * @route '/api/v1/proxy/{endpoint}'
 */
export const post = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: post.url(args, options),
    method: 'post',
})

post.definition = {
    methods: ["post"],
    url: '/api/v1/proxy/{endpoint}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\BackendProxyController::post
 * @see app/Http/Controllers/Api/BackendProxyController.php:42
 * @route '/api/v1/proxy/{endpoint}'
 */
post.url = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { endpoint: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    endpoint: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        endpoint: args.endpoint,
                }

    return post.definition.url
            .replace('{endpoint}', parsedArgs.endpoint.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\BackendProxyController::post
 * @see app/Http/Controllers/Api/BackendProxyController.php:42
 * @route '/api/v1/proxy/{endpoint}'
 */
post.post = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: post.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Api\BackendProxyController::post
 * @see app/Http/Controllers/Api/BackendProxyController.php:42
 * @route '/api/v1/proxy/{endpoint}'
 */
    const postForm = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: post.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\BackendProxyController::post
 * @see app/Http/Controllers/Api/BackendProxyController.php:42
 * @route '/api/v1/proxy/{endpoint}'
 */
        postForm.post = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: post.url(args, options),
            method: 'post',
        })
    
    post.form = postForm
/**
* @see \App\Http\Controllers\Api\BackendProxyController::put
 * @see app/Http/Controllers/Api/BackendProxyController.php:57
 * @route '/api/v1/proxy/{endpoint}'
 */
export const put = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: put.url(args, options),
    method: 'put',
})

put.definition = {
    methods: ["put"],
    url: '/api/v1/proxy/{endpoint}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Api\BackendProxyController::put
 * @see app/Http/Controllers/Api/BackendProxyController.php:57
 * @route '/api/v1/proxy/{endpoint}'
 */
put.url = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { endpoint: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    endpoint: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        endpoint: args.endpoint,
                }

    return put.definition.url
            .replace('{endpoint}', parsedArgs.endpoint.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\BackendProxyController::put
 * @see app/Http/Controllers/Api/BackendProxyController.php:57
 * @route '/api/v1/proxy/{endpoint}'
 */
put.put = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: put.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Api\BackendProxyController::put
 * @see app/Http/Controllers/Api/BackendProxyController.php:57
 * @route '/api/v1/proxy/{endpoint}'
 */
    const putForm = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: put.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\BackendProxyController::put
 * @see app/Http/Controllers/Api/BackendProxyController.php:57
 * @route '/api/v1/proxy/{endpoint}'
 */
        putForm.put = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: put.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    put.form = putForm
/**
* @see \App\Http\Controllers\Api\BackendProxyController::deleteMethod
 * @see app/Http/Controllers/Api/BackendProxyController.php:72
 * @route '/api/v1/proxy/{endpoint}'
 */
export const deleteMethod = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

deleteMethod.definition = {
    methods: ["delete"],
    url: '/api/v1/proxy/{endpoint}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\BackendProxyController::deleteMethod
 * @see app/Http/Controllers/Api/BackendProxyController.php:72
 * @route '/api/v1/proxy/{endpoint}'
 */
deleteMethod.url = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { endpoint: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    endpoint: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        endpoint: args.endpoint,
                }

    return deleteMethod.definition.url
            .replace('{endpoint}', parsedArgs.endpoint.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\BackendProxyController::deleteMethod
 * @see app/Http/Controllers/Api/BackendProxyController.php:72
 * @route '/api/v1/proxy/{endpoint}'
 */
deleteMethod.delete = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Api\BackendProxyController::deleteMethod
 * @see app/Http/Controllers/Api/BackendProxyController.php:72
 * @route '/api/v1/proxy/{endpoint}'
 */
    const deleteMethodForm = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: deleteMethod.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\BackendProxyController::deleteMethod
 * @see app/Http/Controllers/Api/BackendProxyController.php:72
 * @route '/api/v1/proxy/{endpoint}'
 */
        deleteMethodForm.delete = (args: { endpoint: string | number } | [endpoint: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: deleteMethod.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    deleteMethod.form = deleteMethodForm
const BackendProxyController = { get, post, put, deleteMethod, delete: deleteMethod }

export default BackendProxyController