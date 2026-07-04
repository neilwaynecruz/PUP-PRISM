import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
import verify8ef1b2 from './verify'
/**
* @see \App\Http\Controllers\Inventory\HandoverController::index
 * @see app/Http/Controllers/Inventory/HandoverController.php:35
 * @route '/inventory/handover'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/inventory/handover',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\HandoverController::index
 * @see app/Http/Controllers/Inventory/HandoverController.php:35
 * @route '/inventory/handover'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\HandoverController::index
 * @see app/Http/Controllers/Inventory/HandoverController.php:35
 * @route '/inventory/handover'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\HandoverController::index
 * @see app/Http/Controllers/Inventory/HandoverController.php:35
 * @route '/inventory/handover'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\HandoverController::store
 * @see app/Http/Controllers/Inventory/HandoverController.php:70
 * @route '/inventory/handover'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/inventory/handover',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\HandoverController::store
 * @see app/Http/Controllers/Inventory/HandoverController.php:70
 * @route '/inventory/handover'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\HandoverController::store
 * @see app/Http/Controllers/Inventory/HandoverController.php:70
 * @route '/inventory/handover'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\HandoverVerificationController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverVerificationController.php:15
 * @route '/inventory/handover/verify/{handoverLog}'
 */
export const verify = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verify.url(args, options),
    method: 'get',
})

verify.definition = {
    methods: ["get","head"],
    url: '/inventory/handover/verify/{handoverLog}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\HandoverVerificationController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverVerificationController.php:15
 * @route '/inventory/handover/verify/{handoverLog}'
 */
verify.url = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { handoverLog: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { handoverLog: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    handoverLog: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        handoverLog: typeof args.handoverLog === 'object'
                ? args.handoverLog.id
                : args.handoverLog,
                }

    return verify.definition.url
            .replace('{handoverLog}', parsedArgs.handoverLog.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\HandoverVerificationController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverVerificationController.php:15
 * @route '/inventory/handover/verify/{handoverLog}'
 */
verify.get = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verify.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\HandoverVerificationController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverVerificationController.php:15
 * @route '/inventory/handover/verify/{handoverLog}'
 */
verify.head = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: verify.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\HandoverReceiptController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverReceiptController.php:16
 * @route '/inventory/handover/receipt/{handoverLog}'
 */
export const receipt = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: receipt.url(args, options),
    method: 'get',
})

receipt.definition = {
    methods: ["get","head"],
    url: '/inventory/handover/receipt/{handoverLog}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\HandoverReceiptController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverReceiptController.php:16
 * @route '/inventory/handover/receipt/{handoverLog}'
 */
receipt.url = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { handoverLog: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { handoverLog: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    handoverLog: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        handoverLog: typeof args.handoverLog === 'object'
                ? args.handoverLog.id
                : args.handoverLog,
                }

    return receipt.definition.url
            .replace('{handoverLog}', parsedArgs.handoverLog.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\HandoverReceiptController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverReceiptController.php:16
 * @route '/inventory/handover/receipt/{handoverLog}'
 */
receipt.get = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: receipt.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\HandoverReceiptController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverReceiptController.php:16
 * @route '/inventory/handover/receipt/{handoverLog}'
 */
receipt.head = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: receipt.url(args, options),
    method: 'head',
})
const handover = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
verify: Object.assign(verify, verify8ef1b2),
receipt: Object.assign(receipt, receipt),
}

export default handover