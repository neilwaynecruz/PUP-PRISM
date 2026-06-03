import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\StockMovementController::index
 * @see app/Http/Controllers/Inventory/StockMovementController.php:15
 * @route '/inventory/movements'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/inventory/movements',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\StockMovementController::index
 * @see app/Http/Controllers/Inventory/StockMovementController.php:15
 * @route '/inventory/movements'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\StockMovementController::index
 * @see app/Http/Controllers/Inventory/StockMovementController.php:15
 * @route '/inventory/movements'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\StockMovementController::index
 * @see app/Http/Controllers/Inventory/StockMovementController.php:15
 * @route '/inventory/movements'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\StockMovementController::index
 * @see app/Http/Controllers/Inventory/StockMovementController.php:15
 * @route '/inventory/movements'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\StockMovementController::index
 * @see app/Http/Controllers/Inventory/StockMovementController.php:15
 * @route '/inventory/movements'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\StockMovementController::index
 * @see app/Http/Controllers/Inventory/StockMovementController.php:15
 * @route '/inventory/movements'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
const movements = {
    index: Object.assign(index, index),
}

export default movements