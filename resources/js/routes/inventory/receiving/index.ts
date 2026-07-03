import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\ReceivingController::index
 * @see app/Http/Controllers/Inventory/ReceivingController.php:19
 * @route '/inventory/receiving'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/inventory/receiving',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\ReceivingController::index
 * @see app/Http/Controllers/Inventory/ReceivingController.php:19
 * @route '/inventory/receiving'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ReceivingController::index
 * @see app/Http/Controllers/Inventory/ReceivingController.php:19
 * @route '/inventory/receiving'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ReceivingController::index
 * @see app/Http/Controllers/Inventory/ReceivingController.php:19
 * @route '/inventory/receiving'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\ReceivingController::store
 * @see app/Http/Controllers/Inventory/ReceivingController.php:24
 * @route '/inventory/receiving'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/inventory/receiving',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\ReceivingController::store
 * @see app/Http/Controllers/Inventory/ReceivingController.php:24
 * @route '/inventory/receiving'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ReceivingController::store
 * @see app/Http/Controllers/Inventory/ReceivingController.php:24
 * @route '/inventory/receiving'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\ReceivingController::batch
 * @see app/Http/Controllers/Inventory/ReceivingController.php:67
 * @route '/inventory/receiving/batch'
 */
export const batch = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: batch.url(options),
    method: 'post',
})

batch.definition = {
    methods: ["post"],
    url: '/inventory/receiving/batch',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\ReceivingController::batch
 * @see app/Http/Controllers/Inventory/ReceivingController.php:67
 * @route '/inventory/receiving/batch'
 */
batch.url = (options?: RouteQueryOptions) => {
    return batch.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ReceivingController::batch
 * @see app/Http/Controllers/Inventory/ReceivingController.php:67
 * @route '/inventory/receiving/batch'
 */
batch.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: batch.url(options),
    method: 'post',
})
const receiving = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
batch: Object.assign(batch, batch),
}

export default receiving