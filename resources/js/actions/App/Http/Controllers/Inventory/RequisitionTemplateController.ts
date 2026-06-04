import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::store
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:15
 * @route '/inventory/requisition-templates'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/inventory/requisition-templates',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::store
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:15
 * @route '/inventory/requisition-templates'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::store
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:15
 * @route '/inventory/requisition-templates'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::store
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:15
 * @route '/inventory/requisition-templates'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::store
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:15
 * @route '/inventory/requisition-templates'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::update
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:37
 * @route '/inventory/requisition-templates/{requisitionTemplate}'
 */
export const update = (args: { requisitionTemplate: number | { id: number } } | [requisitionTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/inventory/requisition-templates/{requisitionTemplate}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::update
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:37
 * @route '/inventory/requisition-templates/{requisitionTemplate}'
 */
update.url = (args: { requisitionTemplate: number | { id: number } } | [requisitionTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisitionTemplate: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisitionTemplate: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisitionTemplate: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisitionTemplate: typeof args.requisitionTemplate === 'object'
                ? args.requisitionTemplate.id
                : args.requisitionTemplate,
                }

    return update.definition.url
            .replace('{requisitionTemplate}', parsedArgs.requisitionTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::update
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:37
 * @route '/inventory/requisition-templates/{requisitionTemplate}'
 */
update.put = (args: { requisitionTemplate: number | { id: number } } | [requisitionTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::update
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:37
 * @route '/inventory/requisition-templates/{requisitionTemplate}'
 */
    const updateForm = (args: { requisitionTemplate: number | { id: number } } | [requisitionTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::update
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:37
 * @route '/inventory/requisition-templates/{requisitionTemplate}'
 */
        updateForm.put = (args: { requisitionTemplate: number | { id: number } } | [requisitionTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::duplicate
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:60
 * @route '/inventory/requisition-templates/{requisitionTemplate}/duplicate'
 */
export const duplicate = (args: { requisitionTemplate: number | { id: number } } | [requisitionTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: duplicate.url(args, options),
    method: 'post',
})

duplicate.definition = {
    methods: ["post"],
    url: '/inventory/requisition-templates/{requisitionTemplate}/duplicate',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::duplicate
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:60
 * @route '/inventory/requisition-templates/{requisitionTemplate}/duplicate'
 */
duplicate.url = (args: { requisitionTemplate: number | { id: number } } | [requisitionTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisitionTemplate: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisitionTemplate: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisitionTemplate: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisitionTemplate: typeof args.requisitionTemplate === 'object'
                ? args.requisitionTemplate.id
                : args.requisitionTemplate,
                }

    return duplicate.definition.url
            .replace('{requisitionTemplate}', parsedArgs.requisitionTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::duplicate
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:60
 * @route '/inventory/requisition-templates/{requisitionTemplate}/duplicate'
 */
duplicate.post = (args: { requisitionTemplate: number | { id: number } } | [requisitionTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: duplicate.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::duplicate
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:60
 * @route '/inventory/requisition-templates/{requisitionTemplate}/duplicate'
 */
    const duplicateForm = (args: { requisitionTemplate: number | { id: number } } | [requisitionTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: duplicate.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::duplicate
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:60
 * @route '/inventory/requisition-templates/{requisitionTemplate}/duplicate'
 */
        duplicateForm.post = (args: { requisitionTemplate: number | { id: number } } | [requisitionTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: duplicate.url(args, options),
            method: 'post',
        })
    
    duplicate.form = duplicateForm
/**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::destroy
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:78
 * @route '/inventory/requisition-templates/{requisitionTemplate}'
 */
export const destroy = (args: { requisitionTemplate: number | { id: number } } | [requisitionTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/inventory/requisition-templates/{requisitionTemplate}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::destroy
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:78
 * @route '/inventory/requisition-templates/{requisitionTemplate}'
 */
destroy.url = (args: { requisitionTemplate: number | { id: number } } | [requisitionTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisitionTemplate: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisitionTemplate: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisitionTemplate: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisitionTemplate: typeof args.requisitionTemplate === 'object'
                ? args.requisitionTemplate.id
                : args.requisitionTemplate,
                }

    return destroy.definition.url
            .replace('{requisitionTemplate}', parsedArgs.requisitionTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::destroy
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:78
 * @route '/inventory/requisition-templates/{requisitionTemplate}'
 */
destroy.delete = (args: { requisitionTemplate: number | { id: number } } | [requisitionTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::destroy
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:78
 * @route '/inventory/requisition-templates/{requisitionTemplate}'
 */
    const destroyForm = (args: { requisitionTemplate: number | { id: number } } | [requisitionTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\RequisitionTemplateController::destroy
 * @see app/Http/Controllers/Inventory/RequisitionTemplateController.php:78
 * @route '/inventory/requisition-templates/{requisitionTemplate}'
 */
        destroyForm.delete = (args: { requisitionTemplate: number | { id: number } } | [requisitionTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const RequisitionTemplateController = { store, update, duplicate, destroy }

export default RequisitionTemplateController