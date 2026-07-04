import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::index
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:31
 * @route '/inventory/purchase-orders'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/inventory/purchase-orders',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::index
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:31
 * @route '/inventory/purchase-orders'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::index
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:31
 * @route '/inventory/purchase-orders'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::index
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:31
 * @route '/inventory/purchase-orders'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::create
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:82
 * @route '/inventory/purchase-orders/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/inventory/purchase-orders/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::create
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:82
 * @route '/inventory/purchase-orders/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::create
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:82
 * @route '/inventory/purchase-orders/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::create
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:82
 * @route '/inventory/purchase-orders/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::store
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:92
 * @route '/inventory/purchase-orders'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/inventory/purchase-orders',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::store
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:92
 * @route '/inventory/purchase-orders'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::store
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:92
 * @route '/inventory/purchase-orders'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::generate
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:285
 * @route '/inventory/purchase-orders/generate'
 */
export const generate = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generate.url(options),
    method: 'post',
})

generate.definition = {
    methods: ["post"],
    url: '/inventory/purchase-orders/generate',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::generate
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:285
 * @route '/inventory/purchase-orders/generate'
 */
generate.url = (options?: RouteQueryOptions) => {
    return generate.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::generate
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:285
 * @route '/inventory/purchase-orders/generate'
 */
generate.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generate.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::generateFromForecasts
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:308
 * @route '/inventory/purchase-orders/generate-from-forecasts'
 */
export const generateFromForecasts = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generateFromForecasts.url(options),
    method: 'post',
})

generateFromForecasts.definition = {
    methods: ["post"],
    url: '/inventory/purchase-orders/generate-from-forecasts',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::generateFromForecasts
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:308
 * @route '/inventory/purchase-orders/generate-from-forecasts'
 */
generateFromForecasts.url = (options?: RouteQueryOptions) => {
    return generateFromForecasts.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::generateFromForecasts
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:308
 * @route '/inventory/purchase-orders/generate-from-forecasts'
 */
generateFromForecasts.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generateFromForecasts.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::show
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:140
 * @route '/inventory/purchase-orders/{purchaseOrder}'
 */
export const show = (args: { purchaseOrder: number | { id: number } } | [purchaseOrder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/inventory/purchase-orders/{purchaseOrder}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::show
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:140
 * @route '/inventory/purchase-orders/{purchaseOrder}'
 */
show.url = (args: { purchaseOrder: number | { id: number } } | [purchaseOrder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { purchaseOrder: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { purchaseOrder: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    purchaseOrder: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        purchaseOrder: typeof args.purchaseOrder === 'object'
                ? args.purchaseOrder.id
                : args.purchaseOrder,
                }

    return show.definition.url
            .replace('{purchaseOrder}', parsedArgs.purchaseOrder.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::show
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:140
 * @route '/inventory/purchase-orders/{purchaseOrder}'
 */
show.get = (args: { purchaseOrder: number | { id: number } } | [purchaseOrder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::show
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:140
 * @route '/inventory/purchase-orders/{purchaseOrder}'
 */
show.head = (args: { purchaseOrder: number | { id: number } } | [purchaseOrder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::send
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:161
 * @route '/inventory/purchase-orders/{purchaseOrder}/send'
 */
export const send = (args: { purchaseOrder: number | { id: number } } | [purchaseOrder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: send.url(args, options),
    method: 'put',
})

send.definition = {
    methods: ["put"],
    url: '/inventory/purchase-orders/{purchaseOrder}/send',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::send
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:161
 * @route '/inventory/purchase-orders/{purchaseOrder}/send'
 */
send.url = (args: { purchaseOrder: number | { id: number } } | [purchaseOrder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { purchaseOrder: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { purchaseOrder: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    purchaseOrder: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        purchaseOrder: typeof args.purchaseOrder === 'object'
                ? args.purchaseOrder.id
                : args.purchaseOrder,
                }

    return send.definition.url
            .replace('{purchaseOrder}', parsedArgs.purchaseOrder.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::send
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:161
 * @route '/inventory/purchase-orders/{purchaseOrder}/send'
 */
send.put = (args: { purchaseOrder: number | { id: number } } | [purchaseOrder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: send.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::receive
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:205
 * @route '/inventory/purchase-orders/{purchaseOrder}/receive'
 */
export const receive = (args: { purchaseOrder: number | { id: number } } | [purchaseOrder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: receive.url(args, options),
    method: 'post',
})

receive.definition = {
    methods: ["post"],
    url: '/inventory/purchase-orders/{purchaseOrder}/receive',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::receive
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:205
 * @route '/inventory/purchase-orders/{purchaseOrder}/receive'
 */
receive.url = (args: { purchaseOrder: number | { id: number } } | [purchaseOrder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { purchaseOrder: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { purchaseOrder: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    purchaseOrder: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        purchaseOrder: typeof args.purchaseOrder === 'object'
                ? args.purchaseOrder.id
                : args.purchaseOrder,
                }

    return receive.definition.url
            .replace('{purchaseOrder}', parsedArgs.purchaseOrder.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::receive
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:205
 * @route '/inventory/purchase-orders/{purchaseOrder}/receive'
 */
receive.post = (args: { purchaseOrder: number | { id: number } } | [purchaseOrder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: receive.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::cancel
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:252
 * @route '/inventory/purchase-orders/{purchaseOrder}/cancel'
 */
export const cancel = (args: { purchaseOrder: number | { id: number } } | [purchaseOrder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: cancel.url(args, options),
    method: 'put',
})

cancel.definition = {
    methods: ["put"],
    url: '/inventory/purchase-orders/{purchaseOrder}/cancel',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::cancel
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:252
 * @route '/inventory/purchase-orders/{purchaseOrder}/cancel'
 */
cancel.url = (args: { purchaseOrder: number | { id: number } } | [purchaseOrder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { purchaseOrder: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { purchaseOrder: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    purchaseOrder: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        purchaseOrder: typeof args.purchaseOrder === 'object'
                ? args.purchaseOrder.id
                : args.purchaseOrder,
                }

    return cancel.definition.url
            .replace('{purchaseOrder}', parsedArgs.purchaseOrder.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\PurchaseOrderController::cancel
 * @see app/Http/Controllers/Inventory/PurchaseOrderController.php:252
 * @route '/inventory/purchase-orders/{purchaseOrder}/cancel'
 */
cancel.put = (args: { purchaseOrder: number | { id: number } } | [purchaseOrder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: cancel.url(args, options),
    method: 'put',
})
const purchaseOrders = {
    index: Object.assign(index, index),
create: Object.assign(create, create),
store: Object.assign(store, store),
generate: Object.assign(generate, generate),
generateFromForecasts: Object.assign(generateFromForecasts, generateFromForecasts),
show: Object.assign(show, show),
send: Object.assign(send, send),
receive: Object.assign(receive, receive),
cancel: Object.assign(cancel, cancel),
}

export default purchaseOrders