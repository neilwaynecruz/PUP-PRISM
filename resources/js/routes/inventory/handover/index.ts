import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import verify8ef1b2 from './verify'
/**
* @see \App\Http\Controllers\Inventory\HandoverController::index
 * @see app/Http/Controllers/Inventory/HandoverController.php:51
 * @route '/inventory/handover'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/inventory/handover',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\HandoverController::index
 * @see app/Http/Controllers/Inventory/HandoverController.php:51
 * @route '/inventory/handover'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\HandoverController::index
 * @see app/Http/Controllers/Inventory/HandoverController.php:51
 * @route '/inventory/handover'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\HandoverController::index
 * @see app/Http/Controllers/Inventory/HandoverController.php:51
 * @route '/inventory/handover'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\HandoverController::index
 * @see app/Http/Controllers/Inventory/HandoverController.php:51
 * @route '/inventory/handover'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\HandoverController::index
 * @see app/Http/Controllers/Inventory/HandoverController.php:51
 * @route '/inventory/handover'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\HandoverController::index
 * @see app/Http/Controllers/Inventory/HandoverController.php:51
 * @route '/inventory/handover'
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
* @see \App\Http\Controllers\Inventory\HandoverController::store
 * @see app/Http/Controllers/Inventory/HandoverController.php:98
 * @route '/inventory/handover'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/inventory/handover',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\HandoverController::store
 * @see app/Http/Controllers/Inventory/HandoverController.php:98
 * @route '/inventory/handover'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\HandoverController::store
 * @see app/Http/Controllers/Inventory/HandoverController.php:98
 * @route '/inventory/handover'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\HandoverController::store
 * @see app/Http/Controllers/Inventory/HandoverController.php:98
 * @route '/inventory/handover'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\HandoverController::store
 * @see app/Http/Controllers/Inventory/HandoverController.php:98
 * @route '/inventory/handover'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Inventory\HandoverController::cancel
 * @see app/Http/Controllers/Inventory/HandoverController.php:168
 * @route '/inventory/handover/{handoverLog}/cancel'
 */
export const cancel = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

cancel.definition = {
    methods: ["post"],
    url: '/inventory/handover/{handoverLog}/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\HandoverController::cancel
 * @see app/Http/Controllers/Inventory/HandoverController.php:168
 * @route '/inventory/handover/{handoverLog}/cancel'
 */
cancel.url = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { handoverLog: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { handoverLog: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    handoverLog: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        handoverLog: typeof args.handoverLog === 'object'
                ? args.handoverLog.id
                : args.handoverLog,
                }

    return cancel.definition.url
            .replace('{handoverLog}', parsedArgs.handoverLog.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\HandoverController::cancel
 * @see app/Http/Controllers/Inventory/HandoverController.php:168
 * @route '/inventory/handover/{handoverLog}/cancel'
 */
cancel.post = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\HandoverController::cancel
 * @see app/Http/Controllers/Inventory/HandoverController.php:168
 * @route '/inventory/handover/{handoverLog}/cancel'
 */
    const cancelForm = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: cancel.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\HandoverController::cancel
 * @see app/Http/Controllers/Inventory/HandoverController.php:168
 * @route '/inventory/handover/{handoverLog}/cancel'
 */
        cancelForm.post = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: cancel.url(args, options),
            method: 'post',
        })

    cancel.form = cancelForm
/**
* @see \App\Http\Controllers\Inventory\HandoverController::resend
 * @see app/Http/Controllers/Inventory/HandoverController.php:211
 * @route '/inventory/handover/{handoverLog}/resend'
 */
export const resend = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resend.url(args, options),
    method: 'post',
})

resend.definition = {
    methods: ["post"],
    url: '/inventory/handover/{handoverLog}/resend',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\HandoverController::resend
 * @see app/Http/Controllers/Inventory/HandoverController.php:211
 * @route '/inventory/handover/{handoverLog}/resend'
 */
resend.url = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { handoverLog: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { handoverLog: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    handoverLog: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        handoverLog: typeof args.handoverLog === 'object'
                ? args.handoverLog.id
                : args.handoverLog,
                }

    return resend.definition.url
            .replace('{handoverLog}', parsedArgs.handoverLog.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\HandoverController::resend
 * @see app/Http/Controllers/Inventory/HandoverController.php:211
 * @route '/inventory/handover/{handoverLog}/resend'
 */
resend.post = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resend.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\HandoverController::resend
 * @see app/Http/Controllers/Inventory/HandoverController.php:211
 * @route '/inventory/handover/{handoverLog}/resend'
 */
    const resendForm = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: resend.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\HandoverController::resend
 * @see app/Http/Controllers/Inventory/HandoverController.php:211
 * @route '/inventory/handover/{handoverLog}/resend'
 */
        resendForm.post = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: resend.url(args, options),
            method: 'post',
        })

    resend.form = resendForm
/**
* @see \App\Http\Controllers\Inventory\HandoverVerificationController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverVerificationController.php:20
 * @route '/inventory/handover/verify/{handoverLog}'
 */
export const verify = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verify.url(args, options),
    method: 'get',
})

verify.definition = {
    methods: ["get","head"],
    url: '/inventory/handover/verify/{handoverLog}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\HandoverVerificationController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverVerificationController.php:20
 * @route '/inventory/handover/verify/{handoverLog}'
 */
verify.url = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { handoverLog: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { handoverLog: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    handoverLog: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        handoverLog: typeof args.handoverLog === 'object'
                ? args.handoverLog.id
                : args.handoverLog,
                }

    return verify.definition.url
            .replace('{handoverLog}', parsedArgs.handoverLog.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\HandoverVerificationController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverVerificationController.php:20
 * @route '/inventory/handover/verify/{handoverLog}'
 */
verify.get = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verify.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\HandoverVerificationController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverVerificationController.php:20
 * @route '/inventory/handover/verify/{handoverLog}'
 */
verify.head = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: verify.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\HandoverVerificationController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverVerificationController.php:20
 * @route '/inventory/handover/verify/{handoverLog}'
 */
    const verifyForm = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: verify.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\HandoverVerificationController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverVerificationController.php:20
 * @route '/inventory/handover/verify/{handoverLog}'
 */
        verifyForm.get = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: verify.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\HandoverVerificationController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverVerificationController.php:20
 * @route '/inventory/handover/verify/{handoverLog}'
 */
        verifyForm.head = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: verify.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    verify.form = verifyForm
/**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::mine
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:15
 * @route '/inventory/handover/mine'
 */
export const mine = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: mine.url(options),
    method: 'get',
})

mine.definition = {
    methods: ["get","head"],
    url: '/inventory/handover/mine',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::mine
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:15
 * @route '/inventory/handover/mine'
 */
mine.url = (options?: RouteQueryOptions) => {
    return mine.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::mine
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:15
 * @route '/inventory/handover/mine'
 */
mine.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: mine.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::mine
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:15
 * @route '/inventory/handover/mine'
 */
mine.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: mine.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::mine
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:15
 * @route '/inventory/handover/mine'
 */
    const mineForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: mine.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::mine
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:15
 * @route '/inventory/handover/mine'
 */
        mineForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: mine.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::mine
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:15
 * @route '/inventory/handover/mine'
 */
        mineForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: mine.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    mine.form = mineForm
/**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::review
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:37
 * @route '/inventory/handover/{handoverLog}/review'
 */
export const review = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: review.url(args, options),
    method: 'post',
})

review.definition = {
    methods: ["post"],
    url: '/inventory/handover/{handoverLog}/review',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::review
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:37
 * @route '/inventory/handover/{handoverLog}/review'
 */
review.url = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { handoverLog: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { handoverLog: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    handoverLog: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        handoverLog: typeof args.handoverLog === 'object'
                ? args.handoverLog.id
                : args.handoverLog,
                }

    return review.definition.url
            .replace('{handoverLog}', parsedArgs.handoverLog.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::review
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:37
 * @route '/inventory/handover/{handoverLog}/review'
 */
review.post = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: review.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::review
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:37
 * @route '/inventory/handover/{handoverLog}/review'
 */
    const reviewForm = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: review.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Inventory\PersonalHandoverController::review
 * @see app/Http/Controllers/Inventory/PersonalHandoverController.php:37
 * @route '/inventory/handover/{handoverLog}/review'
 */
        reviewForm.post = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: review.url(args, options),
            method: 'post',
        })

    review.form = reviewForm
/**
* @see \App\Http\Controllers\Inventory\HandoverReceiptController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverReceiptController.php:16
 * @route '/inventory/handover/receipt/{handoverLog}'
 */
export const receipt = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: receipt.url(args, options),
    method: 'get',
})

receipt.definition = {
    methods: ["get","head"],
    url: '/inventory/handover/receipt/{handoverLog}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Inventory\HandoverReceiptController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverReceiptController.php:16
 * @route '/inventory/handover/receipt/{handoverLog}'
 */
receipt.url = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { handoverLog: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { handoverLog: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    handoverLog: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        handoverLog: typeof args.handoverLog === 'object'
                ? args.handoverLog.id
                : args.handoverLog,
                }

    return receipt.definition.url
            .replace('{handoverLog}', parsedArgs.handoverLog.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Inventory\HandoverReceiptController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverReceiptController.php:16
 * @route '/inventory/handover/receipt/{handoverLog}'
 */
receipt.get = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: receipt.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Inventory\HandoverReceiptController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverReceiptController.php:16
 * @route '/inventory/handover/receipt/{handoverLog}'
 */
receipt.head = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: receipt.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Inventory\HandoverReceiptController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverReceiptController.php:16
 * @route '/inventory/handover/receipt/{handoverLog}'
 */
    const receiptForm = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: receipt.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Inventory\HandoverReceiptController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverReceiptController.php:16
 * @route '/inventory/handover/receipt/{handoverLog}'
 */
        receiptForm.get = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: receipt.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Inventory\HandoverReceiptController::__invoke
 * @see app/Http/Controllers/Inventory/HandoverReceiptController.php:16
 * @route '/inventory/handover/receipt/{handoverLog}'
 */
        receiptForm.head = (args: { handoverLog: number | { id: number } } | [handoverLog: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: receipt.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    receipt.form = receiptForm
const handover = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
cancel: Object.assign(cancel, cancel),
resend: Object.assign(resend, resend),
verify: Object.assign(verify, verify8ef1b2),
mine: Object.assign(mine, mine),
review: Object.assign(review, review),
receipt: Object.assign(receipt, receipt),
}

export default handover