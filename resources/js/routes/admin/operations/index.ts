import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\OperationsHealthController::__invoke
 * @see app/Http/Controllers/Admin/OperationsHealthController.php:13
 * @route '/admin/operations/health'
 */
export const health = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: health.url(options),
    method: 'get',
})

health.definition = {
    methods: ["get","head"],
    url: '/admin/operations/health',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\OperationsHealthController::__invoke
 * @see app/Http/Controllers/Admin/OperationsHealthController.php:13
 * @route '/admin/operations/health'
 */
health.url = (options?: RouteQueryOptions) => {
    return health.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\OperationsHealthController::__invoke
 * @see app/Http/Controllers/Admin/OperationsHealthController.php:13
 * @route '/admin/operations/health'
 */
health.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: health.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\OperationsHealthController::__invoke
 * @see app/Http/Controllers/Admin/OperationsHealthController.php:13
 * @route '/admin/operations/health'
 */
health.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: health.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\OperationsHealthController::__invoke
 * @see app/Http/Controllers/Admin/OperationsHealthController.php:13
 * @route '/admin/operations/health'
 */
    const healthForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: health.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\OperationsHealthController::__invoke
 * @see app/Http/Controllers/Admin/OperationsHealthController.php:13
 * @route '/admin/operations/health'
 */
        healthForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: health.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\OperationsHealthController::__invoke
 * @see app/Http/Controllers/Admin/OperationsHealthController.php:13
 * @route '/admin/operations/health'
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
const operations = {
    health: Object.assign(health, health),
}

export default operations