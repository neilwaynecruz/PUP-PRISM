import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\AssetTransferController::store
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:16
 * @route '/inventory/asset-transfers'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/inventory/asset-transfers',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\AssetTransferController::store
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:16
 * @route '/inventory/asset-transfers'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\AssetTransferController::store
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:16
 * @route '/inventory/asset-transfers'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\AssetTransferController::store
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:16
 * @route '/inventory/asset-transfers'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\AssetTransferController::store
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:16
 * @route '/inventory/asset-transfers'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Inventory\AssetTransferController::approve
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:29
 * @route '/inventory/asset-transfers/{assetTransfer}/approve'
 */
export const approve = (args: { assetTransfer: number | { id: number } } | [assetTransfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: approve.url(args, options),
    method: 'post',
})

approve.definition = {
    methods: ["post"],
    url: '/inventory/asset-transfers/{assetTransfer}/approve',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\AssetTransferController::approve
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:29
 * @route '/inventory/asset-transfers/{assetTransfer}/approve'
 */
approve.url = (args: { assetTransfer: number | { id: number } } | [assetTransfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { assetTransfer: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { assetTransfer: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    assetTransfer: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        assetTransfer: typeof args.assetTransfer === 'object'
                ? args.assetTransfer.id
                : args.assetTransfer,
                }

    return approve.definition.url
            .replace('{assetTransfer}', parsedArgs.assetTransfer.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\AssetTransferController::approve
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:29
 * @route '/inventory/asset-transfers/{assetTransfer}/approve'
 */
approve.post = (args: { assetTransfer: number | { id: number } } | [assetTransfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: approve.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\AssetTransferController::approve
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:29
 * @route '/inventory/asset-transfers/{assetTransfer}/approve'
 */
    const approveForm = (args: { assetTransfer: number | { id: number } } | [assetTransfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: approve.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\AssetTransferController::approve
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:29
 * @route '/inventory/asset-transfers/{assetTransfer}/approve'
 */
        approveForm.post = (args: { assetTransfer: number | { id: number } } | [assetTransfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: approve.url(args, options),
            method: 'post',
        })
    
    approve.form = approveForm
/**
* @see \App\Http\Controllers\Inventory\AssetTransferController::reject
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:42
 * @route '/inventory/asset-transfers/{assetTransfer}/reject'
 */
export const reject = (args: { assetTransfer: number | { id: number } } | [assetTransfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reject.url(args, options),
    method: 'post',
})

reject.definition = {
    methods: ["post"],
    url: '/inventory/asset-transfers/{assetTransfer}/reject',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\AssetTransferController::reject
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:42
 * @route '/inventory/asset-transfers/{assetTransfer}/reject'
 */
reject.url = (args: { assetTransfer: number | { id: number } } | [assetTransfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { assetTransfer: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { assetTransfer: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    assetTransfer: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        assetTransfer: typeof args.assetTransfer === 'object'
                ? args.assetTransfer.id
                : args.assetTransfer,
                }

    return reject.definition.url
            .replace('{assetTransfer}', parsedArgs.assetTransfer.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\AssetTransferController::reject
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:42
 * @route '/inventory/asset-transfers/{assetTransfer}/reject'
 */
reject.post = (args: { assetTransfer: number | { id: number } } | [assetTransfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reject.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\AssetTransferController::reject
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:42
 * @route '/inventory/asset-transfers/{assetTransfer}/reject'
 */
    const rejectForm = (args: { assetTransfer: number | { id: number } } | [assetTransfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reject.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\AssetTransferController::reject
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:42
 * @route '/inventory/asset-transfers/{assetTransfer}/reject'
 */
        rejectForm.post = (args: { assetTransfer: number | { id: number } } | [assetTransfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reject.url(args, options),
            method: 'post',
        })
    
    reject.form = rejectForm
/**
* @see \App\Http\Controllers\Inventory\AssetTransferController::cancel
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:55
 * @route '/inventory/asset-transfers/{assetTransfer}/cancel'
 */
export const cancel = (args: { assetTransfer: number | { id: number } } | [assetTransfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

cancel.definition = {
    methods: ["post"],
    url: '/inventory/asset-transfers/{assetTransfer}/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\AssetTransferController::cancel
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:55
 * @route '/inventory/asset-transfers/{assetTransfer}/cancel'
 */
cancel.url = (args: { assetTransfer: number | { id: number } } | [assetTransfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { assetTransfer: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { assetTransfer: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    assetTransfer: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        assetTransfer: typeof args.assetTransfer === 'object'
                ? args.assetTransfer.id
                : args.assetTransfer,
                }

    return cancel.definition.url
            .replace('{assetTransfer}', parsedArgs.assetTransfer.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\AssetTransferController::cancel
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:55
 * @route '/inventory/asset-transfers/{assetTransfer}/cancel'
 */
cancel.post = (args: { assetTransfer: number | { id: number } } | [assetTransfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\AssetTransferController::cancel
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:55
 * @route '/inventory/asset-transfers/{assetTransfer}/cancel'
 */
    const cancelForm = (args: { assetTransfer: number | { id: number } } | [assetTransfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: cancel.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\AssetTransferController::cancel
 * @see app/Http/Controllers/Inventory/AssetTransferController.php:55
 * @route '/inventory/asset-transfers/{assetTransfer}/cancel'
 */
        cancelForm.post = (args: { assetTransfer: number | { id: number } } | [assetTransfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: cancel.url(args, options),
            method: 'post',
        })
    
    cancel.form = cancelForm
const assetTransfers = {
    store: Object.assign(store, store),
approve: Object.assign(approve, approve),
reject: Object.assign(reject, reject),
cancel: Object.assign(cancel, cancel),
}

export default assetTransfers