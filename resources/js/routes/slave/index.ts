import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
 * @see [serialized-closure]:2
 * @route '/slave/health'
 */
export const health = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: health.url(options),
    method: 'get',
})

health.definition = {
    methods: ["get","head"],
    url: '/slave/health',
} satisfies RouteDefinition<["get","head"]>

/**
 * @see [serialized-closure]:2
 * @route '/slave/health'
 */
health.url = (options?: RouteQueryOptions) => {
    return health.definition.url + queryParams(options)
}

/**
 * @see [serialized-closure]:2
 * @route '/slave/health'
 */
health.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: health.url(options),
    method: 'get',
})
/**
 * @see [serialized-closure]:2
 * @route '/slave/health'
 */
health.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: health.url(options),
    method: 'head',
})

    /**
 * @see [serialized-closure]:2
 * @route '/slave/health'
 */
    const healthForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: health.url(options),
        method: 'get',
    })

            /**
 * @see [serialized-closure]:2
 * @route '/slave/health'
 */
        healthForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: health.url(options),
            method: 'get',
        })
            /**
 * @see [serialized-closure]:2
 * @route '/slave/health'
 */
        healthForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: health.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    health.form = healthForm
/**
* @see \Slave\Http\Controllers\WebhookController::__invoke
 * @see engine/slave/src/Http/Controllers/WebhookController.php:21
 * @route '/slave/webhook'
 */
export const webhook = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: webhook.url(options),
    method: 'post',
})

webhook.definition = {
    methods: ["post"],
    url: '/slave/webhook',
} satisfies RouteDefinition<["post"]>

/**
* @see \Slave\Http\Controllers\WebhookController::__invoke
 * @see engine/slave/src/Http/Controllers/WebhookController.php:21
 * @route '/slave/webhook'
 */
webhook.url = (options?: RouteQueryOptions) => {
    return webhook.definition.url + queryParams(options)
}

/**
* @see \Slave\Http\Controllers\WebhookController::__invoke
 * @see engine/slave/src/Http/Controllers/WebhookController.php:21
 * @route '/slave/webhook'
 */
webhook.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: webhook.url(options),
    method: 'post',
})

    /**
* @see \Slave\Http\Controllers\WebhookController::__invoke
 * @see engine/slave/src/Http/Controllers/WebhookController.php:21
 * @route '/slave/webhook'
 */
    const webhookForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: webhook.url(options),
        method: 'post',
    })

            /**
* @see \Slave\Http\Controllers\WebhookController::__invoke
 * @see engine/slave/src/Http/Controllers/WebhookController.php:21
 * @route '/slave/webhook'
 */
        webhookForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: webhook.url(options),
            method: 'post',
        })
    
    webhook.form = webhookForm
const slave = {
    health: Object.assign(health, health),
webhook: Object.assign(webhook, webhook),
}

export default slave