import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\NotificationController::index
 * @see app/Http/Controllers/NotificationController.php:13
 * @route '/notifications'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/notifications',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\NotificationController::index
 * @see app/Http/Controllers/NotificationController.php:13
 * @route '/notifications'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\NotificationController::index
 * @see app/Http/Controllers/NotificationController.php:13
 * @route '/notifications'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\NotificationController::index
 * @see app/Http/Controllers/NotificationController.php:13
 * @route '/notifications'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\NotificationController::read
 * @see app/Http/Controllers/NotificationController.php:82
 * @route '/notifications/{notification}/read'
 */
export const read = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: read.url(args, options),
    method: 'put',
})

read.definition = {
    methods: ["put"],
    url: '/notifications/{notification}/read',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\NotificationController::read
 * @see app/Http/Controllers/NotificationController.php:82
 * @route '/notifications/{notification}/read'
 */
read.url = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { notification: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    notification: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        notification: args.notification,
                }

    return read.definition.url
            .replace('{notification}', parsedArgs.notification.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\NotificationController::read
 * @see app/Http/Controllers/NotificationController.php:82
 * @route '/notifications/{notification}/read'
 */
read.put = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: read.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\NotificationController::readAll
 * @see app/Http/Controllers/NotificationController.php:97
 * @route '/notifications/read-all'
 */
export const readAll = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: readAll.url(options),
    method: 'put',
})

readAll.definition = {
    methods: ["put"],
    url: '/notifications/read-all',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\NotificationController::readAll
 * @see app/Http/Controllers/NotificationController.php:97
 * @route '/notifications/read-all'
 */
readAll.url = (options?: RouteQueryOptions) => {
    return readAll.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\NotificationController::readAll
 * @see app/Http/Controllers/NotificationController.php:97
 * @route '/notifications/read-all'
 */
readAll.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: readAll.url(options),
    method: 'put',
})
const notifications = {
    index: Object.assign(index, index),
read: Object.assign(read, read),
readAll: Object.assign(readAll, readAll),
}

export default notifications