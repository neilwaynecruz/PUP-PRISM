import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
import operations from './operations'
import users from './users'
import alerts from './alerts'
/**
* @see \App\Http\Controllers\Admin\HealthController::__invoke
 * @see app/Http/Controllers/Admin/HealthController.php:12
 * @route '/admin/health'
 */
export const health = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: health.url(options),
    method: 'get',
})

health.definition = {
    methods: ["get","head"],
    url: '/admin/health',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\HealthController::__invoke
 * @see app/Http/Controllers/Admin/HealthController.php:12
 * @route '/admin/health'
 */
health.url = (options?: RouteQueryOptions) => {
    return health.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\HealthController::__invoke
 * @see app/Http/Controllers/Admin/HealthController.php:12
 * @route '/admin/health'
 */
health.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: health.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\HealthController::__invoke
 * @see app/Http/Controllers/Admin/HealthController.php:12
 * @route '/admin/health'
 */
health.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: health.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\HealthController::__invoke
 * @see app/Http/Controllers/Admin/HealthController.php:12
 * @route '/admin/health'
 */
    const healthForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: health.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\HealthController::__invoke
 * @see app/Http/Controllers/Admin/HealthController.php:12
 * @route '/admin/health'
 */
        healthForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: health.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\HealthController::__invoke
 * @see app/Http/Controllers/Admin/HealthController.php:12
 * @route '/admin/health'
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
const admin = {
    health: Object.assign(health, health),
operations: Object.assign(operations, operations),
users: Object.assign(users, users),
alerts: Object.assign(alerts, alerts),
}

export default admin