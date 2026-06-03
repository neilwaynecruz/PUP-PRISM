import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Inventory\BookingController::update
 * @see app/Http/Controllers/Inventory/BookingController.php:147
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
 * @see app/Http/Controllers/Inventory/BookingController.php:147
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
 * @see app/Http/Controllers/Inventory/BookingController.php:147
 * @route '/inventory/bookings/{booking}'
 */
update.put = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Inventory\BookingController::update
 * @see app/Http/Controllers/Inventory/BookingController.php:147
 * @route '/inventory/bookings/{booking}'
 */
    const updateForm = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\BookingController::update
 * @see app/Http/Controllers/Inventory/BookingController.php:147
 * @route '/inventory/bookings/{booking}'
 */
        updateForm.put = (args: { booking: number | { id: number } } | [booking: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Inventory\BookingController::index
 * @see app/Http/Controllers/Inventory/BookingController.php:26
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
 * @see app/Http/Controllers/Inventory/BookingController.php:26
 * @route '/inventory/bookings'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\BookingController::index
 * @see app/Http/Controllers/Inventory/BookingController.php:26
 * @route '/inventory/bookings'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\BookingController::index
 * @see app/Http/Controllers/Inventory/BookingController.php:26
 * @route '/inventory/bookings'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\BookingController::index
 * @see app/Http/Controllers/Inventory/BookingController.php:26
 * @route '/inventory/bookings'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\BookingController::index
 * @see app/Http/Controllers/Inventory/BookingController.php:26
 * @route '/inventory/bookings'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\BookingController::index
 * @see app/Http/Controllers/Inventory/BookingController.php:26
 * @route '/inventory/bookings'
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
* @see \App\Http\Controllers\Inventory\BookingController::store
 * @see app/Http/Controllers/Inventory/BookingController.php:103
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
 * @see app/Http/Controllers/Inventory/BookingController.php:103
 * @route '/inventory/bookings'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\BookingController::store
 * @see app/Http/Controllers/Inventory/BookingController.php:103
 * @route '/inventory/bookings'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\BookingController::store
 * @see app/Http/Controllers/Inventory/BookingController.php:103
 * @route '/inventory/bookings'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\BookingController::store
 * @see app/Http/Controllers/Inventory/BookingController.php:103
 * @route '/inventory/bookings'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const bookings = {
    update: Object.assign(update, update),
index: Object.assign(index, index),
store: Object.assign(store, store),
}

export default bookings