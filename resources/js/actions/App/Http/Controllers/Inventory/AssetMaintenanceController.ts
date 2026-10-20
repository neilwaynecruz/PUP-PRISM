import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\AssetMaintenanceController::store
 * @see app/Http/Controllers/Inventory/AssetMaintenanceController.php:16
 * @route '/inventory/maintenance'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/inventory/maintenance',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\AssetMaintenanceController::store
 * @see app/Http/Controllers/Inventory/AssetMaintenanceController.php:16
 * @route '/inventory/maintenance'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\AssetMaintenanceController::store
 * @see app/Http/Controllers/Inventory/AssetMaintenanceController.php:16
 * @route '/inventory/maintenance'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\AssetMaintenanceController::store
 * @see app/Http/Controllers/Inventory/AssetMaintenanceController.php:16
 * @route '/inventory/maintenance'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\AssetMaintenanceController::store
 * @see app/Http/Controllers/Inventory/AssetMaintenanceController.php:16
 * @route '/inventory/maintenance'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Inventory\AssetMaintenanceController::transition
 * @see app/Http/Controllers/Inventory/AssetMaintenanceController.php:29
 * @route '/inventory/maintenance/{assetMaintenance}/status'
 */
export const transition = (args: { assetMaintenance: string | number | { id: string | number } } | [assetMaintenance: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: transition.url(args, options),
    method: 'put',
})

transition.definition = {
    methods: ["put"],
    url: '/inventory/maintenance/{assetMaintenance}/status',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Inventory\AssetMaintenanceController::transition
 * @see app/Http/Controllers/Inventory/AssetMaintenanceController.php:29
 * @route '/inventory/maintenance/{assetMaintenance}/status'
 */
transition.url = (args: { assetMaintenance: string | number | { id: string | number } } | [assetMaintenance: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { assetMaintenance: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { assetMaintenance: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    assetMaintenance: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        assetMaintenance: typeof args.assetMaintenance === 'object'
                ? args.assetMaintenance.id
                : args.assetMaintenance,
                }

    return transition.definition.url
            .replace('{assetMaintenance}', parsedArgs.assetMaintenance.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\AssetMaintenanceController::transition
 * @see app/Http/Controllers/Inventory/AssetMaintenanceController.php:29
 * @route '/inventory/maintenance/{assetMaintenance}/status'
 */
transition.put = (args: { assetMaintenance: string | number | { id: string | number } } | [assetMaintenance: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: transition.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Inventory\AssetMaintenanceController::transition
 * @see app/Http/Controllers/Inventory/AssetMaintenanceController.php:29
 * @route '/inventory/maintenance/{assetMaintenance}/status'
 */
    const transitionForm = (args: { assetMaintenance: string | number | { id: string | number } } | [assetMaintenance: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: transition.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\AssetMaintenanceController::transition
 * @see app/Http/Controllers/Inventory/AssetMaintenanceController.php:29
 * @route '/inventory/maintenance/{assetMaintenance}/status'
 */
        transitionForm.put = (args: { assetMaintenance: string | number | { id: string | number } } | [assetMaintenance: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: transition.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    transition.form = transitionForm
const AssetMaintenanceController = { store, transition }

export default AssetMaintenanceController