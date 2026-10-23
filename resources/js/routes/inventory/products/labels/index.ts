import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
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
const labels = {
    sheet: Object.assign(sheet, sheet),
pdf: Object.assign(pdf, pdf),
}

export default labels