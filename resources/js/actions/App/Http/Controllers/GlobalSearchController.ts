import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\GlobalSearchController::__invoke
 * @see app/Http/Controllers/GlobalSearchController.php:11
 * @route '/search'
 */
const GlobalSearchController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: GlobalSearchController.url(options),
    method: 'get',
})

GlobalSearchController.definition = {
    methods: ["get","head"],
    url: '/search',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\GlobalSearchController::__invoke
 * @see app/Http/Controllers/GlobalSearchController.php:11
 * @route '/search'
 */
GlobalSearchController.url = (options?: RouteQueryOptions) => {
    return GlobalSearchController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\GlobalSearchController::__invoke
 * @see app/Http/Controllers/GlobalSearchController.php:11
 * @route '/search'
 */
GlobalSearchController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: GlobalSearchController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\GlobalSearchController::__invoke
 * @see app/Http/Controllers/GlobalSearchController.php:11
 * @route '/search'
 */
GlobalSearchController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: GlobalSearchController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\GlobalSearchController::__invoke
 * @see app/Http/Controllers/GlobalSearchController.php:11
 * @route '/search'
 */
    const GlobalSearchControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: GlobalSearchController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\GlobalSearchController::__invoke
 * @see app/Http/Controllers/GlobalSearchController.php:11
 * @route '/search'
 */
        GlobalSearchControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: GlobalSearchController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\GlobalSearchController::__invoke
 * @see app/Http/Controllers/GlobalSearchController.php:11
 * @route '/search'
 */
        GlobalSearchControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: GlobalSearchController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    GlobalSearchController.form = GlobalSearchControllerForm
export default GlobalSearchController