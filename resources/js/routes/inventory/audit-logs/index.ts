import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\AuditLogController::index
 * @see app/Http/Controllers/Inventory/AuditLogController.php:22
 * @route '/inventory/audit-logs'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/inventory/audit-logs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\AuditLogController::index
 * @see app/Http/Controllers/Inventory/AuditLogController.php:22
 * @route '/inventory/audit-logs'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\AuditLogController::index
 * @see app/Http/Controllers/Inventory/AuditLogController.php:22
 * @route '/inventory/audit-logs'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\AuditLogController::index
 * @see app/Http/Controllers/Inventory/AuditLogController.php:22
 * @route '/inventory/audit-logs'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\AuditLogController::index
 * @see app/Http/Controllers/Inventory/AuditLogController.php:22
 * @route '/inventory/audit-logs'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\AuditLogController::index
 * @see app/Http/Controllers/Inventory/AuditLogController.php:22
 * @route '/inventory/audit-logs'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\AuditLogController::index
 * @see app/Http/Controllers/Inventory/AuditLogController.php:22
 * @route '/inventory/audit-logs'
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
* @see \App\Http\Controllers\Inventory\AuditLogController::exportMethod
 * @see app/Http/Controllers/Inventory/AuditLogController.php:185
 * @route '/inventory/audit-logs/export'
 */
export const exportMethod = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(options),
    method: 'get',
})

exportMethod.definition = {
    methods: ["get","head"],
    url: '/inventory/audit-logs/export',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\AuditLogController::exportMethod
 * @see app/Http/Controllers/Inventory/AuditLogController.php:185
 * @route '/inventory/audit-logs/export'
 */
exportMethod.url = (options?: RouteQueryOptions) => {
    return exportMethod.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\AuditLogController::exportMethod
 * @see app/Http/Controllers/Inventory/AuditLogController.php:185
 * @route '/inventory/audit-logs/export'
 */
exportMethod.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\AuditLogController::exportMethod
 * @see app/Http/Controllers/Inventory/AuditLogController.php:185
 * @route '/inventory/audit-logs/export'
 */
exportMethod.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportMethod.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\AuditLogController::exportMethod
 * @see app/Http/Controllers/Inventory/AuditLogController.php:185
 * @route '/inventory/audit-logs/export'
 */
    const exportMethodForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: exportMethod.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\AuditLogController::exportMethod
 * @see app/Http/Controllers/Inventory/AuditLogController.php:185
 * @route '/inventory/audit-logs/export'
 */
        exportMethodForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: exportMethod.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\AuditLogController::exportMethod
 * @see app/Http/Controllers/Inventory/AuditLogController.php:185
 * @route '/inventory/audit-logs/export'
 */
        exportMethodForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: exportMethod.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    exportMethod.form = exportMethodForm
const auditLogs = {
    index: Object.assign(index, index),
export: Object.assign(exportMethod, exportMethod),
}

export default auditLogs