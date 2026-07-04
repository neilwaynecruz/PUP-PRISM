import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\SessionStatusController::__invoke
 * @see app/Http/Controllers/SessionStatusController.php:11
 * @route '/session/status'
 */
const SessionStatusController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: SessionStatusController.url(options),
    method: 'get',
})

SessionStatusController.definition = {
    methods: ["get","head"],
    url: '/session/status',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SessionStatusController::__invoke
 * @see app/Http/Controllers/SessionStatusController.php:11
 * @route '/session/status'
 */
SessionStatusController.url = (options?: RouteQueryOptions) => {
    return SessionStatusController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SessionStatusController::__invoke
 * @see app/Http/Controllers/SessionStatusController.php:11
 * @route '/session/status'
 */
SessionStatusController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: SessionStatusController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\SessionStatusController::__invoke
 * @see app/Http/Controllers/SessionStatusController.php:11
 * @route '/session/status'
 */
SessionStatusController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: SessionStatusController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\SessionStatusController::__invoke
 * @see app/Http/Controllers/SessionStatusController.php:11
 * @route '/session/status'
 */
    const SessionStatusControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: SessionStatusController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\SessionStatusController::__invoke
 * @see app/Http/Controllers/SessionStatusController.php:11
 * @route '/session/status'
 */
        SessionStatusControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: SessionStatusController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\SessionStatusController::__invoke
 * @see app/Http/Controllers/SessionStatusController.php:11
 * @route '/session/status'
 */
        SessionStatusControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: SessionStatusController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    SessionStatusController.form = SessionStatusControllerForm
export default SessionStatusController