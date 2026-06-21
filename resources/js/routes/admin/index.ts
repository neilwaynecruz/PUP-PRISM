import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
 * @see routes/web.php:37
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
 * @see routes/web.php:37
 * @route '/admin/health'
 */
health.url = (options?: RouteQueryOptions) => {
    return health.definition.url + queryParams(options)
}

/**
 * @see routes/web.php:37
 * @route '/admin/health'
 */
health.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: health.url(options),
    method: 'get',
})
/**
 * @see routes/web.php:37
 * @route '/admin/health'
 */
health.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: health.url(options),
    method: 'head',
})

    /**
 * @see routes/web.php:37
 * @route '/admin/health'
 */
    const healthForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: health.url(options),
        method: 'get',
    })

            /**
 * @see routes/web.php:37
 * @route '/admin/health'
 */
        healthForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: health.url(options),
            method: 'get',
        })
            /**
 * @see routes/web.php:37
 * @route '/admin/health'
 */
        healthForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: health.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    health.form = healthForm
const admin = {
    health: Object.assign(health, health),
}

export default admin