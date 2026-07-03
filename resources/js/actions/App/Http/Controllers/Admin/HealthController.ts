import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\HealthController::__invoke
 * @see app/Http/Controllers/Admin/HealthController.php:12
 * @route '/admin/health'
 */
const HealthController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: HealthController.url(options),
    method: 'get',
})

HealthController.definition = {
    methods: ["get","head"],
    url: '/admin/health',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\HealthController::__invoke
 * @see app/Http/Controllers/Admin/HealthController.php:12
 * @route '/admin/health'
 */
HealthController.url = (options?: RouteQueryOptions) => {
    return HealthController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\HealthController::__invoke
 * @see app/Http/Controllers/Admin/HealthController.php:12
 * @route '/admin/health'
 */
HealthController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: HealthController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\HealthController::__invoke
 * @see app/Http/Controllers/Admin/HealthController.php:12
 * @route '/admin/health'
 */
HealthController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: HealthController.url(options),
    method: 'head',
})
export default HealthController