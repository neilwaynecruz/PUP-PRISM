import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import operations from './operations'
import users from './users'
import departments from './departments'
import positions from './positions'
import categories from './categories'
import origins from './origins'
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
const admin = {
    health: Object.assign(health, health),
operations: Object.assign(operations, operations),
users: Object.assign(users, users),
departments: Object.assign(departments, departments),
positions: Object.assign(positions, positions),
categories: Object.assign(categories, categories),
origins: Object.assign(origins, origins),
alerts: Object.assign(alerts, alerts),
}

export default admin