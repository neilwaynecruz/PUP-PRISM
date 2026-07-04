import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\PositionController::index
 * @see app/Http/Controllers/Admin/PositionController.php:23
 * @route '/admin/positions'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/positions',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\PositionController::index
 * @see app/Http/Controllers/Admin/PositionController.php:23
 * @route '/admin/positions'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PositionController::index
 * @see app/Http/Controllers/Admin/PositionController.php:23
 * @route '/admin/positions'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\PositionController::index
 * @see app/Http/Controllers/Admin/PositionController.php:23
 * @route '/admin/positions'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\PositionController::create
 * @see app/Http/Controllers/Admin/PositionController.php:69
 * @route '/admin/positions/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/admin/positions/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\PositionController::create
 * @see app/Http/Controllers/Admin/PositionController.php:69
 * @route '/admin/positions/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PositionController::create
 * @see app/Http/Controllers/Admin/PositionController.php:69
 * @route '/admin/positions/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\PositionController::create
 * @see app/Http/Controllers/Admin/PositionController.php:69
 * @route '/admin/positions/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\PositionController::store
 * @see app/Http/Controllers/Admin/PositionController.php:78
 * @route '/admin/positions'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/positions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\PositionController::store
 * @see app/Http/Controllers/Admin/PositionController.php:78
 * @route '/admin/positions'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PositionController::store
 * @see app/Http/Controllers/Admin/PositionController.php:78
 * @route '/admin/positions'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\PositionController::edit
 * @see app/Http/Controllers/Admin/PositionController.php:89
 * @route '/admin/positions/{position}/edit'
 */
export const edit = (args: { position: number | { id: number } } | [position: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/positions/{position}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\PositionController::edit
 * @see app/Http/Controllers/Admin/PositionController.php:89
 * @route '/admin/positions/{position}/edit'
 */
edit.url = (args: { position: number | { id: number } } | [position: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { position: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { position: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    position: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        position: typeof args.position === 'object'
                ? args.position.id
                : args.position,
                }

    return edit.definition.url
            .replace('{position}', parsedArgs.position.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PositionController::edit
 * @see app/Http/Controllers/Admin/PositionController.php:89
 * @route '/admin/positions/{position}/edit'
 */
edit.get = (args: { position: number | { id: number } } | [position: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\PositionController::edit
 * @see app/Http/Controllers/Admin/PositionController.php:89
 * @route '/admin/positions/{position}/edit'
 */
edit.head = (args: { position: number | { id: number } } | [position: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\PositionController::update
 * @see app/Http/Controllers/Admin/PositionController.php:115
 * @route '/admin/positions/{position}'
 */
export const update = (args: { position: number | { id: number } } | [position: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/admin/positions/{position}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Admin\PositionController::update
 * @see app/Http/Controllers/Admin/PositionController.php:115
 * @route '/admin/positions/{position}'
 */
update.url = (args: { position: number | { id: number } } | [position: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { position: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { position: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    position: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        position: typeof args.position === 'object'
                ? args.position.id
                : args.position,
                }

    return update.definition.url
            .replace('{position}', parsedArgs.position.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PositionController::update
 * @see app/Http/Controllers/Admin/PositionController.php:115
 * @route '/admin/positions/{position}'
 */
update.put = (args: { position: number | { id: number } } | [position: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
/**
* @see \App\Http\Controllers\Admin\PositionController::update
 * @see app/Http/Controllers/Admin/PositionController.php:115
 * @route '/admin/positions/{position}'
 */
update.patch = (args: { position: number | { id: number } } | [position: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Admin\PositionController::destroy
 * @see app/Http/Controllers/Admin/PositionController.php:136
 * @route '/admin/positions/{position}'
 */
export const destroy = (args: { position: number | { id: number } } | [position: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/admin/positions/{position}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Admin\PositionController::destroy
 * @see app/Http/Controllers/Admin/PositionController.php:136
 * @route '/admin/positions/{position}'
 */
destroy.url = (args: { position: number | { id: number } } | [position: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { position: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { position: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    position: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        position: typeof args.position === 'object'
                ? args.position.id
                : args.position,
                }

    return destroy.definition.url
            .replace('{position}', parsedArgs.position.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PositionController::destroy
 * @see app/Http/Controllers/Admin/PositionController.php:136
 * @route '/admin/positions/{position}'
 */
destroy.delete = (args: { position: number | { id: number } } | [position: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Admin\PositionController::deactivate
 * @see app/Http/Controllers/Admin/PositionController.php:157
 * @route '/admin/positions/{position}/deactivate'
 */
export const deactivate = (args: { position: number | { id: number } } | [position: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: deactivate.url(args, options),
    method: 'patch',
})

deactivate.definition = {
    methods: ["patch"],
    url: '/admin/positions/{position}/deactivate',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\PositionController::deactivate
 * @see app/Http/Controllers/Admin/PositionController.php:157
 * @route '/admin/positions/{position}/deactivate'
 */
deactivate.url = (args: { position: number | { id: number } } | [position: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { position: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { position: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    position: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        position: typeof args.position === 'object'
                ? args.position.id
                : args.position,
                }

    return deactivate.definition.url
            .replace('{position}', parsedArgs.position.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PositionController::deactivate
 * @see app/Http/Controllers/Admin/PositionController.php:157
 * @route '/admin/positions/{position}/deactivate'
 */
deactivate.patch = (args: { position: number | { id: number } } | [position: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: deactivate.url(args, options),
    method: 'patch',
})
const positions = {
    index: Object.assign(index, index),
create: Object.assign(create, create),
store: Object.assign(store, store),
edit: Object.assign(edit, edit),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
deactivate: Object.assign(deactivate, deactivate),
}

export default positions