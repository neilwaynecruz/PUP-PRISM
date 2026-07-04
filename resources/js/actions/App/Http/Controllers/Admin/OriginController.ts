import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\OriginController::index
 * @see app/Http/Controllers/Admin/OriginController.php:22
 * @route '/admin/origins'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/origins',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\OriginController::index
 * @see app/Http/Controllers/Admin/OriginController.php:22
 * @route '/admin/origins'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\OriginController::index
 * @see app/Http/Controllers/Admin/OriginController.php:22
 * @route '/admin/origins'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\OriginController::index
 * @see app/Http/Controllers/Admin/OriginController.php:22
 * @route '/admin/origins'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\OriginController::create
 * @see app/Http/Controllers/Admin/OriginController.php:54
 * @route '/admin/origins/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/admin/origins/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\OriginController::create
 * @see app/Http/Controllers/Admin/OriginController.php:54
 * @route '/admin/origins/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\OriginController::create
 * @see app/Http/Controllers/Admin/OriginController.php:54
 * @route '/admin/origins/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\OriginController::create
 * @see app/Http/Controllers/Admin/OriginController.php:54
 * @route '/admin/origins/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\OriginController::store
 * @see app/Http/Controllers/Admin/OriginController.php:61
 * @route '/admin/origins'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/origins',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\OriginController::store
 * @see app/Http/Controllers/Admin/OriginController.php:61
 * @route '/admin/origins'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\OriginController::store
 * @see app/Http/Controllers/Admin/OriginController.php:61
 * @route '/admin/origins'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\OriginController::edit
 * @see app/Http/Controllers/Admin/OriginController.php:72
 * @route '/admin/origins/{origin}/edit'
 */
export const edit = (args: { origin: number | { id: number } } | [origin: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/origins/{origin}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\OriginController::edit
 * @see app/Http/Controllers/Admin/OriginController.php:72
 * @route '/admin/origins/{origin}/edit'
 */
edit.url = (args: { origin: number | { id: number } } | [origin: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { origin: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { origin: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    origin: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        origin: typeof args.origin === 'object'
                ? args.origin.id
                : args.origin,
                }

    return edit.definition.url
            .replace('{origin}', parsedArgs.origin.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\OriginController::edit
 * @see app/Http/Controllers/Admin/OriginController.php:72
 * @route '/admin/origins/{origin}/edit'
 */
edit.get = (args: { origin: number | { id: number } } | [origin: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\OriginController::edit
 * @see app/Http/Controllers/Admin/OriginController.php:72
 * @route '/admin/origins/{origin}/edit'
 */
edit.head = (args: { origin: number | { id: number } } | [origin: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\OriginController::update
 * @see app/Http/Controllers/Admin/OriginController.php:93
 * @route '/admin/origins/{origin}'
 */
export const update = (args: { origin: number | { id: number } } | [origin: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/admin/origins/{origin}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Admin\OriginController::update
 * @see app/Http/Controllers/Admin/OriginController.php:93
 * @route '/admin/origins/{origin}'
 */
update.url = (args: { origin: number | { id: number } } | [origin: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { origin: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { origin: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    origin: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        origin: typeof args.origin === 'object'
                ? args.origin.id
                : args.origin,
                }

    return update.definition.url
            .replace('{origin}', parsedArgs.origin.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\OriginController::update
 * @see app/Http/Controllers/Admin/OriginController.php:93
 * @route '/admin/origins/{origin}'
 */
update.put = (args: { origin: number | { id: number } } | [origin: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
/**
* @see \App\Http\Controllers\Admin\OriginController::update
 * @see app/Http/Controllers/Admin/OriginController.php:93
 * @route '/admin/origins/{origin}'
 */
update.patch = (args: { origin: number | { id: number } } | [origin: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Admin\OriginController::destroy
 * @see app/Http/Controllers/Admin/OriginController.php:105
 * @route '/admin/origins/{origin}'
 */
export const destroy = (args: { origin: number | { id: number } } | [origin: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/admin/origins/{origin}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Admin\OriginController::destroy
 * @see app/Http/Controllers/Admin/OriginController.php:105
 * @route '/admin/origins/{origin}'
 */
destroy.url = (args: { origin: number | { id: number } } | [origin: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { origin: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { origin: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    origin: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        origin: typeof args.origin === 'object'
                ? args.origin.id
                : args.origin,
                }

    return destroy.definition.url
            .replace('{origin}', parsedArgs.origin.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\OriginController::destroy
 * @see app/Http/Controllers/Admin/OriginController.php:105
 * @route '/admin/origins/{origin}'
 */
destroy.delete = (args: { origin: number | { id: number } } | [origin: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Admin\OriginController::deactivate
 * @see app/Http/Controllers/Admin/OriginController.php:126
 * @route '/admin/origins/{origin}/deactivate'
 */
export const deactivate = (args: { origin: number | { id: number } } | [origin: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: deactivate.url(args, options),
    method: 'patch',
})

deactivate.definition = {
    methods: ["patch"],
    url: '/admin/origins/{origin}/deactivate',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\OriginController::deactivate
 * @see app/Http/Controllers/Admin/OriginController.php:126
 * @route '/admin/origins/{origin}/deactivate'
 */
deactivate.url = (args: { origin: number | { id: number } } | [origin: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { origin: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { origin: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    origin: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        origin: typeof args.origin === 'object'
                ? args.origin.id
                : args.origin,
                }

    return deactivate.definition.url
            .replace('{origin}', parsedArgs.origin.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\OriginController::deactivate
 * @see app/Http/Controllers/Admin/OriginController.php:126
 * @route '/admin/origins/{origin}/deactivate'
 */
deactivate.patch = (args: { origin: number | { id: number } } | [origin: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: deactivate.url(args, options),
    method: 'patch',
})
const OriginController = { index, create, store, edit, update, destroy, deactivate }

export default OriginController