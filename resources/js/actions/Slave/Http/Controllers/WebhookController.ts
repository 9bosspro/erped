import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \Slave\Http\Controllers\WebhookController::__invoke
 * @see engine/slave/src/Http/Controllers/WebhookController.php:21
 * @route '/slave/webhook'
 */
const WebhookController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: WebhookController.url(options),
    method: 'post',
})

WebhookController.definition = {
    methods: ["post"],
    url: '/slave/webhook',
} satisfies RouteDefinition<["post"]>

/**
* @see \Slave\Http\Controllers\WebhookController::__invoke
 * @see engine/slave/src/Http/Controllers/WebhookController.php:21
 * @route '/slave/webhook'
 */
WebhookController.url = (options?: RouteQueryOptions) => {
    return WebhookController.definition.url + queryParams(options)
}

/**
* @see \Slave\Http\Controllers\WebhookController::__invoke
 * @see engine/slave/src/Http/Controllers/WebhookController.php:21
 * @route '/slave/webhook'
 */
WebhookController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: WebhookController.url(options),
    method: 'post',
})

    /**
* @see \Slave\Http\Controllers\WebhookController::__invoke
 * @see engine/slave/src/Http/Controllers/WebhookController.php:21
 * @route '/slave/webhook'
 */
    const WebhookControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: WebhookController.url(options),
        method: 'post',
    })

            /**
* @see \Slave\Http\Controllers\WebhookController::__invoke
 * @see engine/slave/src/Http/Controllers/WebhookController.php:21
 * @route '/slave/webhook'
 */
        WebhookControllerForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: WebhookController.url(options),
            method: 'post',
        })
    
    WebhookController.form = WebhookControllerForm
export default WebhookController