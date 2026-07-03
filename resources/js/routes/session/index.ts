import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
 * @see routes/web.php:38
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
 * @see routes/web.php:38
 * @route '/session/keep-alive'
 */
keepAlive.url = (options?: RouteQueryOptions) => {
    return keepAlive.definition.url + queryParams(options)
}

/**
 * @see routes/web.php:38
 * @route '/session/keep-alive'
 */
keepAlive.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: keepAlive.url(options),
    method: 'get',
})
/**
 * @see routes/web.php:38
 * @route '/session/keep-alive'
 */
keepAlive.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: keepAlive.url(options),
    method: 'head',
})
const session = {
    keepAlive: Object.assign(keepAlive, keepAlive),
}

export default session