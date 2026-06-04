import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
import handover from './handover'
import bookings from './bookings'
import requisitions from './requisitions'
import requisitionTemplates from './requisition-templates'
import reports from './reports'
import products from './products'
import receiving from './receiving'
import movements from './movements'
import auditLogs from './audit-logs'
/**
* @see \App\Http\Controllers\Inventory\TrashController::__invoke
 * @see app/Http/Controllers/Inventory/TrashController.php:15
 * @route '/inventory/trash'
 */
export const trash = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: trash.url(options),
    method: 'get',
})

trash.definition = {
    methods: ["get","head"],
    url: '/inventory/trash',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\TrashController::__invoke
 * @see app/Http/Controllers/Inventory/TrashController.php:15
 * @route '/inventory/trash'
 */
trash.url = (options?: RouteQueryOptions) => {
    return trash.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\TrashController::__invoke
 * @see app/Http/Controllers/Inventory/TrashController.php:15
 * @route '/inventory/trash'
 */
trash.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: trash.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\TrashController::__invoke
 * @see app/Http/Controllers/Inventory/TrashController.php:15
 * @route '/inventory/trash'
 */
trash.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: trash.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\TrashController::__invoke
 * @see app/Http/Controllers/Inventory/TrashController.php:15
 * @route '/inventory/trash'
 */
    const trashForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: trash.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\TrashController::__invoke
 * @see app/Http/Controllers/Inventory/TrashController.php:15
 * @route '/inventory/trash'
 */
        trashForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: trash.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\TrashController::__invoke
 * @see app/Http/Controllers/Inventory/TrashController.php:15
 * @route '/inventory/trash'
 */
        trashForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: trash.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    trash.form = trashForm
const inventory = {
    handover: Object.assign(handover, handover),
bookings: Object.assign(bookings, bookings),
requisitions: Object.assign(requisitions, requisitions),
requisitionTemplates: Object.assign(requisitionTemplates, requisitionTemplates),
reports: Object.assign(reports, reports),
products: Object.assign(products, products),
receiving: Object.assign(receiving, receiving),
trash: Object.assign(trash, trash),
movements: Object.assign(movements, movements),
auditLogs: Object.assign(auditLogs, auditLogs),
}

export default inventory