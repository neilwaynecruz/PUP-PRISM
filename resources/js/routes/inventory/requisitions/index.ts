import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\RequisitionController::index
 * @see app/Http/Controllers/Inventory/RequisitionController.php:32
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
 * @see app/Http/Controllers/Inventory/RequisitionController.php:32
 * @route '/inventory/requisitions'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::index
 * @see app/Http/Controllers/Inventory/RequisitionController.php:32
 * @route '/inventory/requisitions'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\RequisitionController::index
 * @see app/Http/Controllers/Inventory/RequisitionController.php:32
 * @route '/inventory/requisitions'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\RequisitionController::index
 * @see app/Http/Controllers/Inventory/RequisitionController.php:32
 * @route '/inventory/requisitions'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\RequisitionController::index
 * @see app/Http/Controllers/Inventory/RequisitionController.php:32
 * @route '/inventory/requisitions'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\RequisitionController::index
 * @see app/Http/Controllers/Inventory/RequisitionController.php:32
 * @route '/inventory/requisitions'
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
* @see \App\Http\Controllers\Inventory\RequisitionController::store
 * @see app/Http/Controllers/Inventory/RequisitionController.php:82
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
 * @see app/Http/Controllers/Inventory/RequisitionController.php:82
 * @route '/inventory/requisitions'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::store
 * @see app/Http/Controllers/Inventory/RequisitionController.php:82
 * @route '/inventory/requisitions'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\RequisitionController::store
 * @see app/Http/Controllers/Inventory/RequisitionController.php:82
 * @route '/inventory/requisitions'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\RequisitionController::store
 * @see app/Http/Controllers/Inventory/RequisitionController.php:82
 * @route '/inventory/requisitions'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Inventory\RequisitionController::trash
 * @see app/Http/Controllers/Inventory/RequisitionController.php:201
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
 * @see app/Http/Controllers/Inventory/RequisitionController.php:201
 * @route '/inventory/requisitions/trash'
 */
trash.url = (options?: RouteQueryOptions) => {
    return trash.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::trash
 * @see app/Http/Controllers/Inventory/RequisitionController.php:201
 * @route '/inventory/requisitions/trash'
 */
trash.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: trash.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\RequisitionController::trash
 * @see app/Http/Controllers/Inventory/RequisitionController.php:201
 * @route '/inventory/requisitions/trash'
 */
trash.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: trash.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\RequisitionController::trash
 * @see app/Http/Controllers/Inventory/RequisitionController.php:201
 * @route '/inventory/requisitions/trash'
 */
    const trashForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: trash.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\RequisitionController::trash
 * @see app/Http/Controllers/Inventory/RequisitionController.php:201
 * @route '/inventory/requisitions/trash'
 */
        trashForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: trash.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\RequisitionController::trash
 * @see app/Http/Controllers/Inventory/RequisitionController.php:201
 * @route '/inventory/requisitions/trash'
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
/**
* @see \App\Http\Controllers\Inventory\RequisitionController::show
 * @see app/Http/Controllers/Inventory/RequisitionController.php:49
 * @route '/inventory/requisitions/{requisition}'
 */
export const show = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/inventory/requisitions/{requisition}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::show
 * @see app/Http/Controllers/Inventory/RequisitionController.php:49
 * @route '/inventory/requisitions/{requisition}'
 */
show.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionController::show
 * @see app/Http/Controllers/Inventory/RequisitionController.php:49
 * @route '/inventory/requisitions/{requisition}'
 */
show.get = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\RequisitionController::show
 * @see app/Http/Controllers/Inventory/RequisitionController.php:49
 * @route '/inventory/requisitions/{requisition}'
 */
show.head = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\RequisitionController::show
 * @see app/Http/Controllers/Inventory/RequisitionController.php:49
 * @route '/inventory/requisitions/{requisition}'
 */
    const showForm = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\RequisitionController::show
 * @see app/Http/Controllers/Inventory/RequisitionController.php:49
 * @route '/inventory/requisitions/{requisition}'
 */
        showForm.get = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\RequisitionController::show
 * @see app/Http/Controllers/Inventory/RequisitionController.php:49
 * @route '/inventory/requisitions/{requisition}'
 */
        showForm.head = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \App\Http\Controllers\Inventory\RequisitionController::destroy
 * @see app/Http/Controllers/Inventory/RequisitionController.php:187
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
 * @see app/Http/Controllers/Inventory/RequisitionController.php:187
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
 * @see app/Http/Controllers/Inventory/RequisitionController.php:187
 * @route '/inventory/requisitions/{requisition}'
 */
destroy.delete = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Inventory\RequisitionController::destroy
 * @see app/Http/Controllers/Inventory/RequisitionController.php:187
 * @route '/inventory/requisitions/{requisition}'
 */
    const destroyForm = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\RequisitionController::destroy
 * @see app/Http/Controllers/Inventory/RequisitionController.php:187
 * @route '/inventory/requisitions/{requisition}'
 */
        destroyForm.delete = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
/**
* @see \App\Http\Controllers\Inventory\RequisitionController::restore
 * @see app/Http/Controllers/Inventory/RequisitionController.php:222
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
 * @see app/Http/Controllers/Inventory/RequisitionController.php:222
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
 * @see app/Http/Controllers/Inventory/RequisitionController.php:222
 * @route '/inventory/requisitions/{requisition}/restore'
 */
restore.put = (args: { requisition: string | number } | [requisition: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: restore.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Inventory\RequisitionController::restore
 * @see app/Http/Controllers/Inventory/RequisitionController.php:222
 * @route '/inventory/requisitions/{requisition}/restore'
 */
    const restoreForm = (args: { requisition: string | number } | [requisition: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: restore.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\RequisitionController::restore
 * @see app/Http/Controllers/Inventory/RequisitionController.php:222
 * @route '/inventory/requisitions/{requisition}/restore'
 */
        restoreForm.put = (args: { requisition: string | number } | [requisition: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: restore.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    restore.form = restoreForm
/**
* @see \App\Http\Controllers\Inventory\RequisitionController::approve
 * @see app/Http/Controllers/Inventory/RequisitionController.php:124
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
 * @see app/Http/Controllers/Inventory/RequisitionController.php:124
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
 * @see app/Http/Controllers/Inventory/RequisitionController.php:124
 * @route '/inventory/requisitions/{requisition}/approve'
 */
approve.put = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: approve.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Inventory\RequisitionController::approve
 * @see app/Http/Controllers/Inventory/RequisitionController.php:124
 * @route '/inventory/requisitions/{requisition}/approve'
 */
    const approveForm = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: approve.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\RequisitionController::approve
 * @see app/Http/Controllers/Inventory/RequisitionController.php:124
 * @route '/inventory/requisitions/{requisition}/approve'
 */
        approveForm.put = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: approve.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    approve.form = approveForm
/**
* @see \App\Http\Controllers\Inventory\RequisitionController::reject
 * @see app/Http/Controllers/Inventory/RequisitionController.php:144
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
 * @see app/Http/Controllers/Inventory/RequisitionController.php:144
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
 * @see app/Http/Controllers/Inventory/RequisitionController.php:144
 * @route '/inventory/requisitions/{requisition}/reject'
 */
reject.put = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: reject.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Inventory\RequisitionController::reject
 * @see app/Http/Controllers/Inventory/RequisitionController.php:144
 * @route '/inventory/requisitions/{requisition}/reject'
 */
    const rejectForm = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reject.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\RequisitionController::reject
 * @see app/Http/Controllers/Inventory/RequisitionController.php:144
 * @route '/inventory/requisitions/{requisition}/reject'
 */
        rejectForm.put = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reject.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    reject.form = rejectForm
/**
* @see \App\Http\Controllers\Inventory\RequisitionController::issue
 * @see app/Http/Controllers/Inventory/RequisitionController.php:165
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
 * @see app/Http/Controllers/Inventory/RequisitionController.php:165
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
 * @see app/Http/Controllers/Inventory/RequisitionController.php:165
 * @route '/inventory/requisitions/{requisition}/issue'
 */
issue.put = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: issue.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Inventory\RequisitionController::issue
 * @see app/Http/Controllers/Inventory/RequisitionController.php:165
 * @route '/inventory/requisitions/{requisition}/issue'
 */
    const issueForm = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: issue.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\RequisitionController::issue
 * @see app/Http/Controllers/Inventory/RequisitionController.php:165
 * @route '/inventory/requisitions/{requisition}/issue'
 */
        issueForm.put = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: issue.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    issue.form = issueForm
const requisitions = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
trash: Object.assign(trash, trash),
show: Object.assign(show, show),
destroy: Object.assign(destroy, destroy),
restore: Object.assign(restore, restore),
approve: Object.assign(approve, approve),
reject: Object.assign(reject, reject),
issue: Object.assign(issue, issue),
}

export default requisitions