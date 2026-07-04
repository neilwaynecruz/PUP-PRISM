import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\HandoverReceiptController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverReceiptController.php:16
 * @route '/inventory/handover/receipt/{handoverLog}'
 */
const HandoverReceiptController = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: HandoverReceiptController.url(args, options),
    method: 'get',
})

HandoverReceiptController.definition = {
    methods: ["get","head"],
    url: '/inventory/handover/receipt/{handoverLog}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\HandoverReceiptController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverReceiptController.php:16
 * @route '/inventory/handover/receipt/{handoverLog}'
 */
HandoverReceiptController.url = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return HandoverReceiptController.definition.url
            .replace('{handoverLog}', parsedArgs.handoverLog.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\HandoverReceiptController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverReceiptController.php:16
 * @route '/inventory/handover/receipt/{handoverLog}'
 */
HandoverReceiptController.get = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: HandoverReceiptController.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\HandoverReceiptController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverReceiptController.php:16
 * @route '/inventory/handover/receipt/{handoverLog}'
 */
HandoverReceiptController.head = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: HandoverReceiptController.url(args, options),
    method: 'head',
})
export default HandoverReceiptController