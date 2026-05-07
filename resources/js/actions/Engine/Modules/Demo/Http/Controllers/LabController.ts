import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../../wayfinder'
/**
* @see \Engine\Modules\Demo\Http\Controllers\LabController::index
 * @see engine/modules/Demo/app/Http/Controllers/LabController.php:29
 * @route '/demos/lab'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/demos/lab',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\LabController::index
 * @see engine/modules/Demo/app/Http/Controllers/LabController.php:29
 * @route '/demos/lab'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\LabController::index
 * @see engine/modules/Demo/app/Http/Controllers/LabController.php:29
 * @route '/demos/lab'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\LabController::index
 * @see engine/modules/Demo/app/Http/Controllers/LabController.php:29
 * @route '/demos/lab'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\LabController::index
 * @see engine/modules/Demo/app/Http/Controllers/LabController.php:29
 * @route '/demos/lab'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\LabController::index
 * @see engine/modules/Demo/app/Http/Controllers/LabController.php:29
 * @route '/demos/lab'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\LabController::index
 * @see engine/modules/Demo/app/Http/Controllers/LabController.php:29
 * @route '/demos/lab'
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
* @see \Engine\Modules\Demo\Http\Controllers\LabController::lab1
 * @see engine/modules/Demo/app/Http/Controllers/LabController.php:40
 * @route '/demos/lab/lab1'
 */
export const lab1 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: lab1.url(options),
    method: 'get',
})

lab1.definition = {
    methods: ["get","head"],
    url: '/demos/lab/lab1',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Engine\Modules\Demo\Http\Controllers\LabController::lab1
 * @see engine/modules/Demo/app/Http/Controllers/LabController.php:40
 * @route '/demos/lab/lab1'
 */
lab1.url = (options?: RouteQueryOptions) => {
    return lab1.definition.url + queryParams(options)
}

/**
* @see \Engine\Modules\Demo\Http\Controllers\LabController::lab1
 * @see engine/modules/Demo/app/Http/Controllers/LabController.php:40
 * @route '/demos/lab/lab1'
 */
lab1.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: lab1.url(options),
    method: 'get',
})
/**
* @see \Engine\Modules\Demo\Http\Controllers\LabController::lab1
 * @see engine/modules/Demo/app/Http/Controllers/LabController.php:40
 * @route '/demos/lab/lab1'
 */
lab1.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: lab1.url(options),
    method: 'head',
})

    /**
* @see \Engine\Modules\Demo\Http\Controllers\LabController::lab1
 * @see engine/modules/Demo/app/Http/Controllers/LabController.php:40
 * @route '/demos/lab/lab1'
 */
    const lab1Form = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: lab1.url(options),
        method: 'get',
    })

            /**
* @see \Engine\Modules\Demo\Http\Controllers\LabController::lab1
 * @see engine/modules/Demo/app/Http/Controllers/LabController.php:40
 * @route '/demos/lab/lab1'
 */
        lab1Form.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: lab1.url(options),
            method: 'get',
        })
            /**
* @see \Engine\Modules\Demo\Http\Controllers\LabController::lab1
 * @see engine/modules/Demo/app/Http/Controllers/LabController.php:40
 * @route '/demos/lab/lab1'
 */
        lab1Form.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: lab1.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    lab1.form = lab1Form
const LabController = { index, lab1 }

export default LabController