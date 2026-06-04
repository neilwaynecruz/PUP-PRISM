import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\ProductController::index
 * @see app/Http/Controllers/Inventory/ProductController.php:29
 * @route '/inventory/products'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/inventory/products',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::index
 * @see app/Http/Controllers/Inventory/ProductController.php:29
 * @route '/inventory/products'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::index
 * @see app/Http/Controllers/Inventory/ProductController.php:29
 * @route '/inventory/products'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ProductController::index
 * @see app/Http/Controllers/Inventory/ProductController.php:29
 * @route '/inventory/products'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::index
 * @see app/Http/Controllers/Inventory/ProductController.php:29
 * @route '/inventory/products'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::index
 * @see app/Http/Controllers/Inventory/ProductController.php:29
 * @route '/inventory/products'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\ProductController::index
 * @see app/Http/Controllers/Inventory/ProductController.php:29
 * @route '/inventory/products'
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
* @see \App\Http\Controllers\Inventory\ProductController::create
 * @see app/Http/Controllers/Inventory/ProductController.php:87
 * @route '/inventory/products/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/inventory/products/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::create
 * @see app/Http/Controllers/Inventory/ProductController.php:87
 * @route '/inventory/products/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::create
 * @see app/Http/Controllers/Inventory/ProductController.php:87
 * @route '/inventory/products/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ProductController::create
 * @see app/Http/Controllers/Inventory/ProductController.php:87
 * @route '/inventory/products/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::create
 * @see app/Http/Controllers/Inventory/ProductController.php:87
 * @route '/inventory/products/create'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::create
 * @see app/Http/Controllers/Inventory/ProductController.php:87
 * @route '/inventory/products/create'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\ProductController::create
 * @see app/Http/Controllers/Inventory/ProductController.php:87
 * @route '/inventory/products/create'
 */
        createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    create.form = createForm
/**
* @see \App\Http\Controllers\Inventory\ProductController::store
 * @see app/Http/Controllers/Inventory/ProductController.php:97
 * @route '/inventory/products'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/inventory/products',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::store
 * @see app/Http/Controllers/Inventory/ProductController.php:97
 * @route '/inventory/products'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::store
 * @see app/Http/Controllers/Inventory/ProductController.php:97
 * @route '/inventory/products'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::store
 * @see app/Http/Controllers/Inventory/ProductController.php:97
 * @route '/inventory/products'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::store
 * @see app/Http/Controllers/Inventory/ProductController.php:97
 * @route '/inventory/products'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Inventory\ProductController::trash
 * @see app/Http/Controllers/Inventory/ProductController.php:279
 * @route '/inventory/products/trash'
 */
export const trash = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: trash.url(options),
    method: 'get',
})

trash.definition = {
    methods: ["get","head"],
    url: '/inventory/products/trash',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::trash
 * @see app/Http/Controllers/Inventory/ProductController.php:279
 * @route '/inventory/products/trash'
 */
trash.url = (options?: RouteQueryOptions) => {
    return trash.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::trash
 * @see app/Http/Controllers/Inventory/ProductController.php:279
 * @route '/inventory/products/trash'
 */
trash.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: trash.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ProductController::trash
 * @see app/Http/Controllers/Inventory/ProductController.php:279
 * @route '/inventory/products/trash'
 */
trash.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: trash.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::trash
 * @see app/Http/Controllers/Inventory/ProductController.php:279
 * @route '/inventory/products/trash'
 */
    const trashForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: trash.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::trash
 * @see app/Http/Controllers/Inventory/ProductController.php:279
 * @route '/inventory/products/trash'
 */
        trashForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: trash.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\ProductController::trash
 * @see app/Http/Controllers/Inventory/ProductController.php:279
 * @route '/inventory/products/trash'
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
* @see \App\Http\Controllers\Inventory\ProductController::show
 * @see app/Http/Controllers/Inventory/ProductController.php:131
 * @route '/inventory/products/{product}'
 */
export const show = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/inventory/products/{product}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::show
 * @see app/Http/Controllers/Inventory/ProductController.php:131
 * @route '/inventory/products/{product}'
 */
show.url = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { product: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    product: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        product: args.product,
                }

    return show.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::show
 * @see app/Http/Controllers/Inventory/ProductController.php:131
 * @route '/inventory/products/{product}'
 */
show.get = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ProductController::show
 * @see app/Http/Controllers/Inventory/ProductController.php:131
 * @route '/inventory/products/{product}'
 */
show.head = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::show
 * @see app/Http/Controllers/Inventory/ProductController.php:131
 * @route '/inventory/products/{product}'
 */
    const showForm = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::show
 * @see app/Http/Controllers/Inventory/ProductController.php:131
 * @route '/inventory/products/{product}'
 */
        showForm.get = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\ProductController::show
 * @see app/Http/Controllers/Inventory/ProductController.php:131
 * @route '/inventory/products/{product}'
 */
        showForm.head = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Inventory\ProductController::edit
 * @see app/Http/Controllers/Inventory/ProductController.php:204
 * @route '/inventory/products/{product}/edit'
 */
export const edit = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/inventory/products/{product}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::edit
 * @see app/Http/Controllers/Inventory/ProductController.php:204
 * @route '/inventory/products/{product}/edit'
 */
edit.url = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { product: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    product: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        product: args.product,
                }

    return edit.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::edit
 * @see app/Http/Controllers/Inventory/ProductController.php:204
 * @route '/inventory/products/{product}/edit'
 */
edit.get = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ProductController::edit
 * @see app/Http/Controllers/Inventory/ProductController.php:204
 * @route '/inventory/products/{product}/edit'
 */
edit.head = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::edit
 * @see app/Http/Controllers/Inventory/ProductController.php:204
 * @route '/inventory/products/{product}/edit'
 */
    const editForm = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: edit.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::edit
 * @see app/Http/Controllers/Inventory/ProductController.php:204
 * @route '/inventory/products/{product}/edit'
 */
        editForm.get = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\ProductController::edit
 * @see app/Http/Controllers/Inventory/ProductController.php:204
 * @route '/inventory/products/{product}/edit'
 */
        editForm.head = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    edit.form = editForm
/**
* @see \App\Http\Controllers\Inventory\ProductController::update
 * @see app/Http/Controllers/Inventory/ProductController.php:238
 * @route '/inventory/products/{product}'
 */
export const update = (args: { product: string | number | { id: string | number } } | [product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/inventory/products/{product}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::update
 * @see app/Http/Controllers/Inventory/ProductController.php:238
 * @route '/inventory/products/{product}'
 */
update.url = (args: { product: string | number | { id: string | number } } | [product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { product: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { product: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    product: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        product: typeof args.product === 'object'
                ? args.product.id
                : args.product,
                }

    return update.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::update
 * @see app/Http/Controllers/Inventory/ProductController.php:238
 * @route '/inventory/products/{product}'
 */
update.put = (args: { product: string | number | { id: string | number } } | [product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::update
 * @see app/Http/Controllers/Inventory/ProductController.php:238
 * @route '/inventory/products/{product}'
 */
    const updateForm = (args: { product: string | number | { id: string | number } } | [product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::update
 * @see app/Http/Controllers/Inventory/ProductController.php:238
 * @route '/inventory/products/{product}'
 */
        updateForm.put = (args: { product: string | number | { id: string | number } } | [product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\Inventory\ProductController::destroy
 * @see app/Http/Controllers/Inventory/ProductController.php:252
 * @route '/inventory/products/{product}'
 */
export const destroy = (args: { product: string | number | { id: string | number } } | [product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/inventory/products/{product}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::destroy
 * @see app/Http/Controllers/Inventory/ProductController.php:252
 * @route '/inventory/products/{product}'
 */
destroy.url = (args: { product: string | number | { id: string | number } } | [product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { product: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { product: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    product: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        product: typeof args.product === 'object'
                ? args.product.id
                : args.product,
                }

    return destroy.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::destroy
 * @see app/Http/Controllers/Inventory/ProductController.php:252
 * @route '/inventory/products/{product}'
 */
destroy.delete = (args: { product: string | number | { id: string | number } } | [product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::destroy
 * @see app/Http/Controllers/Inventory/ProductController.php:252
 * @route '/inventory/products/{product}'
 */
    const destroyForm = (args: { product: string | number | { id: string | number } } | [product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::destroy
 * @see app/Http/Controllers/Inventory/ProductController.php:252
 * @route '/inventory/products/{product}'
 */
        destroyForm.delete = (args: { product: string | number | { id: string | number } } | [product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Inventory\ProductController::restore
 * @see app/Http/Controllers/Inventory/ProductController.php:330
 * @route '/inventory/products/{product}/restore'
 */
export const restore = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: restore.url(args, options),
    method: 'put',
})

restore.definition = {
    methods: ["put"],
    url: '/inventory/products/{product}/restore',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::restore
 * @see app/Http/Controllers/Inventory/ProductController.php:330
 * @route '/inventory/products/{product}/restore'
 */
restore.url = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { product: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    product: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        product: args.product,
                }

    return restore.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::restore
 * @see app/Http/Controllers/Inventory/ProductController.php:330
 * @route '/inventory/products/{product}/restore'
 */
restore.put = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: restore.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::restore
 * @see app/Http/Controllers/Inventory/ProductController.php:330
 * @route '/inventory/products/{product}/restore'
 */
    const restoreForm = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: restore.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::restore
 * @see app/Http/Controllers/Inventory/ProductController.php:330
 * @route '/inventory/products/{product}/restore'
 */
        restoreForm.put = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Inventory\ProductController::forceDelete
 * @see app/Http/Controllers/Inventory/ProductController.php:346
 * @route '/inventory/products/{product}/force'
 */
export const forceDelete = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: forceDelete.url(args, options),
    method: 'delete',
})

forceDelete.definition = {
    methods: ["delete"],
    url: '/inventory/products/{product}/force',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::forceDelete
 * @see app/Http/Controllers/Inventory/ProductController.php:346
 * @route '/inventory/products/{product}/force'
 */
forceDelete.url = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { product: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    product: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        product: args.product,
                }

    return forceDelete.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::forceDelete
 * @see app/Http/Controllers/Inventory/ProductController.php:346
 * @route '/inventory/products/{product}/force'
 */
forceDelete.delete = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: forceDelete.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::forceDelete
 * @see app/Http/Controllers/Inventory/ProductController.php:346
 * @route '/inventory/products/{product}/force'
 */
    const forceDeleteForm = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: forceDelete.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::forceDelete
 * @see app/Http/Controllers/Inventory/ProductController.php:346
 * @route '/inventory/products/{product}/force'
 */
        forceDeleteForm.delete = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: forceDelete.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    forceDelete.form = forceDeleteForm
/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkRestore
 * @see app/Http/Controllers/Inventory/ProductController.php:370
 * @route '/inventory/products/bulk-restore'
 */
export const bulkRestore = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkRestore.url(options),
    method: 'post',
})

bulkRestore.definition = {
    methods: ["post"],
    url: '/inventory/products/bulk-restore',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkRestore
 * @see app/Http/Controllers/Inventory/ProductController.php:370
 * @route '/inventory/products/bulk-restore'
 */
bulkRestore.url = (options?: RouteQueryOptions) => {
    return bulkRestore.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkRestore
 * @see app/Http/Controllers/Inventory/ProductController.php:370
 * @route '/inventory/products/bulk-restore'
 */
bulkRestore.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkRestore.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::bulkRestore
 * @see app/Http/Controllers/Inventory/ProductController.php:370
 * @route '/inventory/products/bulk-restore'
 */
    const bulkRestoreForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: bulkRestore.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::bulkRestore
 * @see app/Http/Controllers/Inventory/ProductController.php:370
 * @route '/inventory/products/bulk-restore'
 */
        bulkRestoreForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: bulkRestore.url(options),
            method: 'post',
        })
    
    bulkRestore.form = bulkRestoreForm
/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkForceDelete
 * @see app/Http/Controllers/Inventory/ProductController.php:398
 * @route '/inventory/products/bulk-force-delete'
 */
export const bulkForceDelete = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkForceDelete.url(options),
    method: 'post',
})

bulkForceDelete.definition = {
    methods: ["post"],
    url: '/inventory/products/bulk-force-delete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkForceDelete
 * @see app/Http/Controllers/Inventory/ProductController.php:398
 * @route '/inventory/products/bulk-force-delete'
 */
bulkForceDelete.url = (options?: RouteQueryOptions) => {
    return bulkForceDelete.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkForceDelete
 * @see app/Http/Controllers/Inventory/ProductController.php:398
 * @route '/inventory/products/bulk-force-delete'
 */
bulkForceDelete.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkForceDelete.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::bulkForceDelete
 * @see app/Http/Controllers/Inventory/ProductController.php:398
 * @route '/inventory/products/bulk-force-delete'
 */
    const bulkForceDeleteForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: bulkForceDelete.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::bulkForceDelete
 * @see app/Http/Controllers/Inventory/ProductController.php:398
 * @route '/inventory/products/bulk-force-delete'
 */
        bulkForceDeleteForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: bulkForceDelete.url(options),
            method: 'post',
        })
    
    bulkForceDelete.form = bulkForceDeleteForm
/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkActivate
 * @see app/Http/Controllers/Inventory/ProductController.php:438
 * @route '/inventory/products/bulk-activate'
 */
export const bulkActivate = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkActivate.url(options),
    method: 'post',
})

bulkActivate.definition = {
    methods: ["post"],
    url: '/inventory/products/bulk-activate',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkActivate
 * @see app/Http/Controllers/Inventory/ProductController.php:438
 * @route '/inventory/products/bulk-activate'
 */
bulkActivate.url = (options?: RouteQueryOptions) => {
    return bulkActivate.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkActivate
 * @see app/Http/Controllers/Inventory/ProductController.php:438
 * @route '/inventory/products/bulk-activate'
 */
bulkActivate.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkActivate.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::bulkActivate
 * @see app/Http/Controllers/Inventory/ProductController.php:438
 * @route '/inventory/products/bulk-activate'
 */
    const bulkActivateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: bulkActivate.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::bulkActivate
 * @see app/Http/Controllers/Inventory/ProductController.php:438
 * @route '/inventory/products/bulk-activate'
 */
        bulkActivateForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: bulkActivate.url(options),
            method: 'post',
        })
    
    bulkActivate.form = bulkActivateForm
/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkDeactivate
 * @see app/Http/Controllers/Inventory/ProductController.php:481
 * @route '/inventory/products/bulk-deactivate'
 */
export const bulkDeactivate = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkDeactivate.url(options),
    method: 'post',
})

bulkDeactivate.definition = {
    methods: ["post"],
    url: '/inventory/products/bulk-deactivate',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkDeactivate
 * @see app/Http/Controllers/Inventory/ProductController.php:481
 * @route '/inventory/products/bulk-deactivate'
 */
bulkDeactivate.url = (options?: RouteQueryOptions) => {
    return bulkDeactivate.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkDeactivate
 * @see app/Http/Controllers/Inventory/ProductController.php:481
 * @route '/inventory/products/bulk-deactivate'
 */
bulkDeactivate.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkDeactivate.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::bulkDeactivate
 * @see app/Http/Controllers/Inventory/ProductController.php:481
 * @route '/inventory/products/bulk-deactivate'
 */
    const bulkDeactivateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: bulkDeactivate.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::bulkDeactivate
 * @see app/Http/Controllers/Inventory/ProductController.php:481
 * @route '/inventory/products/bulk-deactivate'
 */
        bulkDeactivateForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: bulkDeactivate.url(options),
            method: 'post',
        })
    
    bulkDeactivate.form = bulkDeactivateForm
/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkChangeCategory
 * @see app/Http/Controllers/Inventory/ProductController.php:524
 * @route '/inventory/products/bulk-change-category'
 */
export const bulkChangeCategory = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkChangeCategory.url(options),
    method: 'post',
})

bulkChangeCategory.definition = {
    methods: ["post"],
    url: '/inventory/products/bulk-change-category',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkChangeCategory
 * @see app/Http/Controllers/Inventory/ProductController.php:524
 * @route '/inventory/products/bulk-change-category'
 */
bulkChangeCategory.url = (options?: RouteQueryOptions) => {
    return bulkChangeCategory.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkChangeCategory
 * @see app/Http/Controllers/Inventory/ProductController.php:524
 * @route '/inventory/products/bulk-change-category'
 */
bulkChangeCategory.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkChangeCategory.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::bulkChangeCategory
 * @see app/Http/Controllers/Inventory/ProductController.php:524
 * @route '/inventory/products/bulk-change-category'
 */
    const bulkChangeCategoryForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: bulkChangeCategory.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::bulkChangeCategory
 * @see app/Http/Controllers/Inventory/ProductController.php:524
 * @route '/inventory/products/bulk-change-category'
 */
        bulkChangeCategoryForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: bulkChangeCategory.url(options),
            method: 'post',
        })
    
    bulkChangeCategory.form = bulkChangeCategoryForm
const ProductController = { index, create, store, trash, show, edit, update, destroy, restore, forceDelete, bulkRestore, bulkForceDelete, bulkActivate, bulkDeactivate, bulkChangeCategory }

export default ProductController