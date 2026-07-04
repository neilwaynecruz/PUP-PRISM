import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\OperationsHealthController::__invoke
 * @see app/Http/Controllers/Admin/OperationsHealthController.php:13
 * @route '/admin/operations/health'
 */
const OperationsHealthController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: OperationsHealthController.url(options),
    method: 'get',
})

OperationsHealthController.definition = {
    methods: ["get","head"],
    url: '/admin/operations/health',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\OperationsHealthController::__invoke
 * @see app/Http/Controllers/Admin/OperationsHealthController.php:13
 * @route '/admin/operations/health'
 */
OperationsHealthController.url = (options?: RouteQueryOptions) => {
    return OperationsHealthController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\OperationsHealthController::__invoke
 * @see app/Http/Controllers/Admin/OperationsHealthController.php:13
 * @route '/admin/operations/health'
 */
OperationsHealthController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: OperationsHealthController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\OperationsHealthController::__invoke
 * @see app/Http/Controllers/Admin/OperationsHealthController.php:13
 * @route '/admin/operations/health'
 */
OperationsHealthController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: OperationsHealthController.url(options),
    method: 'head',
})
export default OperationsHealthController