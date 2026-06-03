import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\TrashController::__invoke
 * @see app/Http/Controllers/Inventory/TrashController.php:15
 * @route '/inventory/trash'
 */
const TrashController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: TrashController.url(options),
    method: 'get',
})

TrashController.definition = {
    methods: ["get","head"],
    url: '/inventory/trash',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\TrashController::__invoke
 * @see app/Http/Controllers/Inventory/TrashController.php:15
 * @route '/inventory/trash'
 */
TrashController.url = (options?: RouteQueryOptions) => {
    return TrashController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\TrashController::__invoke
 * @see app/Http/Controllers/Inventory/TrashController.php:15
 * @route '/inventory/trash'
 */
TrashController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: TrashController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\TrashController::__invoke
 * @see app/Http/Controllers/Inventory/TrashController.php:15
 * @route '/inventory/trash'
 */
TrashController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: TrashController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\TrashController::__invoke
 * @see app/Http/Controllers/Inventory/TrashController.php:15
 * @route '/inventory/trash'
 */
    const TrashControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: TrashController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\TrashController::__invoke
 * @see app/Http/Controllers/Inventory/TrashController.php:15
 * @route '/inventory/trash'
 */
        TrashControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: TrashController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\TrashController::__invoke
 * @see app/Http/Controllers/Inventory/TrashController.php:15
 * @route '/inventory/trash'
 */
        TrashControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: TrashController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    TrashController.form = TrashControllerForm
export default TrashController