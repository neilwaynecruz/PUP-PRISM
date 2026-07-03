import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\StockMovementController::index
 * @see app/Http/Controllers/Api/StockMovementController.php:14
 * @route '/api/stock-movements'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/stock-movements',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\StockMovementController::index
 * @see app/Http/Controllers/Api/StockMovementController.php:14
 * @route '/api/stock-movements'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\StockMovementController::index
 * @see app/Http/Controllers/Api/StockMovementController.php:14
 * @route '/api/stock-movements'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Api\StockMovementController::index
 * @see app/Http/Controllers/Api/StockMovementController.php:14
 * @route '/api/stock-movements'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Api\StockMovementController::index
 * @see app/Http/Controllers/Api/StockMovementController.php:14
 * @route '/api/stock-movements'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Api\StockMovementController::index
 * @see app/Http/Controllers/Api/StockMovementController.php:14
 * @route '/api/stock-movements'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Api\StockMovementController::index
 * @see app/Http/Controllers/Api/StockMovementController.php:14
 * @route '/api/stock-movements'
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
const stockMovements = {
    index: Object.assign(index, index),
}

export default stockMovements