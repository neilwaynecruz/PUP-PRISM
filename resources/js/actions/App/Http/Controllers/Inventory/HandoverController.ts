import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\HandoverController::index
 * @see app/Http/Controllers/Inventory/HandoverController.php:25
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
 * @see app/Http/Controllers/Inventory/HandoverController.php:25
 * @route '/inventory/handover'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\HandoverController::index
 * @see app/Http/Controllers/Inventory/HandoverController.php:25
 * @route '/inventory/handover'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\HandoverController::index
 * @see app/Http/Controllers/Inventory/HandoverController.php:25
 * @route '/inventory/handover'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\HandoverController::index
 * @see app/Http/Controllers/Inventory/HandoverController.php:25
 * @route '/inventory/handover'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\HandoverController::index
 * @see app/Http/Controllers/Inventory/HandoverController.php:25
 * @route '/inventory/handover'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\HandoverController::index
 * @see app/Http/Controllers/Inventory/HandoverController.php:25
 * @route '/inventory/handover'
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
* @see \App\Http\Controllers\Inventory\HandoverController::store
 * @see app/Http/Controllers/Inventory/HandoverController.php:60
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
 * @see app/Http/Controllers/Inventory/HandoverController.php:60
 * @route '/inventory/handover'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\HandoverController::store
 * @see app/Http/Controllers/Inventory/HandoverController.php:60
 * @route '/inventory/handover'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\HandoverController::store
 * @see app/Http/Controllers/Inventory/HandoverController.php:60
 * @route '/inventory/handover'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\HandoverController::store
 * @see app/Http/Controllers/Inventory/HandoverController.php:60
 * @route '/inventory/handover'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Inventory\HandoverController::verify
 * @see app/Http/Controllers/Inventory/HandoverController.php:100
 * @route '/inventory/handover/verify/{handoverLog}'
 */
export const verify = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: verify.url(args, options),
    method: 'post',
})

verify.definition = {
    methods: ["post"],
    url: '/inventory/handover/verify/{handoverLog}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\HandoverController::verify
 * @see app/Http/Controllers/Inventory/HandoverController.php:100
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
* @see \App\Http\Controllers\Inventory\HandoverController::verify
 * @see app/Http/Controllers/Inventory/HandoverController.php:100
 * @route '/inventory/handover/verify/{handoverLog}'
 */
verify.post = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: verify.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\HandoverController::verify
 * @see app/Http/Controllers/Inventory/HandoverController.php:100
 * @route '/inventory/handover/verify/{handoverLog}'
 */
    const verifyForm = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: verify.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\HandoverController::verify
 * @see app/Http/Controllers/Inventory/HandoverController.php:100
 * @route '/inventory/handover/verify/{handoverLog}'
 */
        verifyForm.post = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: verify.url(args, options),
            method: 'post',
        })
    
    verify.form = verifyForm
const HandoverController = { index, store, verify }

export default HandoverController