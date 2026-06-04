import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
 * @see [serialized-closure]:2
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
 * @see [serialized-closure]:2
 * @route '/session/keep-alive'
 */
keepAlive.url = (options?: RouteQueryOptions) => {
    return keepAlive.definition.url + queryParams(options)
}

/**
 * @see [serialized-closure]:2
 * @route '/session/keep-alive'
 */
keepAlive.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: keepAlive.url(options),
    method: 'get',
})
/**
 * @see [serialized-closure]:2
 * @route '/session/keep-alive'
 */
keepAlive.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: keepAlive.url(options),
    method: 'head',
})

    /**
 * @see [serialized-closure]:2
 * @route '/session/keep-alive'
 */
    const keepAliveForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: keepAlive.url(options),
        method: 'get',
    })

            /**
 * @see [serialized-closure]:2
 * @route '/session/keep-alive'
 */
        keepAliveForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: keepAlive.url(options),
            method: 'get',
        })
            /**
 * @see [serialized-closure]:2
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
    keepAlive: Object.assign(keepAlive, keepAlive),
}

export default session