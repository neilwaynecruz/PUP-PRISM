import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
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
* @see \App\Http\Controllers\Inventory\ReceivingController::index
 * @see app/Http/Controllers/Inventory/ReceivingController.php:19
 * @route '/inventory/receiving'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\ReceivingController::index
 * @see app/Http/Controllers/Inventory/ReceivingController.php:19
 * @route '/inventory/receiving'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\ReceivingController::index
 * @see app/Http/Controllers/Inventory/ReceivingController.php:19
 * @route '/inventory/receiving'
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
* @see \App\Http\Controllers\Inventory\ReceivingController::store
 * @see app/Http/Controllers/Inventory/ReceivingController.php:24
 * @route '/inventory/receiving'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\ReceivingController::store
 * @see app/Http/Controllers/Inventory/ReceivingController.php:24
 * @route '/inventory/receiving'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Inventory\ReceivingController::storeBatch
 * @see app/Http/Controllers/Inventory/ReceivingController.php:49
 * @route '/inventory/receiving/batch'
 */
export const storeBatch = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeBatch.url(options),
    method: 'post',
})

storeBatch.definition = {
    methods: ["post"],
    url: '/inventory/receiving/batch',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\ReceivingController::storeBatch
 * @see app/Http/Controllers/Inventory/ReceivingController.php:49
 * @route '/inventory/receiving/batch'
 */
storeBatch.url = (options?: RouteQueryOptions) => {
    return storeBatch.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ReceivingController::storeBatch
 * @see app/Http/Controllers/Inventory/ReceivingController.php:49
 * @route '/inventory/receiving/batch'
 */
storeBatch.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeBatch.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\ReceivingController::storeBatch
 * @see app/Http/Controllers/Inventory/ReceivingController.php:49
 * @route '/inventory/receiving/batch'
 */
    const storeBatchForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: storeBatch.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\ReceivingController::storeBatch
 * @see app/Http/Controllers/Inventory/ReceivingController.php:49
 * @route '/inventory/receiving/batch'
 */
        storeBatchForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: storeBatch.url(options),
            method: 'post',
        })
    
    storeBatch.form = storeBatchForm
const ReceivingController = { index, store, storeBatch }

export default ReceivingController