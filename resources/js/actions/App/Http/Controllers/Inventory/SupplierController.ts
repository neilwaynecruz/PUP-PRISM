import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\SupplierController::index
 * @see app/Http/Controllers/Inventory/SupplierController.php:22
 * @route '/inventory/suppliers'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/inventory/suppliers',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\SupplierController::index
 * @see app/Http/Controllers/Inventory/SupplierController.php:22
 * @route '/inventory/suppliers'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\SupplierController::index
 * @see app/Http/Controllers/Inventory/SupplierController.php:22
 * @route '/inventory/suppliers'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\SupplierController::index
 * @see app/Http/Controllers/Inventory/SupplierController.php:22
 * @route '/inventory/suppliers'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\SupplierController::create
 * @see app/Http/Controllers/Inventory/SupplierController.php:57
 * @route '/inventory/suppliers/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/inventory/suppliers/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\SupplierController::create
 * @see app/Http/Controllers/Inventory/SupplierController.php:57
 * @route '/inventory/suppliers/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\SupplierController::create
 * @see app/Http/Controllers/Inventory/SupplierController.php:57
 * @route '/inventory/suppliers/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\SupplierController::create
 * @see app/Http/Controllers/Inventory/SupplierController.php:57
 * @route '/inventory/suppliers/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\SupplierController::store
 * @see app/Http/Controllers/Inventory/SupplierController.php:64
 * @route '/inventory/suppliers'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/inventory/suppliers',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\SupplierController::store
 * @see app/Http/Controllers/Inventory/SupplierController.php:64
 * @route '/inventory/suppliers'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\SupplierController::store
 * @see app/Http/Controllers/Inventory/SupplierController.php:64
 * @route '/inventory/suppliers'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\SupplierController::show
 * @see app/Http/Controllers/Inventory/SupplierController.php:77
 * @route '/inventory/suppliers/{supplier}'
 */
export const show = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/inventory/suppliers/{supplier}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\SupplierController::show
 * @see app/Http/Controllers/Inventory/SupplierController.php:77
 * @route '/inventory/suppliers/{supplier}'
 */
show.url = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { supplier: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { supplier: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    supplier: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        supplier: typeof args.supplier === 'object'
                ? args.supplier.id
                : args.supplier,
                }

    return show.definition.url
            .replace('{supplier}', parsedArgs.supplier.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\SupplierController::show
 * @see app/Http/Controllers/Inventory/SupplierController.php:77
 * @route '/inventory/suppliers/{supplier}'
 */
show.get = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\SupplierController::show
 * @see app/Http/Controllers/Inventory/SupplierController.php:77
 * @route '/inventory/suppliers/{supplier}'
 */
show.head = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\SupplierController::edit
 * @see app/Http/Controllers/Inventory/SupplierController.php:122
 * @route '/inventory/suppliers/{supplier}/edit'
 */
export const edit = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/inventory/suppliers/{supplier}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\SupplierController::edit
 * @see app/Http/Controllers/Inventory/SupplierController.php:122
 * @route '/inventory/suppliers/{supplier}/edit'
 */
edit.url = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { supplier: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { supplier: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    supplier: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        supplier: typeof args.supplier === 'object'
                ? args.supplier.id
                : args.supplier,
                }

    return edit.definition.url
            .replace('{supplier}', parsedArgs.supplier.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\SupplierController::edit
 * @see app/Http/Controllers/Inventory/SupplierController.php:122
 * @route '/inventory/suppliers/{supplier}/edit'
 */
edit.get = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\SupplierController::edit
 * @see app/Http/Controllers/Inventory/SupplierController.php:122
 * @route '/inventory/suppliers/{supplier}/edit'
 */
edit.head = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\SupplierController::update
 * @see app/Http/Controllers/Inventory/SupplierController.php:134
 * @route '/inventory/suppliers/{supplier}'
 */
export const update = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/inventory/suppliers/{supplier}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Inventory\SupplierController::update
 * @see app/Http/Controllers/Inventory/SupplierController.php:134
 * @route '/inventory/suppliers/{supplier}'
 */
update.url = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { supplier: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { supplier: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    supplier: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        supplier: typeof args.supplier === 'object'
                ? args.supplier.id
                : args.supplier,
                }

    return update.definition.url
            .replace('{supplier}', parsedArgs.supplier.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\SupplierController::update
 * @see app/Http/Controllers/Inventory/SupplierController.php:134
 * @route '/inventory/suppliers/{supplier}'
 */
update.put = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Inventory\SupplierController::destroy
 * @see app/Http/Controllers/Inventory/SupplierController.php:148
 * @route '/inventory/suppliers/{supplier}'
 */
export const destroy = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/inventory/suppliers/{supplier}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Inventory\SupplierController::destroy
 * @see app/Http/Controllers/Inventory/SupplierController.php:148
 * @route '/inventory/suppliers/{supplier}'
 */
destroy.url = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { supplier: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { supplier: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    supplier: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        supplier: typeof args.supplier === 'object'
                ? args.supplier.id
                : args.supplier,
                }

    return destroy.definition.url
            .replace('{supplier}', parsedArgs.supplier.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\SupplierController::destroy
 * @see app/Http/Controllers/Inventory/SupplierController.php:148
 * @route '/inventory/suppliers/{supplier}'
 */
destroy.delete = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})
const SupplierController = { index, create, store, show, edit, update, destroy }

export default SupplierController