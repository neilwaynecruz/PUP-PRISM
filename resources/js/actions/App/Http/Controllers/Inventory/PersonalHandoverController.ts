import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::index
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:15
 * @route '/inventory/handover/mine'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/inventory/handover/mine',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::index
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:15
 * @route '/inventory/handover/mine'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::index
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:15
 * @route '/inventory/handover/mine'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::index
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:15
 * @route '/inventory/handover/mine'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::index
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:15
 * @route '/inventory/handover/mine'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::index
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:15
 * @route '/inventory/handover/mine'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::index
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:15
 * @route '/inventory/handover/mine'
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
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::review
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:37
 * @route '/inventory/handover/{handoverLog}/review'
 */
export const review = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: review.url(args, options),
    method: 'post',
})

review.definition = {
    methods: ["post"],
    url: '/inventory/handover/{handoverLog}/review',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::review
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:37
 * @route '/inventory/handover/{handoverLog}/review'
 */
review.url = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return review.definition.url
            .replace('{handoverLog}', parsedArgs.handoverLog.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::review
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:37
 * @route '/inventory/handover/{handoverLog}/review'
 */
review.post = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: review.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::review
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:37
 * @route '/inventory/handover/{handoverLog}/review'
 */
    const reviewForm = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: review.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::review
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:37
 * @route '/inventory/handover/{handoverLog}/review'
 */
        reviewForm.post = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: review.url(args, options),
            method: 'post',
        })

    review.form = reviewForm
const PersonalHandoverController = { index, review }

export default PersonalHandoverController