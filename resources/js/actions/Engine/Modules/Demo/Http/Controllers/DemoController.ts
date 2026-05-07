import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/api/v1/demos'
 */
const indexdb14f13bca2bc03aa328ae12597a9393 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: indexdb14f13bca2bc03aa328ae12597a9393.url(options),
    method: 'get',
})

indexdb14f13bca2bc03aa328ae12597a9393.definition = {
    methods: ["get","head"],
    url: '/api/v1/demos',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/api/v1/demos'
 */
indexdb14f13bca2bc03aa328ae12597a9393.url = (options?: RouteQueryOptions) => {
    return indexdb14f13bca2bc03aa328ae12597a9393.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/api/v1/demos'
 */
indexdb14f13bca2bc03aa328ae12597a9393.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: indexdb14f13bca2bc03aa328ae12597a9393.url(options),
    method: 'get',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/api/v1/demos'
 */
indexdb14f13bca2bc03aa328ae12597a9393.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: indexdb14f13bca2bc03aa328ae12597a9393.url(options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/api/v1/demos'
 */
    const indexdb14f13bca2bc03aa328ae12597a9393Form = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: indexdb14f13bca2bc03aa328ae12597a9393.url(options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/api/v1/demos'
 */
        indexdb14f13bca2bc03aa328ae12597a9393Form.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: indexdb14f13bca2bc03aa328ae12597a9393.url(options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/api/v1/demos'
 */
        indexdb14f13bca2bc03aa328ae12597a9393Form.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: indexdb14f13bca2bc03aa328ae12597a9393.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    indexdb14f13bca2bc03aa328ae12597a9393.form = indexdb14f13bca2bc03aa328ae12597a9393Form
    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/demos'
 */
const index3d1542a67bdf7611c4a89a8c3ce89570 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index3d1542a67bdf7611c4a89a8c3ce89570.url(options),
    method: 'get',
})

index3d1542a67bdf7611c4a89a8c3ce89570.definition = {
    methods: ["get","head"],
    url: '/demos',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/demos'
 */
index3d1542a67bdf7611c4a89a8c3ce89570.url = (options?: RouteQueryOptions) => {
    return index3d1542a67bdf7611c4a89a8c3ce89570.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/demos'
 */
index3d1542a67bdf7611c4a89a8c3ce89570.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index3d1542a67bdf7611c4a89a8c3ce89570.url(options),
    method: 'get',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/demos'
 */
index3d1542a67bdf7611c4a89a8c3ce89570.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index3d1542a67bdf7611c4a89a8c3ce89570.url(options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/demos'
 */
    const index3d1542a67bdf7611c4a89a8c3ce89570Form = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index3d1542a67bdf7611c4a89a8c3ce89570.url(options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/demos'
 */
        index3d1542a67bdf7611c4a89a8c3ce89570Form.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index3d1542a67bdf7611c4a89a8c3ce89570.url(options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/demos'
 */
        index3d1542a67bdf7611c4a89a8c3ce89570Form.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index3d1542a67bdf7611c4a89a8c3ce89570.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index3d1542a67bdf7611c4a89a8c3ce89570.form = index3d1542a67bdf7611c4a89a8c3ce89570Form

export const index = {
    '/api/v1/demos': indexdb14f13bca2bc03aa328ae12597a9393,
    '/demos': index3d1542a67bdf7611c4a89a8c3ce89570,
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::store
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:21
 * @route '/api/v1/demos'
 */
const storedb14f13bca2bc03aa328ae12597a9393 = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storedb14f13bca2bc03aa328ae12597a9393.url(options),
    method: 'post',
})

storedb14f13bca2bc03aa328ae12597a9393.definition = {
    methods: ["post"],
    url: '/api/v1/demos',
} satisfies RouteDefinition<["post"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::store
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:21
 * @route '/api/v1/demos'
 */
storedb14f13bca2bc03aa328ae12597a9393.url = (options?: RouteQueryOptions) => {
    return storedb14f13bca2bc03aa328ae12597a9393.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::store
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:21
 * @route '/api/v1/demos'
 */
storedb14f13bca2bc03aa328ae12597a9393.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storedb14f13bca2bc03aa328ae12597a9393.url(options),
    method: 'post',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::store
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:21
 * @route '/api/v1/demos'
 */
    const storedb14f13bca2bc03aa328ae12597a9393Form = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: storedb14f13bca2bc03aa328ae12597a9393.url(options),
        method: 'post',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::store
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:21
 * @route '/api/v1/demos'
 */
        storedb14f13bca2bc03aa328ae12597a9393Form.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: storedb14f13bca2bc03aa328ae12597a9393.url(options),
            method: 'post',
        })
    
    storedb14f13bca2bc03aa328ae12597a9393.form = storedb14f13bca2bc03aa328ae12597a9393Form
    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::store
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:21
 * @route '/demos'
 */
const store3d1542a67bdf7611c4a89a8c3ce89570 = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store3d1542a67bdf7611c4a89a8c3ce89570.url(options),
    method: 'post',
})

store3d1542a67bdf7611c4a89a8c3ce89570.definition = {
    methods: ["post"],
    url: '/demos',
} satisfies RouteDefinition<["post"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::store
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:21
 * @route '/demos'
 */
store3d1542a67bdf7611c4a89a8c3ce89570.url = (options?: RouteQueryOptions) => {
    return store3d1542a67bdf7611c4a89a8c3ce89570.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::store
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:21
 * @route '/demos'
 */
store3d1542a67bdf7611c4a89a8c3ce89570.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store3d1542a67bdf7611c4a89a8c3ce89570.url(options),
    method: 'post',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::store
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:21
 * @route '/demos'
 */
    const store3d1542a67bdf7611c4a89a8c3ce89570Form = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store3d1542a67bdf7611c4a89a8c3ce89570.url(options),
        method: 'post',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::store
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:21
 * @route '/demos'
 */
        store3d1542a67bdf7611c4a89a8c3ce89570Form.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store3d1542a67bdf7611c4a89a8c3ce89570.url(options),
            method: 'post',
        })
    
    store3d1542a67bdf7611c4a89a8c3ce89570.form = store3d1542a67bdf7611c4a89a8c3ce89570Form

export const store = {
    '/api/v1/demos': storedb14f13bca2bc03aa328ae12597a9393,
    '/demos': store3d1542a67bdf7611c4a89a8c3ce89570,
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/api/v1/demos/{demo}'
 */
const showf8f53ed1e35ab3b0f4db500b96a7e787 = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showf8f53ed1e35ab3b0f4db500b96a7e787.url(args, options),
    method: 'get',
})

showf8f53ed1e35ab3b0f4db500b96a7e787.definition = {
    methods: ["get","head"],
    url: '/api/v1/demos/{demo}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/api/v1/demos/{demo}'
 */
showf8f53ed1e35ab3b0f4db500b96a7e787.url = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { demo: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    demo: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        demo: args.demo,
                }

    return showf8f53ed1e35ab3b0f4db500b96a7e787.definition.url
            .replace('{demo}', parsedArgs.demo.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/api/v1/demos/{demo}'
 */
showf8f53ed1e35ab3b0f4db500b96a7e787.get = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showf8f53ed1e35ab3b0f4db500b96a7e787.url(args, options),
    method: 'get',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/api/v1/demos/{demo}'
 */
showf8f53ed1e35ab3b0f4db500b96a7e787.head = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showf8f53ed1e35ab3b0f4db500b96a7e787.url(args, options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/api/v1/demos/{demo}'
 */
    const showf8f53ed1e35ab3b0f4db500b96a7e787Form = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: showf8f53ed1e35ab3b0f4db500b96a7e787.url(args, options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/api/v1/demos/{demo}'
 */
        showf8f53ed1e35ab3b0f4db500b96a7e787Form.get = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: showf8f53ed1e35ab3b0f4db500b96a7e787.url(args, options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/api/v1/demos/{demo}'
 */
        showf8f53ed1e35ab3b0f4db500b96a7e787Form.head = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: showf8f53ed1e35ab3b0f4db500b96a7e787.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    showf8f53ed1e35ab3b0f4db500b96a7e787.form = showf8f53ed1e35ab3b0f4db500b96a7e787Form
    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/demos/{demo}'
 */
const showe190a59365bf83ce8072297416111090 = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showe190a59365bf83ce8072297416111090.url(args, options),
    method: 'get',
})

showe190a59365bf83ce8072297416111090.definition = {
    methods: ["get","head"],
    url: '/demos/{demo}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/demos/{demo}'
 */
showe190a59365bf83ce8072297416111090.url = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { demo: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    demo: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        demo: args.demo,
                }

    return showe190a59365bf83ce8072297416111090.definition.url
            .replace('{demo}', parsedArgs.demo.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/demos/{demo}'
 */
showe190a59365bf83ce8072297416111090.get = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showe190a59365bf83ce8072297416111090.url(args, options),
    method: 'get',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/demos/{demo}'
 */
showe190a59365bf83ce8072297416111090.head = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showe190a59365bf83ce8072297416111090.url(args, options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/demos/{demo}'
 */
    const showe190a59365bf83ce8072297416111090Form = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: showe190a59365bf83ce8072297416111090.url(args, options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/demos/{demo}'
 */
        showe190a59365bf83ce8072297416111090Form.get = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: showe190a59365bf83ce8072297416111090.url(args, options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/demos/{demo}'
 */
        showe190a59365bf83ce8072297416111090Form.head = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: showe190a59365bf83ce8072297416111090.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    showe190a59365bf83ce8072297416111090.form = showe190a59365bf83ce8072297416111090Form

export const show = {
    '/api/v1/demos/{demo}': showf8f53ed1e35ab3b0f4db500b96a7e787,
    '/demos/{demo}': showe190a59365bf83ce8072297416111090,
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/api/v1/demos/{demo}'
 */
const updatef8f53ed1e35ab3b0f4db500b96a7e787 = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updatef8f53ed1e35ab3b0f4db500b96a7e787.url(args, options),
    method: 'put',
})

updatef8f53ed1e35ab3b0f4db500b96a7e787.definition = {
    methods: ["put","patch"],
    url: '/api/v1/demos/{demo}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/api/v1/demos/{demo}'
 */
updatef8f53ed1e35ab3b0f4db500b96a7e787.url = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { demo: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    demo: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        demo: args.demo,
                }

    return updatef8f53ed1e35ab3b0f4db500b96a7e787.definition.url
            .replace('{demo}', parsedArgs.demo.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/api/v1/demos/{demo}'
 */
updatef8f53ed1e35ab3b0f4db500b96a7e787.put = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updatef8f53ed1e35ab3b0f4db500b96a7e787.url(args, options),
    method: 'put',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/api/v1/demos/{demo}'
 */
updatef8f53ed1e35ab3b0f4db500b96a7e787.patch = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updatef8f53ed1e35ab3b0f4db500b96a7e787.url(args, options),
    method: 'patch',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/api/v1/demos/{demo}'
 */
    const updatef8f53ed1e35ab3b0f4db500b96a7e787Form = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updatef8f53ed1e35ab3b0f4db500b96a7e787.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/api/v1/demos/{demo}'
 */
        updatef8f53ed1e35ab3b0f4db500b96a7e787Form.put = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updatef8f53ed1e35ab3b0f4db500b96a7e787.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/api/v1/demos/{demo}'
 */
        updatef8f53ed1e35ab3b0f4db500b96a7e787Form.patch = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updatef8f53ed1e35ab3b0f4db500b96a7e787.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updatef8f53ed1e35ab3b0f4db500b96a7e787.form = updatef8f53ed1e35ab3b0f4db500b96a7e787Form
    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/demos/{demo}'
 */
const updatee190a59365bf83ce8072297416111090 = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updatee190a59365bf83ce8072297416111090.url(args, options),
    method: 'put',
})

updatee190a59365bf83ce8072297416111090.definition = {
    methods: ["put","patch"],
    url: '/demos/{demo}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/demos/{demo}'
 */
updatee190a59365bf83ce8072297416111090.url = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { demo: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    demo: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        demo: args.demo,
                }

    return updatee190a59365bf83ce8072297416111090.definition.url
            .replace('{demo}', parsedArgs.demo.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/demos/{demo}'
 */
updatee190a59365bf83ce8072297416111090.put = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updatee190a59365bf83ce8072297416111090.url(args, options),
    method: 'put',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/demos/{demo}'
 */
updatee190a59365bf83ce8072297416111090.patch = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updatee190a59365bf83ce8072297416111090.url(args, options),
    method: 'patch',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/demos/{demo}'
 */
    const updatee190a59365bf83ce8072297416111090Form = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updatee190a59365bf83ce8072297416111090.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/demos/{demo}'
 */
        updatee190a59365bf83ce8072297416111090Form.put = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updatee190a59365bf83ce8072297416111090.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/demos/{demo}'
 */
        updatee190a59365bf83ce8072297416111090Form.patch = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updatee190a59365bf83ce8072297416111090.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updatee190a59365bf83ce8072297416111090.form = updatee190a59365bf83ce8072297416111090Form

export const update = {
    '/api/v1/demos/{demo}': updatef8f53ed1e35ab3b0f4db500b96a7e787,
    '/demos/{demo}': updatee190a59365bf83ce8072297416111090,
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::destroy
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:35
 * @route '/api/v1/demos/{demo}'
 */
const destroyf8f53ed1e35ab3b0f4db500b96a7e787 = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyf8f53ed1e35ab3b0f4db500b96a7e787.url(args, options),
    method: 'delete',
})

destroyf8f53ed1e35ab3b0f4db500b96a7e787.definition = {
    methods: ["delete"],
    url: '/api/v1/demos/{demo}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::destroy
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:35
 * @route '/api/v1/demos/{demo}'
 */
destroyf8f53ed1e35ab3b0f4db500b96a7e787.url = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { demo: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    demo: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        demo: args.demo,
                }

    return destroyf8f53ed1e35ab3b0f4db500b96a7e787.definition.url
            .replace('{demo}', parsedArgs.demo.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::destroy
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:35
 * @route '/api/v1/demos/{demo}'
 */
destroyf8f53ed1e35ab3b0f4db500b96a7e787.delete = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyf8f53ed1e35ab3b0f4db500b96a7e787.url(args, options),
    method: 'delete',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::destroy
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:35
 * @route '/api/v1/demos/{demo}'
 */
    const destroyf8f53ed1e35ab3b0f4db500b96a7e787Form = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroyf8f53ed1e35ab3b0f4db500b96a7e787.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::destroy
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:35
 * @route '/api/v1/demos/{demo}'
 */
        destroyf8f53ed1e35ab3b0f4db500b96a7e787Form.delete = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroyf8f53ed1e35ab3b0f4db500b96a7e787.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroyf8f53ed1e35ab3b0f4db500b96a7e787.form = destroyf8f53ed1e35ab3b0f4db500b96a7e787Form
    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::destroy
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:35
 * @route '/demos/{demo}'
 */
const destroye190a59365bf83ce8072297416111090 = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroye190a59365bf83ce8072297416111090.url(args, options),
    method: 'delete',
})

destroye190a59365bf83ce8072297416111090.definition = {
    methods: ["delete"],
    url: '/demos/{demo}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::destroy
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:35
 * @route '/demos/{demo}'
 */
destroye190a59365bf83ce8072297416111090.url = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { demo: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    demo: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        demo: args.demo,
                }

    return destroye190a59365bf83ce8072297416111090.definition.url
            .replace('{demo}', parsedArgs.demo.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::destroy
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:35
 * @route '/demos/{demo}'
 */
destroye190a59365bf83ce8072297416111090.delete = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroye190a59365bf83ce8072297416111090.url(args, options),
    method: 'delete',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::destroy
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:35
 * @route '/demos/{demo}'
 */
    const destroye190a59365bf83ce8072297416111090Form = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroye190a59365bf83ce8072297416111090.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::destroy
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:35
 * @route '/demos/{demo}'
 */
        destroye190a59365bf83ce8072297416111090Form.delete = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroye190a59365bf83ce8072297416111090.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroye190a59365bf83ce8072297416111090.form = destroye190a59365bf83ce8072297416111090Form

export const destroy = {
    '/api/v1/demos/{demo}': destroyf8f53ed1e35ab3b0f4db500b96a7e787,
    '/demos/{demo}': destroye190a59365bf83ce8072297416111090,
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::create
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:16
 * @route '/demos/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/demos/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::create
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:16
 * @route '/demos/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::create
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:16
 * @route '/demos/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::create
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:16
 * @route '/demos/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::create
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:16
 * @route '/demos/create'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::create
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:16
 * @route '/demos/create'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::create
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:16
 * @route '/demos/create'
 */
        createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    create.form = createForm
/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::edit
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:28
 * @route '/demos/{demo}/edit'
 */
export const edit = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/demos/{demo}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::edit
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:28
 * @route '/demos/{demo}/edit'
 */
edit.url = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { demo: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    demo: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        demo: args.demo,
                }

    return edit.definition.url
            .replace('{demo}', parsedArgs.demo.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::edit
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:28
 * @route '/demos/{demo}/edit'
 */
edit.get = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::edit
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:28
 * @route '/demos/{demo}/edit'
 */
edit.head = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::edit
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:28
 * @route '/demos/{demo}/edit'
 */
    const editForm = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: edit.url(args, options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::edit
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:28
 * @route '/demos/{demo}/edit'
 */
        editForm.get = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(args, options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::edit
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:28
 * @route '/demos/{demo}/edit'
 */
        editForm.head = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    edit.form = editForm
const DemoController = { index, store, show, update, destroy, create, edit }

export default DemoController