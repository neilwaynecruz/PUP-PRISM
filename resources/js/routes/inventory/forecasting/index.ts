import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import profile from './profile'
/**
* @see \App\Http\Controllers\Inventory\ForecastController::index
 * @see app/Http/Controllers/Inventory/ForecastController.php:28
 * @route '/inventory/forecasting'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/inventory/forecasting',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\ForecastController::index
 * @see app/Http/Controllers/Inventory/ForecastController.php:28
 * @route '/inventory/forecasting'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ForecastController::index
 * @see app/Http/Controllers/Inventory/ForecastController.php:28
 * @route '/inventory/forecasting'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ForecastController::index
 * @see app/Http/Controllers/Inventory/ForecastController.php:28
 * @route '/inventory/forecasting'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\ForecastController::index
 * @see app/Http/Controllers/Inventory/ForecastController.php:28
 * @route '/inventory/forecasting'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\ForecastController::index
 * @see app/Http/Controllers/Inventory/ForecastController.php:28
 * @route '/inventory/forecasting'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\ForecastController::index
 * @see app/Http/Controllers/Inventory/ForecastController.php:28
 * @route '/inventory/forecasting'
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
* @see \App\Http\Controllers\Inventory\ForecastController::show
 * @see app/Http/Controllers/Inventory/ForecastController.php:91
 * @route '/inventory/forecasting/{product}'
 */
export const show = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/inventory/forecasting/{product}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\ForecastController::show
 * @see app/Http/Controllers/Inventory/ForecastController.php:91
 * @route '/inventory/forecasting/{product}'
 */
show.url = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { product: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { product: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    product: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        product: typeof args.product === 'object'
                ? args.product.id
                : args.product,
                }

    return show.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ForecastController::show
 * @see app/Http/Controllers/Inventory/ForecastController.php:91
 * @route '/inventory/forecasting/{product}'
 */
show.get = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ForecastController::show
 * @see app/Http/Controllers/Inventory/ForecastController.php:91
 * @route '/inventory/forecasting/{product}'
 */
show.head = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\ForecastController::show
 * @see app/Http/Controllers/Inventory/ForecastController.php:91
 * @route '/inventory/forecasting/{product}'
 */
    const showForm = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\ForecastController::show
 * @see app/Http/Controllers/Inventory/ForecastController.php:91
 * @route '/inventory/forecasting/{product}'
 */
        showForm.get = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\ForecastController::show
 * @see app/Http/Controllers/Inventory/ForecastController.php:91
 * @route '/inventory/forecasting/{product}'
 */
        showForm.head = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
const forecasting = {
    index: Object.assign(index, index),
show: Object.assign(show, show),
profile: Object.assign(profile, profile),
}

export default forecasting