import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\HandoverVerificationController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverVerificationController.php:15
 * @route '/inventory/handover/verify/{handoverLog}'
 */
const HandoverVerificationController = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: HandoverVerificationController.url(args, options),
    method: 'get',
})

HandoverVerificationController.definition = {
    methods: ["get","head"],
    url: '/inventory/handover/verify/{handoverLog}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\HandoverVerificationController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverVerificationController.php:15
 * @route '/inventory/handover/verify/{handoverLog}'
 */
HandoverVerificationController.url = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return HandoverVerificationController.definition.url
            .replace('{handoverLog}', parsedArgs.handoverLog.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\HandoverVerificationController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverVerificationController.php:15
 * @route '/inventory/handover/verify/{handoverLog}'
 */
HandoverVerificationController.get = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: HandoverVerificationController.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\HandoverVerificationController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverVerificationController.php:15
 * @route '/inventory/handover/verify/{handoverLog}'
 */
HandoverVerificationController.head = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: HandoverVerificationController.url(args, options),
    method: 'head',
})
export default HandoverVerificationController