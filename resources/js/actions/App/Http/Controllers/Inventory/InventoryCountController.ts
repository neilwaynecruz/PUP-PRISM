import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::index
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:26
 * @route '/inventory/counts'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/inventory/counts',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::index
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:26
 * @route '/inventory/counts'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::index
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:26
 * @route '/inventory/counts'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::index
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:26
 * @route '/inventory/counts'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::index
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:26
 * @route '/inventory/counts'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::index
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:26
 * @route '/inventory/counts'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::index
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:26
 * @route '/inventory/counts'
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
* @see \App\Http\Controllers\Inventory\InventoryCountController::store
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:72
 * @route '/inventory/counts'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/inventory/counts',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::store
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:72
 * @route '/inventory/counts'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::store
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:72
 * @route '/inventory/counts'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::store
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:72
 * @route '/inventory/counts'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::store
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:72
 * @route '/inventory/counts'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::show
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:85
 * @route '/inventory/counts/{inventoryCountSession}'
 */
export const show = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/inventory/counts/{inventoryCountSession}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::show
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:85
 * @route '/inventory/counts/{inventoryCountSession}'
 */
show.url = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { inventoryCountSession: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { inventoryCountSession: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    inventoryCountSession: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        inventoryCountSession: typeof args.inventoryCountSession === 'object'
                ? args.inventoryCountSession.id
                : args.inventoryCountSession,
                }

    return show.definition.url
            .replace('{inventoryCountSession}', parsedArgs.inventoryCountSession.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::show
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:85
 * @route '/inventory/counts/{inventoryCountSession}'
 */
show.get = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::show
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:85
 * @route '/inventory/counts/{inventoryCountSession}'
 */
show.head = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::show
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:85
 * @route '/inventory/counts/{inventoryCountSession}'
 */
    const showForm = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::show
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:85
 * @route '/inventory/counts/{inventoryCountSession}'
 */
        showForm.get = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::show
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:85
 * @route '/inventory/counts/{inventoryCountSession}'
 */
        showForm.head = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Inventory\InventoryCountController::updateLine
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:113
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}'
 */
export const updateLine = (args: { inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } } | [inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateLine.url(args, options),
    method: 'put',
})

updateLine.definition = {
    methods: ["put"],
    url: '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::updateLine
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:113
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}'
 */
updateLine.url = (args: { inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } } | [inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    inventoryCountSession: args[0],
                    inventoryCountLine: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        inventoryCountSession: typeof args.inventoryCountSession === 'object'
                ? args.inventoryCountSession.id
                : args.inventoryCountSession,
                                inventoryCountLine: typeof args.inventoryCountLine === 'object'
                ? args.inventoryCountLine.id
                : args.inventoryCountLine,
                }

    return updateLine.definition.url
            .replace('{inventoryCountSession}', parsedArgs.inventoryCountSession.toString())
            .replace('{inventoryCountLine}', parsedArgs.inventoryCountLine.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::updateLine
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:113
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}'
 */
updateLine.put = (args: { inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } } | [inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateLine.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::updateLine
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:113
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}'
 */
    const updateLineForm = (args: { inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } } | [inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updateLine.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::updateLine
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:113
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}'
 */
        updateLineForm.put = (args: { inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } } | [inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updateLine.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updateLine.form = updateLineForm
/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::submit
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:136
 * @route '/inventory/counts/{inventoryCountSession}/submit'
 */
export const submit = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(args, options),
    method: 'post',
})

submit.definition = {
    methods: ["post"],
    url: '/inventory/counts/{inventoryCountSession}/submit',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::submit
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:136
 * @route '/inventory/counts/{inventoryCountSession}/submit'
 */
submit.url = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { inventoryCountSession: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { inventoryCountSession: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    inventoryCountSession: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        inventoryCountSession: typeof args.inventoryCountSession === 'object'
                ? args.inventoryCountSession.id
                : args.inventoryCountSession,
                }

    return submit.definition.url
            .replace('{inventoryCountSession}', parsedArgs.inventoryCountSession.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::submit
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:136
 * @route '/inventory/counts/{inventoryCountSession}/submit'
 */
submit.post = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::submit
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:136
 * @route '/inventory/counts/{inventoryCountSession}/submit'
 */
    const submitForm = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: submit.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::submit
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:136
 * @route '/inventory/counts/{inventoryCountSession}/submit'
 */
        submitForm.post = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: submit.url(args, options),
            method: 'post',
        })
    
    submit.form = submitForm
/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::reviewLine
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:151
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}/review'
 */
export const reviewLine = (args: { inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } } | [inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: reviewLine.url(args, options),
    method: 'put',
})

reviewLine.definition = {
    methods: ["put"],
    url: '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}/review',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::reviewLine
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:151
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}/review'
 */
reviewLine.url = (args: { inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } } | [inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    inventoryCountSession: args[0],
                    inventoryCountLine: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        inventoryCountSession: typeof args.inventoryCountSession === 'object'
                ? args.inventoryCountSession.id
                : args.inventoryCountSession,
                                inventoryCountLine: typeof args.inventoryCountLine === 'object'
                ? args.inventoryCountLine.id
                : args.inventoryCountLine,
                }

    return reviewLine.definition.url
            .replace('{inventoryCountSession}', parsedArgs.inventoryCountSession.toString())
            .replace('{inventoryCountLine}', parsedArgs.inventoryCountLine.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::reviewLine
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:151
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}/review'
 */
reviewLine.put = (args: { inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } } | [inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: reviewLine.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::reviewLine
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:151
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}/review'
 */
    const reviewLineForm = (args: { inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } } | [inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reviewLine.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::reviewLine
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:151
 * @route '/inventory/counts/{inventoryCountSession}/lines/{inventoryCountLine}/review'
 */
        reviewLineForm.put = (args: { inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } } | [inventoryCountSession: number | { id: number }, inventoryCountLine: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reviewLine.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    reviewLine.form = reviewLineForm
/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::approve
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:174
 * @route '/inventory/counts/{inventoryCountSession}/approve'
 */
export const approve = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: approve.url(args, options),
    method: 'post',
})

approve.definition = {
    methods: ["post"],
    url: '/inventory/counts/{inventoryCountSession}/approve',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::approve
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:174
 * @route '/inventory/counts/{inventoryCountSession}/approve'
 */
approve.url = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { inventoryCountSession: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { inventoryCountSession: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    inventoryCountSession: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        inventoryCountSession: typeof args.inventoryCountSession === 'object'
                ? args.inventoryCountSession.id
                : args.inventoryCountSession,
                }

    return approve.definition.url
            .replace('{inventoryCountSession}', parsedArgs.inventoryCountSession.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::approve
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:174
 * @route '/inventory/counts/{inventoryCountSession}/approve'
 */
approve.post = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: approve.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::approve
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:174
 * @route '/inventory/counts/{inventoryCountSession}/approve'
 */
    const approveForm = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: approve.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::approve
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:174
 * @route '/inventory/counts/{inventoryCountSession}/approve'
 */
        approveForm.post = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: approve.url(args, options),
            method: 'post',
        })
    
    approve.form = approveForm
/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::complete
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:189
 * @route '/inventory/counts/{inventoryCountSession}/complete'
 */
export const complete = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

complete.definition = {
    methods: ["post"],
    url: '/inventory/counts/{inventoryCountSession}/complete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::complete
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:189
 * @route '/inventory/counts/{inventoryCountSession}/complete'
 */
complete.url = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { inventoryCountSession: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { inventoryCountSession: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    inventoryCountSession: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        inventoryCountSession: typeof args.inventoryCountSession === 'object'
                ? args.inventoryCountSession.id
                : args.inventoryCountSession,
                }

    return complete.definition.url
            .replace('{inventoryCountSession}', parsedArgs.inventoryCountSession.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::complete
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:189
 * @route '/inventory/counts/{inventoryCountSession}/complete'
 */
complete.post = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::complete
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:189
 * @route '/inventory/counts/{inventoryCountSession}/complete'
 */
    const completeForm = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: complete.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::complete
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:189
 * @route '/inventory/counts/{inventoryCountSession}/complete'
 */
        completeForm.post = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: complete.url(args, options),
            method: 'post',
        })
    
    complete.form = completeForm
/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::cancel
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:204
 * @route '/inventory/counts/{inventoryCountSession}/cancel'
 */
export const cancel = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

cancel.definition = {
    methods: ["post"],
    url: '/inventory/counts/{inventoryCountSession}/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::cancel
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:204
 * @route '/inventory/counts/{inventoryCountSession}/cancel'
 */
cancel.url = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { inventoryCountSession: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { inventoryCountSession: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    inventoryCountSession: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        inventoryCountSession: typeof args.inventoryCountSession === 'object'
                ? args.inventoryCountSession.id
                : args.inventoryCountSession,
                }

    return cancel.definition.url
            .replace('{inventoryCountSession}', parsedArgs.inventoryCountSession.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\InventoryCountController::cancel
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:204
 * @route '/inventory/counts/{inventoryCountSession}/cancel'
 */
cancel.post = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::cancel
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:204
 * @route '/inventory/counts/{inventoryCountSession}/cancel'
 */
    const cancelForm = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: cancel.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\InventoryCountController::cancel
 * @see app/Http/Controllers/Inventory/InventoryCountController.php:204
 * @route '/inventory/counts/{inventoryCountSession}/cancel'
 */
        cancelForm.post = (args: { inventoryCountSession: number | { id: number } } | [inventoryCountSession: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: cancel.url(args, options),
            method: 'post',
        })
    
    cancel.form = cancelForm
const InventoryCountController = { index, store, show, updateLine, submit, reviewLine, approve, complete, cancel }

export default InventoryCountController