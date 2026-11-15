import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::download
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:146
 * @route '/inventory/reports/generated/{user}/{file}'
 */
export const download = (args: { user: string | number, file: string | number } | [user: string | number, file: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/inventory/reports/generated/{user}/{file}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::download
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:146
 * @route '/inventory/reports/generated/{user}/{file}'
 */
download.url = (args: { user: string | number, file: string | number } | [user: string | number, file: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    user: args[0],
                    file: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        user: args.user,
                                file: args.file,
                }

    return download.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace('{file}', parsedArgs.file.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::download
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:146
 * @route '/inventory/reports/generated/{user}/{file}'
 */
download.get = (args: { user: string | number, file: string | number } | [user: string | number, file: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\InventoryReportController::download
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:146
 * @route '/inventory/reports/generated/{user}/{file}'
 */
download.head = (args: { user: string | number, file: string | number } | [user: string | number, file: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\InventoryReportController::download
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:146
 * @route '/inventory/reports/generated/{user}/{file}'
 */
    const downloadForm = (args: { user: string | number, file: string | number } | [user: string | number, file: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: download.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\InventoryReportController::download
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:146
 * @route '/inventory/reports/generated/{user}/{file}'
 */
        downloadForm.get = (args: { user: string | number, file: string | number } | [user: string | number, file: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\InventoryReportController::download
 * @see app/Http/Controllers/Inventory/InventoryReportController.php:146
 * @route '/inventory/reports/generated/{user}/{file}'
 */
        downloadForm.head = (args: { user: string | number, file: string | number } | [user: string | number, file: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    download.form = downloadForm
const generated = {
    download: Object.assign(download, download),
}

export default generated