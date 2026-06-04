import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\HandoverController::submit
 * @see app/Http/Controllers/Inventory/HandoverController.php:100
 * @route '/inventory/handover/verify/{handoverLog}'
 */
export const submit = (args: { handoverLog: string | number | { id: string | number } } | [handoverLog: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(args, options),
    method: 'post',
})

submit.definition = {
    methods: ["post"],
    url: '/inventory/handover/verify/{handoverLog}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\HandoverController::submit
 * @see app/Http/Controllers/Inventory/HandoverController.php:100
 * @route '/inventory/handover/verify/{handoverLog}'
 */
submit.url = (args: { handoverLog: string | number | { id: string | number } } | [handoverLog: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return submit.definition.url
            .replace('{handoverLog}', parsedArgs.handoverLog.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\HandoverController::submit
 * @see app/Http/Controllers/Inventory/HandoverController.php:100
 * @route '/inventory/handover/verify/{handoverLog}'
 */
submit.post = (args: { handoverLog: string | number | { id: string | number } } | [handoverLog: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\HandoverController::submit
 * @see app/Http/Controllers/Inventory/HandoverController.php:100
 * @route '/inventory/handover/verify/{handoverLog}'
 */
    const submitForm = (args: { handoverLog: string | number | { id: string | number } } | [handoverLog: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: submit.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\HandoverController::submit
 * @see app/Http/Controllers/Inventory/HandoverController.php:100
 * @route '/inventory/handover/verify/{handoverLog}'
 */
        submitForm.post = (args: { handoverLog: string | number | { id: string | number } } | [handoverLog: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: submit.url(args, options),
            method: 'post',
        })
    
    submit.form = submitForm
const verify = {
    submit: Object.assign(submit, submit),
}

export default verify