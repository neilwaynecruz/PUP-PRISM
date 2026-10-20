import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\AttachmentController::store
 * @see app/Http/Controllers/Inventory/AttachmentController.php:16
 * @route '/inventory/attachments/{attachableType}/{attachableId}'
 */
export const store = (args: { attachableType: string | number, attachableId: string | number } | [attachableType: string | number, attachableId: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/inventory/attachments/{attachableType}/{attachableId}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\AttachmentController::store
 * @see app/Http/Controllers/Inventory/AttachmentController.php:16
 * @route '/inventory/attachments/{attachableType}/{attachableId}'
 */
store.url = (args: { attachableType: string | number, attachableId: string | number } | [attachableType: string | number, attachableId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    attachableType: args[0],
                    attachableId: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        attachableType: args.attachableType,
                                attachableId: args.attachableId,
                }

    return store.definition.url
            .replace('{attachableType}', parsedArgs.attachableType.toString())
            .replace('{attachableId}', parsedArgs.attachableId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\AttachmentController::store
 * @see app/Http/Controllers/Inventory/AttachmentController.php:16
 * @route '/inventory/attachments/{attachableType}/{attachableId}'
 */
store.post = (args: { attachableType: string | number, attachableId: string | number } | [attachableType: string | number, attachableId: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\AttachmentController::store
 * @see app/Http/Controllers/Inventory/AttachmentController.php:16
 * @route '/inventory/attachments/{attachableType}/{attachableId}'
 */
    const storeForm = (args: { attachableType: string | number, attachableId: string | number } | [attachableType: string | number, attachableId: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\AttachmentController::store
 * @see app/Http/Controllers/Inventory/AttachmentController.php:16
 * @route '/inventory/attachments/{attachableType}/{attachableId}'
 */
        storeForm.post = (args: { attachableType: string | number, attachableId: string | number } | [attachableType: string | number, attachableId: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Inventory\AttachmentController::download
 * @see app/Http/Controllers/Inventory/AttachmentController.php:35
 * @route '/inventory/attachments/{attachment}/download'
 */
export const download = (args: { attachment: string | number | { id: string | number } } | [attachment: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/inventory/attachments/{attachment}/download',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\AttachmentController::download
 * @see app/Http/Controllers/Inventory/AttachmentController.php:35
 * @route '/inventory/attachments/{attachment}/download'
 */
download.url = (args: { attachment: string | number | { id: string | number } } | [attachment: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { attachment: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { attachment: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    attachment: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        attachment: typeof args.attachment === 'object'
                ? args.attachment.id
                : args.attachment,
                }

    return download.definition.url
            .replace('{attachment}', parsedArgs.attachment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\AttachmentController::download
 * @see app/Http/Controllers/Inventory/AttachmentController.php:35
 * @route '/inventory/attachments/{attachment}/download'
 */
download.get = (args: { attachment: string | number | { id: string | number } } | [attachment: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\AttachmentController::download
 * @see app/Http/Controllers/Inventory/AttachmentController.php:35
 * @route '/inventory/attachments/{attachment}/download'
 */
download.head = (args: { attachment: string | number | { id: string | number } } | [attachment: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\AttachmentController::download
 * @see app/Http/Controllers/Inventory/AttachmentController.php:35
 * @route '/inventory/attachments/{attachment}/download'
 */
    const downloadForm = (args: { attachment: string | number | { id: string | number } } | [attachment: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: download.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\AttachmentController::download
 * @see app/Http/Controllers/Inventory/AttachmentController.php:35
 * @route '/inventory/attachments/{attachment}/download'
 */
        downloadForm.get = (args: { attachment: string | number | { id: string | number } } | [attachment: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\AttachmentController::download
 * @see app/Http/Controllers/Inventory/AttachmentController.php:35
 * @route '/inventory/attachments/{attachment}/download'
 */
        downloadForm.head = (args: { attachment: string | number | { id: string | number } } | [attachment: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    download.form = downloadForm
/**
* @see \App\Http\Controllers\Inventory\AttachmentController::destroy
 * @see app/Http/Controllers/Inventory/AttachmentController.php:47
 * @route '/inventory/attachments/{attachment}'
 */
export const destroy = (args: { attachment: string | number | { id: string | number } } | [attachment: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/inventory/attachments/{attachment}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Inventory\AttachmentController::destroy
 * @see app/Http/Controllers/Inventory/AttachmentController.php:47
 * @route '/inventory/attachments/{attachment}'
 */
destroy.url = (args: { attachment: string | number | { id: string | number } } | [attachment: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { attachment: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { attachment: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    attachment: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        attachment: typeof args.attachment === 'object'
                ? args.attachment.id
                : args.attachment,
                }

    return destroy.definition.url
            .replace('{attachment}', parsedArgs.attachment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\AttachmentController::destroy
 * @see app/Http/Controllers/Inventory/AttachmentController.php:47
 * @route '/inventory/attachments/{attachment}'
 */
destroy.delete = (args: { attachment: string | number | { id: string | number } } | [attachment: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Inventory\AttachmentController::destroy
 * @see app/Http/Controllers/Inventory/AttachmentController.php:47
 * @route '/inventory/attachments/{attachment}'
 */
    const destroyForm = (args: { attachment: string | number | { id: string | number } } | [attachment: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\AttachmentController::destroy
 * @see app/Http/Controllers/Inventory/AttachmentController.php:47
 * @route '/inventory/attachments/{attachment}'
 */
        destroyForm.delete = (args: { attachment: string | number | { id: string | number } } | [attachment: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const attachments = {
    store: Object.assign(store, store),
download: Object.assign(download, download),
destroy: Object.assign(destroy, destroy),
}

export default attachments