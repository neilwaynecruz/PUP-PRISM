import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
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
const StockMovementController = { index }

export default StockMovementController