import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\RequisitionController::index
 * @see app/Http/Controllers/Inventory/RequisitionController.php:37
 * @route '/inventory/requisitions'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/inventory/requisitions',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::index
 * @see app/Http/Controllers/Inventory/RequisitionController.php:37
 * @route '/inventory/requisitions'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::index
 * @see app/Http/Controllers/Inventory/RequisitionController.php:37
 * @route '/inventory/requisitions'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\RequisitionController::index
 * @see app/Http/Controllers/Inventory/RequisitionController.php:37
 * @route '/inventory/requisitions'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::store
 * @see app/Http/Controllers/Inventory/RequisitionController.php:111
 * @route '/inventory/requisitions'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/inventory/requisitions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::store
 * @see app/Http/Controllers/Inventory/RequisitionController.php:111
 * @route '/inventory/requisitions'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::store
 * @see app/Http/Controllers/Inventory/RequisitionController.php:111
 * @route '/inventory/requisitions'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::trash
 * @see app/Http/Controllers/Inventory/RequisitionController.php:358
 * @route '/inventory/requisitions/trash'
 */
export const trash = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: trash.url(options),
    method: 'get',
})

trash.definition = {
    methods: ["get","head"],
    url: '/inventory/requisitions/trash',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::trash
 * @see app/Http/Controllers/Inventory/RequisitionController.php:358
 * @route '/inventory/requisitions/trash'
 */
trash.url = (options?: RouteQueryOptions) => {
    return trash.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::trash
 * @see app/Http/Controllers/Inventory/RequisitionController.php:358
 * @route '/inventory/requisitions/trash'
 */
trash.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: trash.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\RequisitionController::trash
 * @see app/Http/Controllers/Inventory/RequisitionController.php:358
 * @route '/inventory/requisitions/trash'
 */
trash.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: trash.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::show
 * @see app/Http/Controllers/Inventory/RequisitionController.php:66
 * @route '/inventory/requisitions/{requisition}'
 */
export const show = (args: { requisition: string | number } | [requisition: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/inventory/requisitions/{requisition}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::show
 * @see app/Http/Controllers/Inventory/RequisitionController.php:66
 * @route '/inventory/requisitions/{requisition}'
 */
show.url = (args: { requisition: string | number } | [requisition: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: args.requisition,
                }

    return show.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::show
 * @see app/Http/Controllers/Inventory/RequisitionController.php:66
 * @route '/inventory/requisitions/{requisition}'
 */
show.get = (args: { requisition: string | number } | [requisition: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\RequisitionController::show
 * @see app/Http/Controllers/Inventory/RequisitionController.php:66
 * @route '/inventory/requisitions/{requisition}'
 */
show.head = (args: { requisition: string | number } | [requisition: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::destroy
 * @see app/Http/Controllers/Inventory/RequisitionController.php:342
 * @route '/inventory/requisitions/{requisition}'
 */
export const destroy = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/inventory/requisitions/{requisition}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::destroy
 * @see app/Http/Controllers/Inventory/RequisitionController.php:342
 * @route '/inventory/requisitions/{requisition}'
 */
destroy.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisition: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                }

    return destroy.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::destroy
 * @see app/Http/Controllers/Inventory/RequisitionController.php:342
 * @route '/inventory/requisitions/{requisition}'
 */
destroy.delete = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::restore
 * @see app/Http/Controllers/Inventory/RequisitionController.php:408
 * @route '/inventory/requisitions/{requisition}/restore'
 */
export const restore = (args: { requisition: string | number } | [requisition: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: restore.url(args, options),
    method: 'put',
})

restore.definition = {
    methods: ["put"],
    url: '/inventory/requisitions/{requisition}/restore',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::restore
 * @see app/Http/Controllers/Inventory/RequisitionController.php:408
 * @route '/inventory/requisitions/{requisition}/restore'
 */
restore.url = (args: { requisition: string | number } | [requisition: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: args.requisition,
                }

    return restore.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::restore
 * @see app/Http/Controllers/Inventory/RequisitionController.php:408
 * @route '/inventory/requisitions/{requisition}/restore'
 */
restore.put = (args: { requisition: string | number } | [requisition: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: restore.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::forceDelete
 * @see app/Http/Controllers/Inventory/RequisitionController.php:424
 * @route '/inventory/requisitions/{requisition}/force'
 */
export const forceDelete = (args: { requisition: string | number } | [requisition: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: forceDelete.url(args, options),
    method: 'delete',
})

forceDelete.definition = {
    methods: ["delete"],
    url: '/inventory/requisitions/{requisition}/force',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::forceDelete
 * @see app/Http/Controllers/Inventory/RequisitionController.php:424
 * @route '/inventory/requisitions/{requisition}/force'
 */
forceDelete.url = (args: { requisition: string | number } | [requisition: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: args.requisition,
                }

    return forceDelete.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::forceDelete
 * @see app/Http/Controllers/Inventory/RequisitionController.php:424
 * @route '/inventory/requisitions/{requisition}/force'
 */
forceDelete.delete = (args: { requisition: string | number } | [requisition: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: forceDelete.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::bulkRestore
 * @see app/Http/Controllers/Inventory/RequisitionController.php:439
 * @route '/inventory/requisitions/bulk-restore'
 */
export const bulkRestore = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkRestore.url(options),
    method: 'post',
})

bulkRestore.definition = {
    methods: ["post"],
    url: '/inventory/requisitions/bulk-restore',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::bulkRestore
 * @see app/Http/Controllers/Inventory/RequisitionController.php:439
 * @route '/inventory/requisitions/bulk-restore'
 */
bulkRestore.url = (options?: RouteQueryOptions) => {
    return bulkRestore.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::bulkRestore
 * @see app/Http/Controllers/Inventory/RequisitionController.php:439
 * @route '/inventory/requisitions/bulk-restore'
 */
bulkRestore.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkRestore.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::bulkForceDelete
 * @see app/Http/Controllers/Inventory/RequisitionController.php:467
 * @route '/inventory/requisitions/bulk-force-delete'
 */
export const bulkForceDelete = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkForceDelete.url(options),
    method: 'post',
})

bulkForceDelete.definition = {
    methods: ["post"],
    url: '/inventory/requisitions/bulk-force-delete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::bulkForceDelete
 * @see app/Http/Controllers/Inventory/RequisitionController.php:467
 * @route '/inventory/requisitions/bulk-force-delete'
 */
bulkForceDelete.url = (options?: RouteQueryOptions) => {
    return bulkForceDelete.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::bulkForceDelete
 * @see app/Http/Controllers/Inventory/RequisitionController.php:467
 * @route '/inventory/requisitions/bulk-force-delete'
 */
bulkForceDelete.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkForceDelete.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::bulkApprove
 * @see app/Http/Controllers/Inventory/RequisitionController.php:235
 * @route '/inventory/requisitions/bulk-approve'
 */
export const bulkApprove = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkApprove.url(options),
    method: 'post',
})

bulkApprove.definition = {
    methods: ["post"],
    url: '/inventory/requisitions/bulk-approve',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::bulkApprove
 * @see app/Http/Controllers/Inventory/RequisitionController.php:235
 * @route '/inventory/requisitions/bulk-approve'
 */
bulkApprove.url = (options?: RouteQueryOptions) => {
    return bulkApprove.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::bulkApprove
 * @see app/Http/Controllers/Inventory/RequisitionController.php:235
 * @route '/inventory/requisitions/bulk-approve'
 */
bulkApprove.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkApprove.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::bulkIssue
 * @see app/Http/Controllers/Inventory/RequisitionController.php:283
 * @route '/inventory/requisitions/bulk-issue'
 */
export const bulkIssue = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkIssue.url(options),
    method: 'post',
})

bulkIssue.definition = {
    methods: ["post"],
    url: '/inventory/requisitions/bulk-issue',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::bulkIssue
 * @see app/Http/Controllers/Inventory/RequisitionController.php:283
 * @route '/inventory/requisitions/bulk-issue'
 */
bulkIssue.url = (options?: RouteQueryOptions) => {
    return bulkIssue.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::bulkIssue
 * @see app/Http/Controllers/Inventory/RequisitionController.php:283
 * @route '/inventory/requisitions/bulk-issue'
 */
bulkIssue.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkIssue.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::approve
 * @see app/Http/Controllers/Inventory/RequisitionController.php:157
 * @route '/inventory/requisitions/{requisition}/approve'
 */
export const approve = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: approve.url(args, options),
    method: 'put',
})

approve.definition = {
    methods: ["put"],
    url: '/inventory/requisitions/{requisition}/approve',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::approve
 * @see app/Http/Controllers/Inventory/RequisitionController.php:157
 * @route '/inventory/requisitions/{requisition}/approve'
 */
approve.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisition: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                }

    return approve.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::approve
 * @see app/Http/Controllers/Inventory/RequisitionController.php:157
 * @route '/inventory/requisitions/{requisition}/approve'
 */
approve.put = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: approve.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::reject
 * @see app/Http/Controllers/Inventory/RequisitionController.php:179
 * @route '/inventory/requisitions/{requisition}/reject'
 */
export const reject = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: reject.url(args, options),
    method: 'put',
})

reject.definition = {
    methods: ["put"],
    url: '/inventory/requisitions/{requisition}/reject',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::reject
 * @see app/Http/Controllers/Inventory/RequisitionController.php:179
 * @route '/inventory/requisitions/{requisition}/reject'
 */
reject.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisition: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                }

    return reject.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::reject
 * @see app/Http/Controllers/Inventory/RequisitionController.php:179
 * @route '/inventory/requisitions/{requisition}/reject'
 */
reject.put = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: reject.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::issue
 * @see app/Http/Controllers/Inventory/RequisitionController.php:202
 * @route '/inventory/requisitions/{requisition}/issue'
 */
export const issue = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: issue.url(args, options),
    method: 'put',
})

issue.definition = {
    methods: ["put"],
    url: '/inventory/requisitions/{requisition}/issue',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::issue
 * @see app/Http/Controllers/Inventory/RequisitionController.php:202
 * @route '/inventory/requisitions/{requisition}/issue'
 */
issue.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisition: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                }

    return issue.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::issue
 * @see app/Http/Controllers/Inventory/RequisitionController.php:202
 * @route '/inventory/requisitions/{requisition}/issue'
 */
issue.put = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: issue.url(args, options),
    method: 'put',
})
const requisitions = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
trash: Object.assign(trash, trash),
show: Object.assign(show, show),
destroy: Object.assign(destroy, destroy),
restore: Object.assign(restore, restore),
forceDelete: Object.assign(forceDelete, forceDelete),
bulkRestore: Object.assign(bulkRestore, bulkRestore),
bulkForceDelete: Object.assign(bulkForceDelete, bulkForceDelete),
bulkApprove: Object.assign(bulkApprove, bulkApprove),
bulkIssue: Object.assign(bulkIssue, bulkIssue),
approve: Object.assign(approve, approve),
reject: Object.assign(reject, reject),
issue: Object.assign(issue, issue),
}

export default requisitions