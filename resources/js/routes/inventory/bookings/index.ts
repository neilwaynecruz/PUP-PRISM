import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\BookingController::update
 * @see app/Http/Controllers/Inventory/BookingController.php:177
 * @route '/inventory/bookings/{booking}'
 */
export const update = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/inventory/bookings/{booking}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Inventory\BookingController::update
 * @see app/Http/Controllers/Inventory/BookingController.php:177
 * @route '/inventory/bookings/{booking}'
 */
update.url = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { booking: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { booking: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    booking: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        booking: typeof args.booking === 'object'
                ? args.booking.id
                : args.booking,
                }

    return update.definition.url
            .replace('{booking}', parsedArgs.booking.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\BookingController::update
 * @see app/Http/Controllers/Inventory/BookingController.php:177
 * @route '/inventory/bookings/{booking}'
 */
update.put = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Inventory\BookingController::index
 * @see app/Http/Controllers/Inventory/BookingController.php:35
 * @route '/inventory/bookings'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/inventory/bookings',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\BookingController::index
 * @see app/Http/Controllers/Inventory/BookingController.php:35
 * @route '/inventory/bookings'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\BookingController::index
 * @see app/Http/Controllers/Inventory/BookingController.php:35
 * @route '/inventory/bookings'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\BookingController::index
 * @see app/Http/Controllers/Inventory/BookingController.php:35
 * @route '/inventory/bookings'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\BookingController::show
 * @see app/Http/Controllers/Inventory/BookingController.php:114
 * @route '/inventory/bookings/{booking}'
 */
export const show = (args: { booking: string | number } | [booking: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/inventory/bookings/{booking}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\BookingController::show
 * @see app/Http/Controllers/Inventory/BookingController.php:114
 * @route '/inventory/bookings/{booking}'
 */
show.url = (args: { booking: string | number } | [booking: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { booking: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    booking: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        booking: args.booking,
                }

    return show.definition.url
            .replace('{booking}', parsedArgs.booking.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\BookingController::show
 * @see app/Http/Controllers/Inventory/BookingController.php:114
 * @route '/inventory/bookings/{booking}'
 */
show.get = (args: { booking: string | number } | [booking: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\BookingController::show
 * @see app/Http/Controllers/Inventory/BookingController.php:114
 * @route '/inventory/bookings/{booking}'
 */
show.head = (args: { booking: string | number } | [booking: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\BookingController::store
 * @see app/Http/Controllers/Inventory/BookingController.php:154
 * @route '/inventory/bookings'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/inventory/bookings',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\BookingController::store
 * @see app/Http/Controllers/Inventory/BookingController.php:154
 * @route '/inventory/bookings'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\BookingController::store
 * @see app/Http/Controllers/Inventory/BookingController.php:154
 * @route '/inventory/bookings'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\BookingController::destroy
 * @see app/Http/Controllers/Inventory/BookingController.php:315
 * @route '/inventory/bookings/{booking}'
 */
export const destroy = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/inventory/bookings/{booking}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Inventory\BookingController::destroy
 * @see app/Http/Controllers/Inventory/BookingController.php:315
 * @route '/inventory/bookings/{booking}'
 */
destroy.url = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { booking: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { booking: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    booking: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        booking: typeof args.booking === 'object'
                ? args.booking.id
                : args.booking,
                }

    return destroy.definition.url
            .replace('{booking}', parsedArgs.booking.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\BookingController::destroy
 * @see app/Http/Controllers/Inventory/BookingController.php:315
 * @route '/inventory/bookings/{booking}'
 */
destroy.delete = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Inventory\BookingController::trash
 * @see app/Http/Controllers/Inventory/BookingController.php:331
 * @route '/inventory/bookings/trash'
 */
export const trash = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: trash.url(options),
    method: 'get',
})

trash.definition = {
    methods: ["get","head"],
    url: '/inventory/bookings/trash',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\BookingController::trash
 * @see app/Http/Controllers/Inventory/BookingController.php:331
 * @route '/inventory/bookings/trash'
 */
trash.url = (options?: RouteQueryOptions) => {
    return trash.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\BookingController::trash
 * @see app/Http/Controllers/Inventory/BookingController.php:331
 * @route '/inventory/bookings/trash'
 */
trash.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: trash.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\BookingController::trash
 * @see app/Http/Controllers/Inventory/BookingController.php:331
 * @route '/inventory/bookings/trash'
 */
trash.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: trash.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Inventory\BookingController::restore
 * @see app/Http/Controllers/Inventory/BookingController.php:383
 * @route '/inventory/bookings/{booking}/restore'
 */
export const restore = (args: { booking: string | number } | [booking: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: restore.url(args, options),
    method: 'put',
})

restore.definition = {
    methods: ["put"],
    url: '/inventory/bookings/{booking}/restore',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Inventory\BookingController::restore
 * @see app/Http/Controllers/Inventory/BookingController.php:383
 * @route '/inventory/bookings/{booking}/restore'
 */
restore.url = (args: { booking: string | number } | [booking: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { booking: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    booking: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        booking: args.booking,
                }

    return restore.definition.url
            .replace('{booking}', parsedArgs.booking.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\BookingController::restore
 * @see app/Http/Controllers/Inventory/BookingController.php:383
 * @route '/inventory/bookings/{booking}/restore'
 */
restore.put = (args: { booking: string | number } | [booking: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: restore.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Inventory\BookingController::forceDelete
 * @see app/Http/Controllers/Inventory/BookingController.php:399
 * @route '/inventory/bookings/{booking}/force'
 */
export const forceDelete = (args: { booking: string | number } | [booking: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: forceDelete.url(args, options),
    method: 'delete',
})

forceDelete.definition = {
    methods: ["delete"],
    url: '/inventory/bookings/{booking}/force',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Inventory\BookingController::forceDelete
 * @see app/Http/Controllers/Inventory/BookingController.php:399
 * @route '/inventory/bookings/{booking}/force'
 */
forceDelete.url = (args: { booking: string | number } | [booking: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { booking: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    booking: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        booking: args.booking,
                }

    return forceDelete.definition.url
            .replace('{booking}', parsedArgs.booking.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\BookingController::forceDelete
 * @see app/Http/Controllers/Inventory/BookingController.php:399
 * @route '/inventory/bookings/{booking}/force'
 */
forceDelete.delete = (args: { booking: string | number } | [booking: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: forceDelete.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Inventory\BookingController::bulkRestore
 * @see app/Http/Controllers/Inventory/BookingController.php:414
 * @route '/inventory/bookings/bulk-restore'
 */
export const bulkRestore = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkRestore.url(options),
    method: 'post',
})

bulkRestore.definition = {
    methods: ["post"],
    url: '/inventory/bookings/bulk-restore',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\BookingController::bulkRestore
 * @see app/Http/Controllers/Inventory/BookingController.php:414
 * @route '/inventory/bookings/bulk-restore'
 */
bulkRestore.url = (options?: RouteQueryOptions) => {
    return bulkRestore.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\BookingController::bulkRestore
 * @see app/Http/Controllers/Inventory/BookingController.php:414
 * @route '/inventory/bookings/bulk-restore'
 */
bulkRestore.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkRestore.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\BookingController::bulkForceDelete
 * @see app/Http/Controllers/Inventory/BookingController.php:442
 * @route '/inventory/bookings/bulk-force-delete'
 */
export const bulkForceDelete = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkForceDelete.url(options),
    method: 'post',
})

bulkForceDelete.definition = {
    methods: ["post"],
    url: '/inventory/bookings/bulk-force-delete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\BookingController::bulkForceDelete
 * @see app/Http/Controllers/Inventory/BookingController.php:442
 * @route '/inventory/bookings/bulk-force-delete'
 */
bulkForceDelete.url = (options?: RouteQueryOptions) => {
    return bulkForceDelete.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\BookingController::bulkForceDelete
 * @see app/Http/Controllers/Inventory/BookingController.php:442
 * @route '/inventory/bookings/bulk-force-delete'
 */
bulkForceDelete.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkForceDelete.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\BookingController::bulkApprove
 * @see app/Http/Controllers/Inventory/BookingController.php:214
 * @route '/inventory/bookings/bulk-approve'
 */
export const bulkApprove = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkApprove.url(options),
    method: 'post',
})

bulkApprove.definition = {
    methods: ["post"],
    url: '/inventory/bookings/bulk-approve',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\BookingController::bulkApprove
 * @see app/Http/Controllers/Inventory/BookingController.php:214
 * @route '/inventory/bookings/bulk-approve'
 */
bulkApprove.url = (options?: RouteQueryOptions) => {
    return bulkApprove.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\BookingController::bulkApprove
 * @see app/Http/Controllers/Inventory/BookingController.php:214
 * @route '/inventory/bookings/bulk-approve'
 */
bulkApprove.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkApprove.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Inventory\BookingController::bulkReject
 * @see app/Http/Controllers/Inventory/BookingController.php:268
 * @route '/inventory/bookings/bulk-reject'
 */
export const bulkReject = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkReject.url(options),
    method: 'post',
})

bulkReject.definition = {
    methods: ["post"],
    url: '/inventory/bookings/bulk-reject',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\BookingController::bulkReject
 * @see app/Http/Controllers/Inventory/BookingController.php:268
 * @route '/inventory/bookings/bulk-reject'
 */
bulkReject.url = (options?: RouteQueryOptions) => {
    return bulkReject.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\BookingController::bulkReject
 * @see app/Http/Controllers/Inventory/BookingController.php:268
 * @route '/inventory/bookings/bulk-reject'
 */
bulkReject.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkReject.url(options),
    method: 'post',
})
const bookings = {
    update: Object.assign(update, update),
index: Object.assign(index, index),
show: Object.assign(show, show),
store: Object.assign(store, store),
destroy: Object.assign(destroy, destroy),
trash: Object.assign(trash, trash),
restore: Object.assign(restore, restore),
forceDelete: Object.assign(forceDelete, forceDelete),
bulkRestore: Object.assign(bulkRestore, bulkRestore),
bulkForceDelete: Object.assign(bulkForceDelete, bulkForceDelete),
bulkApprove: Object.assign(bulkApprove, bulkApprove),
bulkReject: Object.assign(bulkReject, bulkReject),
}

export default bookings