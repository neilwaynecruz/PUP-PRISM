import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::products
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:32
 * @route '/inventory/reports/products/{format}'
 */
export const products = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: products.url(args, options),
    method: 'get',
})

products.definition = {
    methods: ["get","head"],
    url: '/inventory/reports/products/{format}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::products
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:32
 * @route '/inventory/reports/products/{format}'
 */
products.url = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { format: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    format: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        format: args.format,
                }

    return products.definition.url
            .replace('{format}', parsedArgs.format.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::products
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:32
 * @route '/inventory/reports/products/{format}'
 */
products.get = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: products.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::products
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:32
 * @route '/inventory/reports/products/{format}'
 */
products.head = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: products.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::bookings
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:53
 * @route '/inventory/reports/bookings/{format}'
 */
export const bookings = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: bookings.url(args, options),
    method: 'get',
})

bookings.definition = {
    methods: ["get","head"],
    url: '/inventory/reports/bookings/{format}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::bookings
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:53
 * @route '/inventory/reports/bookings/{format}'
 */
bookings.url = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { format: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    format: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        format: args.format,
                }

    return bookings.definition.url
            .replace('{format}', parsedArgs.format.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::bookings
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:53
 * @route '/inventory/reports/bookings/{format}'
 */
bookings.get = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: bookings.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::bookings
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:53
 * @route '/inventory/reports/bookings/{format}'
 */
bookings.head = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: bookings.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::requisitions
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:60
 * @route '/inventory/reports/requisitions/{format}'
 */
export const requisitions = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: requisitions.url(args, options),
    method: 'get',
})

requisitions.definition = {
    methods: ["get","head"],
    url: '/inventory/reports/requisitions/{format}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::requisitions
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:60
 * @route '/inventory/reports/requisitions/{format}'
 */
requisitions.url = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { format: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    format: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        format: args.format,
                }

    return requisitions.definition.url
            .replace('{format}', parsedArgs.format.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::requisitions
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:60
 * @route '/inventory/reports/requisitions/{format}'
 */
requisitions.get = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: requisitions.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::requisitions
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:60
 * @route '/inventory/reports/requisitions/{format}'
 */
requisitions.head = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: requisitions.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::movements
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:39
 * @route '/inventory/reports/movements/{format}'
 */
export const movements = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: movements.url(args, options),
    method: 'get',
})

movements.definition = {
    methods: ["get","head"],
    url: '/inventory/reports/movements/{format}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::movements
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:39
 * @route '/inventory/reports/movements/{format}'
 */
movements.url = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { format: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    format: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        format: args.format,
                }

    return movements.definition.url
            .replace('{format}', parsedArgs.format.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::movements
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:39
 * @route '/inventory/reports/movements/{format}'
 */
movements.get = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: movements.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::movements
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:39
 * @route '/inventory/reports/movements/{format}'
 */
movements.head = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: movements.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::assetConditions
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:46
 * @route '/inventory/reports/assets/condition/{format}'
 */
export const assetConditions = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: assetConditions.url(args, options),
    method: 'get',
})

assetConditions.definition = {
    methods: ["get","head"],
    url: '/inventory/reports/assets/condition/{format}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::assetConditions
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:46
 * @route '/inventory/reports/assets/condition/{format}'
 */
assetConditions.url = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { format: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    format: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        format: args.format,
                }

    return assetConditions.definition.url
            .replace('{format}', parsedArgs.format.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::assetConditions
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:46
 * @route '/inventory/reports/assets/condition/{format}'
 */
assetConditions.get = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: assetConditions.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::assetConditions
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:46
 * @route '/inventory/reports/assets/condition/{format}'
 */
assetConditions.head = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: assetConditions.url(args, options),
    method: 'head',
})
const reports = {
    products: Object.assign(products, products),
bookings: Object.assign(bookings, bookings),
requisitions: Object.assign(requisitions, requisitions),
movements: Object.assign(movements, movements),
assetConditions: Object.assign(assetConditions, assetConditions),
}

export default reports