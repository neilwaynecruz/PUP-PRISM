import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\StockMovementController::index
 * @see app/Http/Controllers/Inventory/StockMovementController.php:23
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
 * @see app/Http/Controllers/Inventory/StockMovementController.php:23
 * @route '/inventory/movements'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\StockMovementController::index
 * @see app/Http/Controllers/Inventory/StockMovementController.php:23
 * @route '/inventory/movements'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\StockMovementController::index
 * @see app/Http/Controllers/Inventory/StockMovementController.php:23
 * @route '/inventory/movements'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\StockMovementController::adjust
 * @see app/Http/Controllers/Inventory/StockMovementController.php:127
 * @route '/inventory/movements/adjustments'
 */
export const adjust = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: adjust.url(options),
    method: 'post',
})

adjust.definition = {
    methods: ["post"],
    url: '/inventory/movements/adjustments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\StockMovementController::adjust
 * @see app/Http/Controllers/Inventory/StockMovementController.php:127
 * @route '/inventory/movements/adjustments'
 */
adjust.url = (options?: RouteQueryOptions) => {
    return adjust.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\StockMovementController::adjust
 * @see app/Http/Controllers/Inventory/StockMovementController.php:127
 * @route '/inventory/movements/adjustments'
 */
adjust.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: adjust.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\StockMovementController::cycleCount
 * @see app/Http/Controllers/Inventory/StockMovementController.php:157
 * @route '/inventory/movements/cycle-counts'
 */
export const cycleCount = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cycleCount.url(options),
    method: 'post',
})

cycleCount.definition = {
    methods: ["post"],
    url: '/inventory/movements/cycle-counts',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\StockMovementController::cycleCount
 * @see app/Http/Controllers/Inventory/StockMovementController.php:157
 * @route '/inventory/movements/cycle-counts'
 */
cycleCount.url = (options?: RouteQueryOptions) => {
    return cycleCount.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\StockMovementController::cycleCount
 * @see app/Http/Controllers/Inventory/StockMovementController.php:157
 * @route '/inventory/movements/cycle-counts'
 */
cycleCount.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cycleCount.url(options),
    method: 'post',
})
const StockMovementController = { index, adjust, cycleCount }

export default StockMovementController