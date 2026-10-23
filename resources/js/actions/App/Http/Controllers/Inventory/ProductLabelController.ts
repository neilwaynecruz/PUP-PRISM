import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\ProductLabelController::sheet
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:37
 * @route '/inventory/products/labels/sheet'
 */
export const sheet = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sheet.url(options),
    method: 'get',
})

sheet.definition = {
    methods: ["get","head"],
    url: '/inventory/products/labels/sheet',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\ProductLabelController::sheet
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:37
 * @route '/inventory/products/labels/sheet'
 */
sheet.url = (options?: RouteQueryOptions) => {
    return sheet.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductLabelController::sheet
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:37
 * @route '/inventory/products/labels/sheet'
 */
sheet.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sheet.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ProductLabelController::sheet
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:37
 * @route '/inventory/products/labels/sheet'
 */
sheet.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: sheet.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductLabelController::sheet
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:37
 * @route '/inventory/products/labels/sheet'
 */
    const sheetForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: sheet.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductLabelController::sheet
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:37
 * @route '/inventory/products/labels/sheet'
 */
        sheetForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: sheet.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\ProductLabelController::sheet
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:37
 * @route '/inventory/products/labels/sheet'
 */
        sheetForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: sheet.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    sheet.form = sheetForm
/**
* @see \App\Http\Controllers\Inventory\ProductLabelController::pdf
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:88
 * @route '/inventory/products/labels/pdf'
 */
export const pdf = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pdf.url(options),
    method: 'post',
})

pdf.definition = {
    methods: ["post"],
    url: '/inventory/products/labels/pdf',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\ProductLabelController::pdf
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:88
 * @route '/inventory/products/labels/pdf'
 */
pdf.url = (options?: RouteQueryOptions) => {
    return pdf.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductLabelController::pdf
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:88
 * @route '/inventory/products/labels/pdf'
 */
pdf.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pdf.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductLabelController::pdf
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:88
 * @route '/inventory/products/labels/pdf'
 */
    const pdfForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: pdf.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductLabelController::pdf
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:88
 * @route '/inventory/products/labels/pdf'
 */
        pdfForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: pdf.url(options),
            method: 'post',
        })
    
    pdf.form = pdfForm
/**
* @see \App\Http\Controllers\Inventory\ProductLabelController::show
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:25
 * @route '/inventory/products/{product}/label'
 */
export const show = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/inventory/products/{product}/label',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\ProductLabelController::show
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:25
 * @route '/inventory/products/{product}/label'
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
* @see \App\Http\Controllers\Inventory\ProductLabelController::show
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:25
 * @route '/inventory/products/{product}/label'
 */
show.get = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ProductLabelController::show
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:25
 * @route '/inventory/products/{product}/label'
 */
show.head = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductLabelController::show
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:25
 * @route '/inventory/products/{product}/label'
 */
    const showForm = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductLabelController::show
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:25
 * @route '/inventory/products/{product}/label'
 */
        showForm.get = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\ProductLabelController::show
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:25
 * @route '/inventory/products/{product}/label'
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
const ProductLabelController = { sheet, pdf, show }

export default ProductLabelController