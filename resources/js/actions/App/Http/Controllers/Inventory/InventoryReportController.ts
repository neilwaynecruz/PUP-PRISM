import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
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
* @see \App\Http\Controllers\Inventory\InventoryReportController::products
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:32
 * @route '/inventory/reports/products/{format}'
 */
    const productsForm = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: products.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\InventoryReportController::products
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:32
 * @route '/inventory/reports/products/{format}'
 */
        productsForm.get = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: products.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\InventoryReportController::products
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:32
 * @route '/inventory/reports/products/{format}'
 */
        productsForm.head = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: products.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    products.form = productsForm
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
* @see \App\Http\Controllers\Inventory\InventoryReportController::bookings
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:53
 * @route '/inventory/reports/bookings/{format}'
 */
    const bookingsForm = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: bookings.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\InventoryReportController::bookings
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:53
 * @route '/inventory/reports/bookings/{format}'
 */
        bookingsForm.get = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: bookings.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\InventoryReportController::bookings
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:53
 * @route '/inventory/reports/bookings/{format}'
 */
        bookingsForm.head = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: bookings.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    bookings.form = bookingsForm
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
* @see \App\Http\Controllers\Inventory\InventoryReportController::requisitions
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:60
 * @route '/inventory/reports/requisitions/{format}'
 */
    const requisitionsForm = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: requisitions.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\InventoryReportController::requisitions
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:60
 * @route '/inventory/reports/requisitions/{format}'
 */
        requisitionsForm.get = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: requisitions.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\InventoryReportController::requisitions
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:60
 * @route '/inventory/reports/requisitions/{format}'
 */
        requisitionsForm.head = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: requisitions.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    requisitions.form = requisitionsForm
/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::stockMovements
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:39
 * @route '/inventory/reports/movements/{format}'
 */
export const stockMovements = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: stockMovements.url(args, options),
    method: 'get',
})

stockMovements.definition = {
    methods: ["get","head"],
    url: '/inventory/reports/movements/{format}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::stockMovements
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:39
 * @route '/inventory/reports/movements/{format}'
 */
stockMovements.url = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return stockMovements.definition.url
            .replace('{format}', parsedArgs.format.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::stockMovements
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:39
 * @route '/inventory/reports/movements/{format}'
 */
stockMovements.get = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: stockMovements.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::stockMovements
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:39
 * @route '/inventory/reports/movements/{format}'
 */
stockMovements.head = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: stockMovements.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\InventoryReportController::stockMovements
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:39
 * @route '/inventory/reports/movements/{format}'
 */
    const stockMovementsForm = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: stockMovements.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\InventoryReportController::stockMovements
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:39
 * @route '/inventory/reports/movements/{format}'
 */
        stockMovementsForm.get = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: stockMovements.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\InventoryReportController::stockMovements
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:39
 * @route '/inventory/reports/movements/{format}'
 */
        stockMovementsForm.head = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: stockMovements.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    stockMovements.form = stockMovementsForm
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

    /**
* @see \App\Http\Controllers\Inventory\InventoryReportController::assetConditions
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:46
 * @route '/inventory/reports/assets/condition/{format}'
 */
    const assetConditionsForm = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: assetConditions.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\InventoryReportController::assetConditions
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:46
 * @route '/inventory/reports/assets/condition/{format}'
 */
        assetConditionsForm.get = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: assetConditions.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\InventoryReportController::assetConditions
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:46
 * @route '/inventory/reports/assets/condition/{format}'
 */
        assetConditionsForm.head = (args: { format: string | number } | [format: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: assetConditions.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    assetConditions.form = assetConditionsForm
const InventoryReportController = { products, bookings, requisitions, stockMovements, assetConditions }

export default InventoryReportController