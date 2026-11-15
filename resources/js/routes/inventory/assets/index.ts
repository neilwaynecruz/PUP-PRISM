import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\AssetController::index
 * @see app/Http/Controllers/Inventory/AssetController.php:22
 * @route '/inventory/assets'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/inventory/assets',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\AssetController::index
 * @see app/Http/Controllers/Inventory/AssetController.php:22
 * @route '/inventory/assets'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\AssetController::index
 * @see app/Http/Controllers/Inventory/AssetController.php:22
 * @route '/inventory/assets'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\AssetController::index
 * @see app/Http/Controllers/Inventory/AssetController.php:22
 * @route '/inventory/assets'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\AssetController::index
 * @see app/Http/Controllers/Inventory/AssetController.php:22
 * @route '/inventory/assets'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\AssetController::index
 * @see app/Http/Controllers/Inventory/AssetController.php:22
 * @route '/inventory/assets'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\AssetController::index
 * @see app/Http/Controllers/Inventory/AssetController.php:22
 * @route '/inventory/assets'
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
* @see \App\Http\Controllers\Inventory\AssetController::show
 * @see app/Http/Controllers/Inventory/AssetController.php:51
 * @route '/inventory/assets/{asset}'
 */
export const show = (args: { asset: number | { id: number } } | [asset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/inventory/assets/{asset}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\AssetController::show
 * @see app/Http/Controllers/Inventory/AssetController.php:51
 * @route '/inventory/assets/{asset}'
 */
show.url = (args: { asset: number | { id: number } } | [asset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { asset: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { asset: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    asset: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        asset: typeof args.asset === 'object'
                ? args.asset.id
                : args.asset,
                }

    return show.definition.url
            .replace('{asset}', parsedArgs.asset.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\AssetController::show
 * @see app/Http/Controllers/Inventory/AssetController.php:51
 * @route '/inventory/assets/{asset}'
 */
show.get = (args: { asset: number | { id: number } } | [asset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\AssetController::show
 * @see app/Http/Controllers/Inventory/AssetController.php:51
 * @route '/inventory/assets/{asset}'
 */
show.head = (args: { asset: number | { id: number } } | [asset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\AssetController::show
 * @see app/Http/Controllers/Inventory/AssetController.php:51
 * @route '/inventory/assets/{asset}'
 */
    const showForm = (args: { asset: number | { id: number } } | [asset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\AssetController::show
 * @see app/Http/Controllers/Inventory/AssetController.php:51
 * @route '/inventory/assets/{asset}'
 */
        showForm.get = (args: { asset: number | { id: number } } | [asset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\AssetController::show
 * @see app/Http/Controllers/Inventory/AssetController.php:51
 * @route '/inventory/assets/{asset}'
 */
        showForm.head = (args: { asset: number | { id: number } } | [asset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \App\Http\Controllers\Inventory\AssetController::returnMethod
 * @see app/Http/Controllers/Inventory/AssetController.php:79
 * @route '/inventory/assets/{asset}/return'
 */
export const returnMethod = (args: { asset: number | { id: number } } | [asset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: returnMethod.url(args, options),
    method: 'post',
})

returnMethod.definition = {
    methods: ["post"],
    url: '/inventory/assets/{asset}/return',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\AssetController::returnMethod
 * @see app/Http/Controllers/Inventory/AssetController.php:79
 * @route '/inventory/assets/{asset}/return'
 */
returnMethod.url = (args: { asset: number | { id: number } } | [asset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { asset: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { asset: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    asset: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        asset: typeof args.asset === 'object'
                ? args.asset.id
                : args.asset,
                }

    return returnMethod.definition.url
            .replace('{asset}', parsedArgs.asset.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\AssetController::returnMethod
 * @see app/Http/Controllers/Inventory/AssetController.php:79
 * @route '/inventory/assets/{asset}/return'
 */
returnMethod.post = (args: { asset: number | { id: number } } | [asset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: returnMethod.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\AssetController::returnMethod
 * @see app/Http/Controllers/Inventory/AssetController.php:79
 * @route '/inventory/assets/{asset}/return'
 */
    const returnMethodForm = (args: { asset: number | { id: number } } | [asset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: returnMethod.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\AssetController::returnMethod
 * @see app/Http/Controllers/Inventory/AssetController.php:79
 * @route '/inventory/assets/{asset}/return'
 */
        returnMethodForm.post = (args: { asset: number | { id: number } } | [asset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: returnMethod.url(args, options),
            method: 'post',
        })
    
    returnMethod.form = returnMethodForm
const assets = {
    index: Object.assign(index, index),
show: Object.assign(show, show),
return: Object.assign(returnMethod, returnMethod),
}

export default assets