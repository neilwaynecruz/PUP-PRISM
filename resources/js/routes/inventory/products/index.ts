import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\ProductLabelController::label
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:17
 * @route '/inventory/products/{product}/label'
 */
export const label = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: label.url(args, options),
    method: 'get',
})

label.definition = {
    methods: ["get","head"],
    url: '/inventory/products/{product}/label',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\ProductLabelController::label
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:17
 * @route '/inventory/products/{product}/label'
 */
label.url = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return label.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductLabelController::label
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:17
 * @route '/inventory/products/{product}/label'
 */
label.get = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: label.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ProductLabelController::label
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:17
 * @route '/inventory/products/{product}/label'
 */
label.head = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: label.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductLabelController::label
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:17
 * @route '/inventory/products/{product}/label'
 */
    const labelForm = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: label.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductLabelController::label
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:17
 * @route '/inventory/products/{product}/label'
 */
        labelForm.get = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: label.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\ProductLabelController::label
 * @see app/Http/Controllers/Inventory/ProductLabelController.php:17
 * @route '/inventory/products/{product}/label'
 */
        labelForm.head = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: label.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    label.form = labelForm
/**
* @see \App\Http\Controllers\Inventory\ProductController::index
 * @see app/Http/Controllers/Inventory/ProductController.php:31
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
 * @see app/Http/Controllers/Inventory/ProductController.php:31
 * @route '/inventory/products'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::index
 * @see app/Http/Controllers/Inventory/ProductController.php:31
 * @route '/inventory/products'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ProductController::index
 * @see app/Http/Controllers/Inventory/ProductController.php:31
 * @route '/inventory/products'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::index
 * @see app/Http/Controllers/Inventory/ProductController.php:31
 * @route '/inventory/products'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::index
 * @see app/Http/Controllers/Inventory/ProductController.php:31
 * @route '/inventory/products'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\ProductController::index
 * @see app/Http/Controllers/Inventory/ProductController.php:31
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
 * @see app/Http/Controllers/Inventory/ProductController.php:95
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
 * @see app/Http/Controllers/Inventory/ProductController.php:95
 * @route '/inventory/products'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::store
 * @see app/Http/Controllers/Inventory/ProductController.php:95
 * @route '/inventory/products'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::store
 * @see app/Http/Controllers/Inventory/ProductController.php:95
 * @route '/inventory/products'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::store
 * @see app/Http/Controllers/Inventory/ProductController.php:95
 * @route '/inventory/products'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Inventory\ProductController::trash
 * @see app/Http/Controllers/Inventory/ProductController.php:184
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
 * @see app/Http/Controllers/Inventory/ProductController.php:184
 * @route '/inventory/products/trash'
 */
trash.url = (options?: RouteQueryOptions) => {
    return trash.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::trash
 * @see app/Http/Controllers/Inventory/ProductController.php:184
 * @route '/inventory/products/trash'
 */
trash.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: trash.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ProductController::trash
 * @see app/Http/Controllers/Inventory/ProductController.php:184
 * @route '/inventory/products/trash'
 */
trash.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: trash.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::trash
 * @see app/Http/Controllers/Inventory/ProductController.php:184
 * @route '/inventory/products/trash'
 */
    const trashForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: trash.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::trash
 * @see app/Http/Controllers/Inventory/ProductController.php:184
 * @route '/inventory/products/trash'
 */
        trashForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: trash.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\ProductController::trash
 * @see app/Http/Controllers/Inventory/ProductController.php:184
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
 * @see app/Http/Controllers/Inventory/ProductController.php:123
 * @route '/inventory/products/{product}'
 */
export const show = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/inventory/products/{product}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::show
 * @see app/Http/Controllers/Inventory/ProductController.php:123
 * @route '/inventory/products/{product}'
 */
show.url = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::show
 * @see app/Http/Controllers/Inventory/ProductController.php:123
 * @route '/inventory/products/{product}'
 */
show.get = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ProductController::show
 * @see app/Http/Controllers/Inventory/ProductController.php:123
 * @route '/inventory/products/{product}'
 */
show.head = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::show
 * @see app/Http/Controllers/Inventory/ProductController.php:123
 * @route '/inventory/products/{product}'
 */
    const showForm = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::show
 * @see app/Http/Controllers/Inventory/ProductController.php:123
 * @route '/inventory/products/{product}'
 */
        showForm.get = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\ProductController::show
 * @see app/Http/Controllers/Inventory/ProductController.php:123
 * @route '/inventory/products/{product}'
 */
        showForm.head = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
 * @see app/Http/Controllers/Inventory/ProductController.php:140
 * @route '/inventory/products/{product}/edit'
 */
export const edit = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/inventory/products/{product}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::edit
 * @see app/Http/Controllers/Inventory/ProductController.php:140
 * @route '/inventory/products/{product}/edit'
 */
edit.url = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return edit.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::edit
 * @see app/Http/Controllers/Inventory/ProductController.php:140
 * @route '/inventory/products/{product}/edit'
 */
edit.get = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ProductController::edit
 * @see app/Http/Controllers/Inventory/ProductController.php:140
 * @route '/inventory/products/{product}/edit'
 */
edit.head = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::edit
 * @see app/Http/Controllers/Inventory/ProductController.php:140
 * @route '/inventory/products/{product}/edit'
 */
    const editForm = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: edit.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\ProductController::edit
 * @see app/Http/Controllers/Inventory/ProductController.php:140
 * @route '/inventory/products/{product}/edit'
 */
        editForm.get = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\ProductController::edit
 * @see app/Http/Controllers/Inventory/ProductController.php:140
 * @route '/inventory/products/{product}/edit'
 */
        editForm.head = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
 * @see app/Http/Controllers/Inventory/ProductController.php:152
 * @route '/inventory/products/{product}'
 */
export const update = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/inventory/products/{product}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::update
 * @see app/Http/Controllers/Inventory/ProductController.php:152
 * @route '/inventory/products/{product}'
 */
update.url = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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
 * @see app/Http/Controllers/Inventory/ProductController.php:152
 * @route '/inventory/products/{product}'
 */
update.put = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::update
 * @see app/Http/Controllers/Inventory/ProductController.php:152
 * @route '/inventory/products/{product}'
 */
    const updateForm = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
 * @see app/Http/Controllers/Inventory/ProductController.php:152
 * @route '/inventory/products/{product}'
 */
        updateForm.put = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
 * @see app/Http/Controllers/Inventory/ProductController.php:161
 * @route '/inventory/products/{product}'
 */
export const destroy = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/inventory/products/{product}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Inventory\ProductController::destroy
 * @see app/Http/Controllers/Inventory/ProductController.php:161
 * @route '/inventory/products/{product}'
 */
destroy.url = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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
 * @see app/Http/Controllers/Inventory/ProductController.php:161
 * @route '/inventory/products/{product}'
 */
destroy.delete = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::destroy
 * @see app/Http/Controllers/Inventory/ProductController.php:161
 * @route '/inventory/products/{product}'
 */
    const destroyForm = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
 * @see app/Http/Controllers/Inventory/ProductController.php:161
 * @route '/inventory/products/{product}'
 */
        destroyForm.delete = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
 * @see app/Http/Controllers/Inventory/ProductController.php:205
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
 * @see app/Http/Controllers/Inventory/ProductController.php:205
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
 * @see app/Http/Controllers/Inventory/ProductController.php:205
 * @route '/inventory/products/{product}/restore'
 */
restore.put = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: restore.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Inventory\ProductController::restore
 * @see app/Http/Controllers/Inventory/ProductController.php:205
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
 * @see app/Http/Controllers/Inventory/ProductController.php:205
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
const products = {
    label: Object.assign(label, label),
index: Object.assign(index, index),
create: Object.assign(create, create),
store: Object.assign(store, store),
trash: Object.assign(trash, trash),
show: Object.assign(show, show),
edit: Object.assign(edit, edit),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
restore: Object.assign(restore, restore),
}

export default products