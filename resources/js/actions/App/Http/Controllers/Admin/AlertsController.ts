import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\AlertsController::index
 * @see app/Http/Controllers/Admin/AlertsController.php:18
 * @route '/admin/alerts'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/alerts',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\AlertsController::index
 * @see app/Http/Controllers/Admin/AlertsController.php:18
 * @route '/admin/alerts'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AlertsController::index
 * @see app/Http/Controllers/Admin/AlertsController.php:18
 * @route '/admin/alerts'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\AlertsController::index
 * @see app/Http/Controllers/Admin/AlertsController.php:18
 * @route '/admin/alerts'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\AlertsController::index
 * @see app/Http/Controllers/Admin/AlertsController.php:18
 * @route '/admin/alerts'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\AlertsController::index
 * @see app/Http/Controllers/Admin/AlertsController.php:18
 * @route '/admin/alerts'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\AlertsController::index
 * @see app/Http/Controllers/Admin/AlertsController.php:18
 * @route '/admin/alerts'
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
* @see \App\Http\Controllers\Admin\AlertsController::acknowledge
 * @see app/Http/Controllers/Admin/AlertsController.php:109
 * @route '/admin/alerts/{alert}/acknowledge'
 */
export const acknowledge = (args: { alert: number | { id: number } } | [alert: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: acknowledge.url(args, options),
    method: 'patch',
})

acknowledge.definition = {
    methods: ["patch"],
    url: '/admin/alerts/{alert}/acknowledge',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\AlertsController::acknowledge
 * @see app/Http/Controllers/Admin/AlertsController.php:109
 * @route '/admin/alerts/{alert}/acknowledge'
 */
acknowledge.url = (args: { alert: number | { id: number } } | [alert: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { alert: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { alert: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    alert: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        alert: typeof args.alert === 'object'
                ? args.alert.id
                : args.alert,
                }

    return acknowledge.definition.url
            .replace('{alert}', parsedArgs.alert.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AlertsController::acknowledge
 * @see app/Http/Controllers/Admin/AlertsController.php:109
 * @route '/admin/alerts/{alert}/acknowledge'
 */
acknowledge.patch = (args: { alert: number | { id: number } } | [alert: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: acknowledge.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\AlertsController::acknowledge
 * @see app/Http/Controllers/Admin/AlertsController.php:109
 * @route '/admin/alerts/{alert}/acknowledge'
 */
    const acknowledgeForm = (args: { alert: number | { id: number } } | [alert: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: acknowledge.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\AlertsController::acknowledge
 * @see app/Http/Controllers/Admin/AlertsController.php:109
 * @route '/admin/alerts/{alert}/acknowledge'
 */
        acknowledgeForm.patch = (args: { alert: number | { id: number } } | [alert: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: acknowledge.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    acknowledge.form = acknowledgeForm
/**
* @see \App\Http\Controllers\Admin\AlertsController::assign
 * @see app/Http/Controllers/Admin/AlertsController.php:126
 * @route '/admin/alerts/{alert}/assign'
 */
export const assign = (args: { alert: number | { id: number } } | [alert: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: assign.url(args, options),
    method: 'patch',
})

assign.definition = {
    methods: ["patch"],
    url: '/admin/alerts/{alert}/assign',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\AlertsController::assign
 * @see app/Http/Controllers/Admin/AlertsController.php:126
 * @route '/admin/alerts/{alert}/assign'
 */
assign.url = (args: { alert: number | { id: number } } | [alert: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { alert: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { alert: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    alert: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        alert: typeof args.alert === 'object'
                ? args.alert.id
                : args.alert,
                }

    return assign.definition.url
            .replace('{alert}', parsedArgs.alert.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AlertsController::assign
 * @see app/Http/Controllers/Admin/AlertsController.php:126
 * @route '/admin/alerts/{alert}/assign'
 */
assign.patch = (args: { alert: number | { id: number } } | [alert: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: assign.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\AlertsController::assign
 * @see app/Http/Controllers/Admin/AlertsController.php:126
 * @route '/admin/alerts/{alert}/assign'
 */
    const assignForm = (args: { alert: number | { id: number } } | [alert: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: assign.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\AlertsController::assign
 * @see app/Http/Controllers/Admin/AlertsController.php:126
 * @route '/admin/alerts/{alert}/assign'
 */
        assignForm.patch = (args: { alert: number | { id: number } } | [alert: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: assign.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    assign.form = assignForm
/**
* @see \App\Http\Controllers\Admin\AlertsController::resolve
 * @see app/Http/Controllers/Admin/AlertsController.php:145
 * @route '/admin/alerts/{alert}/resolve'
 */
export const resolve = (args: { alert: number | { id: number } } | [alert: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: resolve.url(args, options),
    method: 'patch',
})

resolve.definition = {
    methods: ["patch"],
    url: '/admin/alerts/{alert}/resolve',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\AlertsController::resolve
 * @see app/Http/Controllers/Admin/AlertsController.php:145
 * @route '/admin/alerts/{alert}/resolve'
 */
resolve.url = (args: { alert: number | { id: number } } | [alert: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { alert: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { alert: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    alert: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        alert: typeof args.alert === 'object'
                ? args.alert.id
                : args.alert,
                }

    return resolve.definition.url
            .replace('{alert}', parsedArgs.alert.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AlertsController::resolve
 * @see app/Http/Controllers/Admin/AlertsController.php:145
 * @route '/admin/alerts/{alert}/resolve'
 */
resolve.patch = (args: { alert: number | { id: number } } | [alert: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: resolve.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\AlertsController::resolve
 * @see app/Http/Controllers/Admin/AlertsController.php:145
 * @route '/admin/alerts/{alert}/resolve'
 */
    const resolveForm = (args: { alert: number | { id: number } } | [alert: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: resolve.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\AlertsController::resolve
 * @see app/Http/Controllers/Admin/AlertsController.php:145
 * @route '/admin/alerts/{alert}/resolve'
 */
        resolveForm.patch = (args: { alert: number | { id: number } } | [alert: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: resolve.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    resolve.form = resolveForm
const AlertsController = { index, acknowledge, assign, resolve }

export default AlertsController