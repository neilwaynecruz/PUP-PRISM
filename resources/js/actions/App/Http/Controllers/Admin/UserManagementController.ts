import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\UserManagementController::index
 * @see app/Http/Controllers/Admin/UserManagementController.php:21
 * @route '/admin/users'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/users',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\UserManagementController::index
 * @see app/Http/Controllers/Admin/UserManagementController.php:21
 * @route '/admin/users'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\UserManagementController::index
 * @see app/Http/Controllers/Admin/UserManagementController.php:21
 * @route '/admin/users'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\UserManagementController::index
 * @see app/Http/Controllers/Admin/UserManagementController.php:21
 * @route '/admin/users'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::create
 * @see app/Http/Controllers/Admin/UserManagementController.php:64
 * @route '/admin/users/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/admin/users/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\UserManagementController::create
 * @see app/Http/Controllers/Admin/UserManagementController.php:64
 * @route '/admin/users/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\UserManagementController::create
 * @see app/Http/Controllers/Admin/UserManagementController.php:64
 * @route '/admin/users/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\UserManagementController::create
 * @see app/Http/Controllers/Admin/UserManagementController.php:64
 * @route '/admin/users/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::store
 * @see app/Http/Controllers/Admin/UserManagementController.php:74
 * @route '/admin/users'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/users',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\UserManagementController::store
 * @see app/Http/Controllers/Admin/UserManagementController.php:74
 * @route '/admin/users'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\UserManagementController::store
 * @see app/Http/Controllers/Admin/UserManagementController.php:74
 * @route '/admin/users'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::edit
 * @see app/Http/Controllers/Admin/UserManagementController.php:112
 * @route '/admin/users/{managedUser}/edit'
 */
export const edit = (args: { managedUser: number | { id: number } } | [managedUser: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/users/{managedUser}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\UserManagementController::edit
 * @see app/Http/Controllers/Admin/UserManagementController.php:112
 * @route '/admin/users/{managedUser}/edit'
 */
edit.url = (args: { managedUser: number | { id: number } } | [managedUser: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { managedUser: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { managedUser: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    managedUser: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        managedUser: typeof args.managedUser === 'object'
                ? args.managedUser.id
                : args.managedUser,
                }

    return edit.definition.url
            .replace('{managedUser}', parsedArgs.managedUser.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\UserManagementController::edit
 * @see app/Http/Controllers/Admin/UserManagementController.php:112
 * @route '/admin/users/{managedUser}/edit'
 */
edit.get = (args: { managedUser: number | { id: number } } | [managedUser: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\UserManagementController::edit
 * @see app/Http/Controllers/Admin/UserManagementController.php:112
 * @route '/admin/users/{managedUser}/edit'
 */
edit.head = (args: { managedUser: number | { id: number } } | [managedUser: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::update
 * @see app/Http/Controllers/Admin/UserManagementController.php:128
 * @route '/admin/users/{managedUser}'
 */
export const update = (args: { managedUser: number | { id: number } } | [managedUser: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/admin/users/{managedUser}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Admin\UserManagementController::update
 * @see app/Http/Controllers/Admin/UserManagementController.php:128
 * @route '/admin/users/{managedUser}'
 */
update.url = (args: { managedUser: number | { id: number } } | [managedUser: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { managedUser: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { managedUser: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    managedUser: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        managedUser: typeof args.managedUser === 'object'
                ? args.managedUser.id
                : args.managedUser,
                }

    return update.definition.url
            .replace('{managedUser}', parsedArgs.managedUser.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\UserManagementController::update
 * @see app/Http/Controllers/Admin/UserManagementController.php:128
 * @route '/admin/users/{managedUser}'
 */
update.put = (args: { managedUser: number | { id: number } } | [managedUser: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::deactivate
 * @see app/Http/Controllers/Admin/UserManagementController.php:173
 * @route '/admin/users/{managedUser}/deactivate'
 */
export const deactivate = (args: { managedUser: number | { id: number } } | [managedUser: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: deactivate.url(args, options),
    method: 'patch',
})

deactivate.definition = {
    methods: ["patch"],
    url: '/admin/users/{managedUser}/deactivate',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\UserManagementController::deactivate
 * @see app/Http/Controllers/Admin/UserManagementController.php:173
 * @route '/admin/users/{managedUser}/deactivate'
 */
deactivate.url = (args: { managedUser: number | { id: number } } | [managedUser: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { managedUser: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { managedUser: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    managedUser: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        managedUser: typeof args.managedUser === 'object'
                ? args.managedUser.id
                : args.managedUser,
                }

    return deactivate.definition.url
            .replace('{managedUser}', parsedArgs.managedUser.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\UserManagementController::deactivate
 * @see app/Http/Controllers/Admin/UserManagementController.php:173
 * @route '/admin/users/{managedUser}/deactivate'
 */
deactivate.patch = (args: { managedUser: number | { id: number } } | [managedUser: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: deactivate.url(args, options),
    method: 'patch',
})
const UserManagementController = { index, create, store, edit, update, deactivate }

export default UserManagementController