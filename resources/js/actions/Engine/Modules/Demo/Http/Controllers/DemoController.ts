import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/demos'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/demos',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/demos'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/demos'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/demos'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/demos'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/demos'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::index
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:11
 * @route '/demos'
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
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::store
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:21
 * @route '/demos'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/demos',
} satisfies RouteDefinition<["post"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::store
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:21
 * @route '/demos'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::store
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:21
 * @route '/demos'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::store
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:21
 * @route '/demos'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::store
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:21
 * @route '/demos'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/demos/{demo}'
 */
export const show = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/demos/{demo}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/demos/{demo}'
 */
show.url = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{demo}', parsedArgs.demo.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/demos/{demo}'
 */
show.get = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/demos/{demo}'
 */
show.head = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/demos/{demo}'
 */
    const showForm = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/demos/{demo}'
 */
        showForm.get = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::show
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:23
 * @route '/demos/{demo}'
 */
        showForm.head = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/demos/{demo}'
 */
export const update = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/demos/{demo}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/demos/{demo}'
 */
update.url = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{demo}', parsedArgs.demo.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/demos/{demo}'
 */
update.put = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/demos/{demo}'
 */
update.patch = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::update
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:33
 * @route '/demos/{demo}'
 */
    const updateForm = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
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
        updateForm.put = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
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
        updateForm.patch = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::destroy
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:35
 * @route '/demos/{demo}'
 */
export const destroy = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/demos/{demo}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::destroy
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:35
 * @route '/demos/{demo}'
 */
destroy.url = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{demo}', parsedArgs.demo.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::destroy
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:35
 * @route '/demos/{demo}'
 */
destroy.delete = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\DemoController::destroy
 * @see engine/modules/Demo/app/Http/Controllers/DemoController.php:35
 * @route '/demos/{demo}'
 */
    const destroyForm = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
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
        destroyForm.delete = (args: { demo: string | number } | [demo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const DemoController = { index, create, store, show, edit, update, destroy }

export default DemoController