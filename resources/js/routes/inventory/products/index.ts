import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
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
* @see \App\Http\Controllers\Inventory\ProductController::index
 * @see app/Http/Controllers/Inventory/ProductController.php:33
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
 * @see app/Http/Controllers/Inventory/ProductController.php:33
 * @route '/inventory/products'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::index
 * @see app/Http/Controllers/Inventory/ProductController.php:33
 * @route '/inventory/products'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ProductController::index
 * @see app/Http/Controllers/Inventory/ProductController.php:33
 * @route '/inventory/products'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\ProductController::create
 * @see app/Http/Controllers/Inventory/ProductController.php:96
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
 * @see app/Http/Controllers/Inventory/ProductController.php:96
 * @route '/inventory/products/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::create
 * @see app/Http/Controllers/Inventory/ProductController.php:96
 * @route '/inventory/products/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ProductController::create
 * @see app/Http/Controllers/Inventory/ProductController.php:96
 * @route '/inventory/products/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\ProductController::store
 * @see app/Http/Controllers/Inventory/ProductController.php:107
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
 * @see app/Http/Controllers/Inventory/ProductController.php:107
 * @route '/inventory/products'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::store
 * @see app/Http/Controllers/Inventory/ProductController.php:107
 * @route '/inventory/products'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\ProductController::trash
 * @see app/Http/Controllers/Inventory/ProductController.php:319
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
 * @see app/Http/Controllers/Inventory/ProductController.php:319
 * @route '/inventory/products/trash'
 */
trash.url = (options?: RouteQueryOptions) => {
    return trash.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::trash
 * @see app/Http/Controllers/Inventory/ProductController.php:319
 * @route '/inventory/products/trash'
 */
trash.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: trash.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ProductController::trash
 * @see app/Http/Controllers/Inventory/ProductController.php:319
 * @route '/inventory/products/trash'
 */
trash.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: trash.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\ProductController::show
 * @see app/Http/Controllers/Inventory/ProductController.php:144
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
 * @see app/Http/Controllers/Inventory/ProductController.php:144
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
 * @see app/Http/Controllers/Inventory/ProductController.php:144
 * @route '/inventory/products/{product}'
 */
show.get = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ProductController::show
 * @see app/Http/Controllers/Inventory/ProductController.php:144
 * @route '/inventory/products/{product}'
 */
show.head = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\ProductController::edit
 * @see app/Http/Controllers/Inventory/ProductController.php:242
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
 * @see app/Http/Controllers/Inventory/ProductController.php:242
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
 * @see app/Http/Controllers/Inventory/ProductController.php:242
 * @route '/inventory/products/{product}/edit'
 */
edit.get = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\ProductController::edit
 * @see app/Http/Controllers/Inventory/ProductController.php:242
 * @route '/inventory/products/{product}/edit'
 */
edit.head = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\ProductController::update
 * @see app/Http/Controllers/Inventory/ProductController.php:278
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
 * @see app/Http/Controllers/Inventory/ProductController.php:278
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
 * @see app/Http/Controllers/Inventory/ProductController.php:278
 * @route '/inventory/products/{product}'
 */
update.put = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Inventory\ProductController::destroy
 * @see app/Http/Controllers/Inventory/ProductController.php:292
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
 * @see app/Http/Controllers/Inventory/ProductController.php:292
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
 * @see app/Http/Controllers/Inventory/ProductController.php:292
 * @route '/inventory/products/{product}'
 */
destroy.delete = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Inventory\ProductController::restore
 * @see app/Http/Controllers/Inventory/ProductController.php:370
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
 * @see app/Http/Controllers/Inventory/ProductController.php:370
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
 * @see app/Http/Controllers/Inventory/ProductController.php:370
 * @route '/inventory/products/{product}/restore'
 */
restore.put = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: restore.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Inventory\ProductController::forceDelete
 * @see app/Http/Controllers/Inventory/ProductController.php:386
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
 * @see app/Http/Controllers/Inventory/ProductController.php:386
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
 * @see app/Http/Controllers/Inventory/ProductController.php:386
 * @route '/inventory/products/{product}/force'
 */
forceDelete.delete = (args: { product: string | number } | [product: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: forceDelete.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkRestore
 * @see app/Http/Controllers/Inventory/ProductController.php:410
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
 * @see app/Http/Controllers/Inventory/ProductController.php:410
 * @route '/inventory/products/bulk-restore'
 */
bulkRestore.url = (options?: RouteQueryOptions) => {
    return bulkRestore.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkRestore
 * @see app/Http/Controllers/Inventory/ProductController.php:410
 * @route '/inventory/products/bulk-restore'
 */
bulkRestore.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkRestore.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkForceDelete
 * @see app/Http/Controllers/Inventory/ProductController.php:438
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
 * @see app/Http/Controllers/Inventory/ProductController.php:438
 * @route '/inventory/products/bulk-force-delete'
 */
bulkForceDelete.url = (options?: RouteQueryOptions) => {
    return bulkForceDelete.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkForceDelete
 * @see app/Http/Controllers/Inventory/ProductController.php:438
 * @route '/inventory/products/bulk-force-delete'
 */
bulkForceDelete.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkForceDelete.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkActivate
 * @see app/Http/Controllers/Inventory/ProductController.php:478
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
 * @see app/Http/Controllers/Inventory/ProductController.php:478
 * @route '/inventory/products/bulk-activate'
 */
bulkActivate.url = (options?: RouteQueryOptions) => {
    return bulkActivate.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkActivate
 * @see app/Http/Controllers/Inventory/ProductController.php:478
 * @route '/inventory/products/bulk-activate'
 */
bulkActivate.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkActivate.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkDeactivate
 * @see app/Http/Controllers/Inventory/ProductController.php:521
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
 * @see app/Http/Controllers/Inventory/ProductController.php:521
 * @route '/inventory/products/bulk-deactivate'
 */
bulkDeactivate.url = (options?: RouteQueryOptions) => {
    return bulkDeactivate.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkDeactivate
 * @see app/Http/Controllers/Inventory/ProductController.php:521
 * @route '/inventory/products/bulk-deactivate'
 */
bulkDeactivate.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkDeactivate.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkChangeCategory
 * @see app/Http/Controllers/Inventory/ProductController.php:564
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
 * @see app/Http/Controllers/Inventory/ProductController.php:564
 * @route '/inventory/products/bulk-change-category'
 */
bulkChangeCategory.url = (options?: RouteQueryOptions) => {
    return bulkChangeCategory.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\ProductController::bulkChangeCategory
 * @see app/Http/Controllers/Inventory/ProductController.php:564
 * @route '/inventory/products/bulk-change-category'
 */
bulkChangeCategory.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkChangeCategory.url(options),
    method: 'post',
})
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
forceDelete: Object.assign(forceDelete, forceDelete),
bulkRestore: Object.assign(bulkRestore, bulkRestore),
bulkForceDelete: Object.assign(bulkForceDelete, bulkForceDelete),
bulkActivate: Object.assign(bulkActivate, bulkActivate),
bulkDeactivate: Object.assign(bulkDeactivate, bulkDeactivate),
bulkChangeCategory: Object.assign(bulkChangeCategory, bulkChangeCategory),
}

export default products