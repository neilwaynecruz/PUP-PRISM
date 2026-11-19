import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\AccountSwitchController::__invoke
 * @see app/Http/Controllers/Auth/AccountSwitchController.php:17
 * @route '/account/switch'
 */
export const switchMethod = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: switchMethod.url(options),
    method: 'post',
})

switchMethod.definition = {
    methods: ["post"],
    url: '/account/switch',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\AccountSwitchController::__invoke
 * @see app/Http/Controllers/Auth/AccountSwitchController.php:17
 * @route '/account/switch'
 */
switchMethod.url = (options?: RouteQueryOptions) => {
    return switchMethod.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\AccountSwitchController::__invoke
 * @see app/Http/Controllers/Auth/AccountSwitchController.php:17
 * @route '/account/switch'
 */
switchMethod.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: switchMethod.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Auth\AccountSwitchController::__invoke
 * @see app/Http/Controllers/Auth/AccountSwitchController.php:17
 * @route '/account/switch'
 */
    const switchMethodForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: switchMethod.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Auth\AccountSwitchController::__invoke
 * @see app/Http/Controllers/Auth/AccountSwitchController.php:17
 * @route '/account/switch'
 */
        switchMethodForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: switchMethod.url(options),
            method: 'post',
        })

    switchMethod.form = switchMethodForm
const account = {
    switch: Object.assign(switchMethod, switchMethod),
}

export default account