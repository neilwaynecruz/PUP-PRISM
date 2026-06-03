import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\RequisitionController::store
 * @see app/Http/Controllers/Api/RequisitionController.php:53
 * @route '/api/requisitions'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/requisitions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\RequisitionController::store
 * @see app/Http/Controllers/Api/RequisitionController.php:53
 * @route '/api/requisitions'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\RequisitionController::store
 * @see app/Http/Controllers/Api/RequisitionController.php:53
 * @route '/api/requisitions'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Api\RequisitionController::store
 * @see app/Http/Controllers/Api/RequisitionController.php:53
 * @route '/api/requisitions'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\RequisitionController::store
 * @see app/Http/Controllers/Api/RequisitionController.php:53
 * @route '/api/requisitions'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Api\RequisitionController::index
 * @see app/Http/Controllers/Api/RequisitionController.php:16
 * @route '/api/requisitions'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/requisitions',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\RequisitionController::index
 * @see app/Http/Controllers/Api/RequisitionController.php:16
 * @route '/api/requisitions'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\RequisitionController::index
 * @see app/Http/Controllers/Api/RequisitionController.php:16
 * @route '/api/requisitions'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Api\RequisitionController::index
 * @see app/Http/Controllers/Api/RequisitionController.php:16
 * @route '/api/requisitions'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Api\RequisitionController::index
 * @see app/Http/Controllers/Api/RequisitionController.php:16
 * @route '/api/requisitions'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Api\RequisitionController::index
 * @see app/Http/Controllers/Api/RequisitionController.php:16
 * @route '/api/requisitions'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Api\RequisitionController::index
 * @see app/Http/Controllers/Api/RequisitionController.php:16
 * @route '/api/requisitions'
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
* @see \App\Http\Controllers\Api\RequisitionController::show
 * @see app/Http/Controllers/Api/RequisitionController.php:42
 * @route '/api/requisitions/{requisition}'
 */
export const show = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/requisitions/{requisition}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\RequisitionController::show
 * @see app/Http/Controllers/Api/RequisitionController.php:42
 * @route '/api/requisitions/{requisition}'
 */
show.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisition: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                }

    return show.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\RequisitionController::show
 * @see app/Http/Controllers/Api/RequisitionController.php:42
 * @route '/api/requisitions/{requisition}'
 */
show.get = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Api\RequisitionController::show
 * @see app/Http/Controllers/Api/RequisitionController.php:42
 * @route '/api/requisitions/{requisition}'
 */
show.head = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Api\RequisitionController::show
 * @see app/Http/Controllers/Api/RequisitionController.php:42
 * @route '/api/requisitions/{requisition}'
 */
    const showForm = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Api\RequisitionController::show
 * @see app/Http/Controllers/Api/RequisitionController.php:42
 * @route '/api/requisitions/{requisition}'
 */
        showForm.get = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Api\RequisitionController::show
 * @see app/Http/Controllers/Api/RequisitionController.php:42
 * @route '/api/requisitions/{requisition}'
 */
        showForm.head = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
const requisitions = {
    store: Object.assign(store, store),
index: Object.assign(index, index),
show: Object.assign(show, show),
}

export default requisitions