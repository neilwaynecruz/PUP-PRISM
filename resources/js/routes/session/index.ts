import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\SessionStatusController::__invoke
 * @see app/Http/Controllers/SessionStatusController.php:11
 * @route '/session/status'
 */
export const status = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: status.url(options),
    method: 'get',
})

status.definition = {
    methods: ["get","head"],
    url: '/session/status',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SessionStatusController::__invoke
 * @see app/Http/Controllers/SessionStatusController.php:11
 * @route '/session/status'
 */
status.url = (options?: RouteQueryOptions) => {
    return status.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SessionStatusController::__invoke
 * @see app/Http/Controllers/SessionStatusController.php:11
 * @route '/session/status'
 */
status.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: status.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\SessionStatusController::__invoke
 * @see app/Http/Controllers/SessionStatusController.php:11
 * @route '/session/status'
 */
status.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: status.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\SessionStatusController::__invoke
 * @see app/Http/Controllers/SessionStatusController.php:11
 * @route '/session/status'
 */
    const statusForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: status.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\SessionStatusController::__invoke
 * @see app/Http/Controllers/SessionStatusController.php:11
 * @route '/session/status'
 */
        statusForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: status.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\SessionStatusController::__invoke
 * @see app/Http/Controllers/SessionStatusController.php:11
 * @route '/session/status'
 */
        statusForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: status.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    status.form = statusForm
/**
 * @see routes/web.php:48
 * @route '/session/keep-alive'
 */
export const keepAlive = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: keepAlive.url(options),
    method: 'get',
})

keepAlive.definition = {
    methods: ["get","head"],
    url: '/session/keep-alive',
} satisfies RouteDefinition<["get","head"]>

/**
 * @see routes/web.php:48
 * @route '/session/keep-alive'
 */
keepAlive.url = (options?: RouteQueryOptions) => {
    return keepAlive.definition.url + queryParams(options)
}

/**
 * @see routes/web.php:48
 * @route '/session/keep-alive'
 */
keepAlive.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: keepAlive.url(options),
    method: 'get',
})
/**
 * @see routes/web.php:48
 * @route '/session/keep-alive'
 */
keepAlive.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: keepAlive.url(options),
    method: 'head',
})

    /**
 * @see routes/web.php:48
 * @route '/session/keep-alive'
 */
    const keepAliveForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: keepAlive.url(options),
        method: 'get',
    })

            /**
 * @see routes/web.php:48
 * @route '/session/keep-alive'
 */
        keepAliveForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: keepAlive.url(options),
            method: 'get',
        })
            /**
 * @see routes/web.php:48
 * @route '/session/keep-alive'
 */
        keepAliveForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: keepAlive.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    keepAlive.form = keepAliveForm
const session = {
    status: Object.assign(status, status),
keepAlive: Object.assign(keepAlive, keepAlive),
}

export default session