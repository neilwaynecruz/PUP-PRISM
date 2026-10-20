import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::update
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:113
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}'
 */
export const update = (args: { inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } } | [inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::update
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:113
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}'
 */
update.url = (args: { inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } } | [inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    inventoryCountSession: args[0],
                    inventoryCountLine: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        inventoryCountSession: typeof args.inventoryCountSession === 'object'
                ? args.inventoryCountSession.id
                : args.inventoryCountSession,
                                inventoryCountLine: typeof args.inventoryCountLine === 'object'
                ? args.inventoryCountLine.id
                : args.inventoryCountLine,
                }

    return update.definition.url
            .replace('{inventoryCountSession}', parsedArgs.inventoryCountSession.toString())
            .replace('{inventoryCountLine}', parsedArgs.inventoryCountLine.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::update
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:113
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}'
 */
update.put = (args: { inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } } | [inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::update
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:113
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}'
 */
    const updateForm = (args: { inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } } | [inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::update
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:113
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}'
 */
        updateForm.put = (args: { inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } } | [inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::review
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:151
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}/review'
 */
export const review = (args: { inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } } | [inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: review.url(args, options),
    method: 'put',
})

review.definition = {
    methods: ["put"],
    url: '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}/review',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::review
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:151
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}/review'
 */
review.url = (args: { inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } } | [inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    inventoryCountSession: args[0],
                    inventoryCountLine: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        inventoryCountSession: typeof args.inventoryCountSession === 'object'
                ? args.inventoryCountSession.id
                : args.inventoryCountSession,
                                inventoryCountLine: typeof args.inventoryCountLine === 'object'
                ? args.inventoryCountLine.id
                : args.inventoryCountLine,
                }

    return review.definition.url
            .replace('{inventoryCountSession}', parsedArgs.inventoryCountSession.toString())
            .replace('{inventoryCountLine}', parsedArgs.inventoryCountLine.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::review
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:151
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}/review'
 */
review.put = (args: { inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } } | [inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: review.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::review
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:151
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}/review'
 */
    const reviewForm = (args: { inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } } | [inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: review.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::review
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:151
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}/review'
 */
        reviewForm.put = (args: { inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } } | [inventoryCountSession: string | number | { id: string | number }, inventoryCountLine: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: review.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    review.form = reviewForm
const lines = {
    update: Object.assign(update, update),
review: Object.assign(review, review),
}

export default lines