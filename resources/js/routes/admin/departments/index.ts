import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\DepartmentController::index
 * @see app/Http/Controllers/Admin/DepartmentController.php:22
 * @route '/admin/departments'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/departments',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\DepartmentController::index
 * @see app/Http/Controllers/Admin/DepartmentController.php:22
 * @route '/admin/departments'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\DepartmentController::index
 * @see app/Http/Controllers/Admin/DepartmentController.php:22
 * @route '/admin/departments'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\DepartmentController::index
 * @see app/Http/Controllers/Admin/DepartmentController.php:22
 * @route '/admin/departments'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\DepartmentController::index
 * @see app/Http/Controllers/Admin/DepartmentController.php:22
 * @route '/admin/departments'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\DepartmentController::index
 * @see app/Http/Controllers/Admin/DepartmentController.php:22
 * @route '/admin/departments'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\DepartmentController::index
 * @see app/Http/Controllers/Admin/DepartmentController.php:22
 * @route '/admin/departments'
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
* @see \App\Http\Controllers\Admin\DepartmentController::create
 * @see app/Http/Controllers/Admin/DepartmentController.php:61
 * @route '/admin/departments/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/admin/departments/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\DepartmentController::create
 * @see app/Http/Controllers/Admin/DepartmentController.php:61
 * @route '/admin/departments/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\DepartmentController::create
 * @see app/Http/Controllers/Admin/DepartmentController.php:61
 * @route '/admin/departments/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\DepartmentController::create
 * @see app/Http/Controllers/Admin/DepartmentController.php:61
 * @route '/admin/departments/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\DepartmentController::create
 * @see app/Http/Controllers/Admin/DepartmentController.php:61
 * @route '/admin/departments/create'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\DepartmentController::create
 * @see app/Http/Controllers/Admin/DepartmentController.php:61
 * @route '/admin/departments/create'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\DepartmentController::create
 * @see app/Http/Controllers/Admin/DepartmentController.php:61
 * @route '/admin/departments/create'
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
* @see \App\Http\Controllers\Admin\DepartmentController::store
 * @see app/Http/Controllers/Admin/DepartmentController.php:68
 * @route '/admin/departments'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/departments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\DepartmentController::store
 * @see app/Http/Controllers/Admin/DepartmentController.php:68
 * @route '/admin/departments'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\DepartmentController::store
 * @see app/Http/Controllers/Admin/DepartmentController.php:68
 * @route '/admin/departments'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\DepartmentController::store
 * @see app/Http/Controllers/Admin/DepartmentController.php:68
 * @route '/admin/departments'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\DepartmentController::store
 * @see app/Http/Controllers/Admin/DepartmentController.php:68
 * @route '/admin/departments'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Admin\DepartmentController::edit
 * @see app/Http/Controllers/Admin/DepartmentController.php:79
 * @route '/admin/departments/{department}/edit'
 */
export const edit = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/departments/{department}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\DepartmentController::edit
 * @see app/Http/Controllers/Admin/DepartmentController.php:79
 * @route '/admin/departments/{department}/edit'
 */
edit.url = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { department: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { department: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    department: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        department: typeof args.department === 'object'
                ? args.department.id
                : args.department,
                }

    return edit.definition.url
            .replace('{department}', parsedArgs.department.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\DepartmentController::edit
 * @see app/Http/Controllers/Admin/DepartmentController.php:79
 * @route '/admin/departments/{department}/edit'
 */
edit.get = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\DepartmentController::edit
 * @see app/Http/Controllers/Admin/DepartmentController.php:79
 * @route '/admin/departments/{department}/edit'
 */
edit.head = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\DepartmentController::edit
 * @see app/Http/Controllers/Admin/DepartmentController.php:79
 * @route '/admin/departments/{department}/edit'
 */
    const editForm = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: edit.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\DepartmentController::edit
 * @see app/Http/Controllers/Admin/DepartmentController.php:79
 * @route '/admin/departments/{department}/edit'
 */
        editForm.get = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\DepartmentController::edit
 * @see app/Http/Controllers/Admin/DepartmentController.php:79
 * @route '/admin/departments/{department}/edit'
 */
        editForm.head = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Admin\DepartmentController::update
 * @see app/Http/Controllers/Admin/DepartmentController.php:101
 * @route '/admin/departments/{department}'
 */
export const update = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/admin/departments/{department}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Admin\DepartmentController::update
 * @see app/Http/Controllers/Admin/DepartmentController.php:101
 * @route '/admin/departments/{department}'
 */
update.url = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { department: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { department: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    department: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        department: typeof args.department === 'object'
                ? args.department.id
                : args.department,
                }

    return update.definition.url
            .replace('{department}', parsedArgs.department.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\DepartmentController::update
 * @see app/Http/Controllers/Admin/DepartmentController.php:101
 * @route '/admin/departments/{department}'
 */
update.put = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
/**
* @see \App\Http\Controllers\Admin\DepartmentController::update
 * @see app/Http/Controllers/Admin/DepartmentController.php:101
 * @route '/admin/departments/{department}'
 */
update.patch = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\DepartmentController::update
 * @see app/Http/Controllers/Admin/DepartmentController.php:101
 * @route '/admin/departments/{department}'
 */
    const updateForm = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\DepartmentController::update
 * @see app/Http/Controllers/Admin/DepartmentController.php:101
 * @route '/admin/departments/{department}'
 */
        updateForm.put = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \App\Http\Controllers\Admin\DepartmentController::update
 * @see app/Http/Controllers/Admin/DepartmentController.php:101
 * @route '/admin/departments/{department}'
 */
        updateForm.patch = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\Admin\DepartmentController::destroy
 * @see app/Http/Controllers/Admin/DepartmentController.php:113
 * @route '/admin/departments/{department}'
 */
export const destroy = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/admin/departments/{department}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Admin\DepartmentController::destroy
 * @see app/Http/Controllers/Admin/DepartmentController.php:113
 * @route '/admin/departments/{department}'
 */
destroy.url = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { department: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { department: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    department: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        department: typeof args.department === 'object'
                ? args.department.id
                : args.department,
                }

    return destroy.definition.url
            .replace('{department}', parsedArgs.department.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\DepartmentController::destroy
 * @see app/Http/Controllers/Admin/DepartmentController.php:113
 * @route '/admin/departments/{department}'
 */
destroy.delete = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Admin\DepartmentController::destroy
 * @see app/Http/Controllers/Admin/DepartmentController.php:113
 * @route '/admin/departments/{department}'
 */
    const destroyForm = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\DepartmentController::destroy
 * @see app/Http/Controllers/Admin/DepartmentController.php:113
 * @route '/admin/departments/{department}'
 */
        destroyForm.delete = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Admin\DepartmentController::deactivate
 * @see app/Http/Controllers/Admin/DepartmentController.php:134
 * @route '/admin/departments/{department}/deactivate'
 */
export const deactivate = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: deactivate.url(args, options),
    method: 'patch',
})

deactivate.definition = {
    methods: ["patch"],
    url: '/admin/departments/{department}/deactivate',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\DepartmentController::deactivate
 * @see app/Http/Controllers/Admin/DepartmentController.php:134
 * @route '/admin/departments/{department}/deactivate'
 */
deactivate.url = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { department: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { department: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    department: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        department: typeof args.department === 'object'
                ? args.department.id
                : args.department,
                }

    return deactivate.definition.url
            .replace('{department}', parsedArgs.department.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\DepartmentController::deactivate
 * @see app/Http/Controllers/Admin/DepartmentController.php:134
 * @route '/admin/departments/{department}/deactivate'
 */
deactivate.patch = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: deactivate.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\DepartmentController::deactivate
 * @see app/Http/Controllers/Admin/DepartmentController.php:134
 * @route '/admin/departments/{department}/deactivate'
 */
    const deactivateForm = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: deactivate.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\DepartmentController::deactivate
 * @see app/Http/Controllers/Admin/DepartmentController.php:134
 * @route '/admin/departments/{department}/deactivate'
 */
        deactivateForm.patch = (args: { department: number | { id: number } } | [department: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: deactivate.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    deactivate.form = deactivateForm
const departments = {
    index: Object.assign(index, index),
create: Object.assign(create, create),
store: Object.assign(store, store),
edit: Object.assign(edit, edit),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
deactivate: Object.assign(deactivate, deactivate),
}

export default departments