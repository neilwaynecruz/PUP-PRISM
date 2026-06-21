import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\NotificationController::markAsRead
 * @see app/Http/Controllers/NotificationController.php:11
 * @route '/notifications/{notification}/read'
 */
export const markAsRead = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: markAsRead.url(args, options),
    method: 'put',
})

markAsRead.definition = {
    methods: ["put"],
    url: '/notifications/{notification}/read',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\NotificationController::markAsRead
 * @see app/Http/Controllers/NotificationController.php:11
 * @route '/notifications/{notification}/read'
 */
markAsRead.url = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return markAsRead.definition.url
            .replace('{notification}', parsedArgs.notification.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\NotificationController::markAsRead
 * @see app/Http/Controllers/NotificationController.php:11
 * @route '/notifications/{notification}/read'
 */
markAsRead.put = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: markAsRead.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\NotificationController::markAsRead
 * @see app/Http/Controllers/NotificationController.php:11
 * @route '/notifications/{notification}/read'
 */
    const markAsReadForm = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: markAsRead.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\NotificationController::markAsRead
 * @see app/Http/Controllers/NotificationController.php:11
 * @route '/notifications/{notification}/read'
 */
        markAsReadForm.put = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: markAsRead.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    markAsRead.form = markAsReadForm
/**
* @see \App\Http\Controllers\NotificationController::markAllAsRead
 * @see app/Http/Controllers/NotificationController.php:26
 * @route '/notifications/read-all'
 */
export const markAllAsRead = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: markAllAsRead.url(options),
    method: 'put',
})

markAllAsRead.definition = {
    methods: ["put"],
    url: '/notifications/read-all',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\NotificationController::markAllAsRead
 * @see app/Http/Controllers/NotificationController.php:26
 * @route '/notifications/read-all'
 */
markAllAsRead.url = (options?: RouteQueryOptions) => {
    return markAllAsRead.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\NotificationController::markAllAsRead
 * @see app/Http/Controllers/NotificationController.php:26
 * @route '/notifications/read-all'
 */
markAllAsRead.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: markAllAsRead.url(options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\NotificationController::markAllAsRead
 * @see app/Http/Controllers/NotificationController.php:26
 * @route '/notifications/read-all'
 */
    const markAllAsReadForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: markAllAsRead.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\NotificationController::markAllAsRead
 * @see app/Http/Controllers/NotificationController.php:26
 * @route '/notifications/read-all'
 */
        markAllAsReadForm.put = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: markAllAsRead.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    markAllAsRead.form = markAllAsReadForm
const NotificationController = { markAsRead, markAllAsRead }

export default NotificationController