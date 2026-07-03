import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\NotificationController::read
 * @see app/Http/Controllers/NotificationController.php:11
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
 * @see app/Http/Controllers/NotificationController.php:11
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
 * @see app/Http/Controllers/NotificationController.php:11
 * @route '/notifications/{notification}/read'
 */
read.put = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: read.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\NotificationController::read
 * @see app/Http/Controllers/NotificationController.php:11
 * @route '/notifications/{notification}/read'
 */
    const readForm = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: read.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\NotificationController::read
 * @see app/Http/Controllers/NotificationController.php:11
 * @route '/notifications/{notification}/read'
 */
        readForm.put = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: read.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    read.form = readForm
/**
* @see \App\Http\Controllers\NotificationController::readAll
 * @see app/Http/Controllers/NotificationController.php:26
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
 * @see app/Http/Controllers/NotificationController.php:26
 * @route '/notifications/read-all'
 */
readAll.url = (options?: RouteQueryOptions) => {
    return readAll.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\NotificationController::readAll
 * @see app/Http/Controllers/NotificationController.php:26
 * @route '/notifications/read-all'
 */
readAll.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: readAll.url(options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\NotificationController::readAll
 * @see app/Http/Controllers/NotificationController.php:26
 * @route '/notifications/read-all'
 */
    const readAllForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: readAll.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\NotificationController::readAll
 * @see app/Http/Controllers/NotificationController.php:26
 * @route '/notifications/read-all'
 */
        readAllForm.put = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: readAll.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    readAll.form = readAllForm
const notifications = {
    read: Object.assign(read, read),
readAll: Object.assign(readAll, readAll),
}

export default notifications