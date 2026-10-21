import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\AssetLifecycleController::store
 * @see app/Http/Controllers/Inventory/AssetLifecycleController.php:15
 * @route '/inventory/asset-lifecycle'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/inventory/asset-lifecycle',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\AssetLifecycleController::store
 * @see app/Http/Controllers/Inventory/AssetLifecycleController.php:15
 * @route '/inventory/asset-lifecycle'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\AssetLifecycleController::store
 * @see app/Http/Controllers/Inventory/AssetLifecycleController.php:15
 * @route '/inventory/asset-lifecycle'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\AssetLifecycleController::store
 * @see app/Http/Controllers/Inventory/AssetLifecycleController.php:15
 * @route '/inventory/asset-lifecycle'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\AssetLifecycleController::store
 * @see app/Http/Controllers/Inventory/AssetLifecycleController.php:15
 * @route '/inventory/asset-lifecycle'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Inventory\AssetLifecycleController::approve
 * @see app/Http/Controllers/Inventory/AssetLifecycleController.php:28
 * @route '/inventory/asset-lifecycle/{assetLifecycleEvent}/approve'
 */
export const approve = (args: { assetLifecycleEvent: number | { id: number } } | [assetLifecycleEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: approve.url(args, options),
    method: 'post',
})

approve.definition = {
    methods: ["post"],
    url: '/inventory/asset-lifecycle/{assetLifecycleEvent}/approve',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\AssetLifecycleController::approve
 * @see app/Http/Controllers/Inventory/AssetLifecycleController.php:28
 * @route '/inventory/asset-lifecycle/{assetLifecycleEvent}/approve'
 */
approve.url = (args: { assetLifecycleEvent: number | { id: number } } | [assetLifecycleEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { assetLifecycleEvent: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { assetLifecycleEvent: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    assetLifecycleEvent: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        assetLifecycleEvent: typeof args.assetLifecycleEvent === 'object'
                ? args.assetLifecycleEvent.id
                : args.assetLifecycleEvent,
                }

    return approve.definition.url
            .replace('{assetLifecycleEvent}', parsedArgs.assetLifecycleEvent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\AssetLifecycleController::approve
 * @see app/Http/Controllers/Inventory/AssetLifecycleController.php:28
 * @route '/inventory/asset-lifecycle/{assetLifecycleEvent}/approve'
 */
approve.post = (args: { assetLifecycleEvent: number | { id: number } } | [assetLifecycleEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: approve.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\AssetLifecycleController::approve
 * @see app/Http/Controllers/Inventory/AssetLifecycleController.php:28
 * @route '/inventory/asset-lifecycle/{assetLifecycleEvent}/approve'
 */
    const approveForm = (args: { assetLifecycleEvent: number | { id: number } } | [assetLifecycleEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: approve.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\AssetLifecycleController::approve
 * @see app/Http/Controllers/Inventory/AssetLifecycleController.php:28
 * @route '/inventory/asset-lifecycle/{assetLifecycleEvent}/approve'
 */
        approveForm.post = (args: { assetLifecycleEvent: number | { id: number } } | [assetLifecycleEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: approve.url(args, options),
            method: 'post',
        })
    
    approve.form = approveForm
/**
* @see \App\Http\Controllers\Inventory\AssetLifecycleController::reject
 * @see app/Http/Controllers/Inventory/AssetLifecycleController.php:41
 * @route '/inventory/asset-lifecycle/{assetLifecycleEvent}/reject'
 */
export const reject = (args: { assetLifecycleEvent: number | { id: number } } | [assetLifecycleEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reject.url(args, options),
    method: 'post',
})

reject.definition = {
    methods: ["post"],
    url: '/inventory/asset-lifecycle/{assetLifecycleEvent}/reject',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\AssetLifecycleController::reject
 * @see app/Http/Controllers/Inventory/AssetLifecycleController.php:41
 * @route '/inventory/asset-lifecycle/{assetLifecycleEvent}/reject'
 */
reject.url = (args: { assetLifecycleEvent: number | { id: number } } | [assetLifecycleEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { assetLifecycleEvent: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { assetLifecycleEvent: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    assetLifecycleEvent: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        assetLifecycleEvent: typeof args.assetLifecycleEvent === 'object'
                ? args.assetLifecycleEvent.id
                : args.assetLifecycleEvent,
                }

    return reject.definition.url
            .replace('{assetLifecycleEvent}', parsedArgs.assetLifecycleEvent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\AssetLifecycleController::reject
 * @see app/Http/Controllers/Inventory/AssetLifecycleController.php:41
 * @route '/inventory/asset-lifecycle/{assetLifecycleEvent}/reject'
 */
reject.post = (args: { assetLifecycleEvent: number | { id: number } } | [assetLifecycleEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reject.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\AssetLifecycleController::reject
 * @see app/Http/Controllers/Inventory/AssetLifecycleController.php:41
 * @route '/inventory/asset-lifecycle/{assetLifecycleEvent}/reject'
 */
    const rejectForm = (args: { assetLifecycleEvent: number | { id: number } } | [assetLifecycleEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reject.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\AssetLifecycleController::reject
 * @see app/Http/Controllers/Inventory/AssetLifecycleController.php:41
 * @route '/inventory/asset-lifecycle/{assetLifecycleEvent}/reject'
 */
        rejectForm.post = (args: { assetLifecycleEvent: number | { id: number } } | [assetLifecycleEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reject.url(args, options),
            method: 'post',
        })
    
    reject.form = rejectForm
const assetLifecycle = {
    store: Object.assign(store, store),
approve: Object.assign(approve, approve),
reject: Object.assign(reject, reject),
}

export default assetLifecycle