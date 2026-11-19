import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\AccountSwitchController::__invoke
 * @see app/Http/Controllers/Auth/AccountSwitchController.php:17
 * @route '/account/switch'
 */
const AccountSwitchController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AccountSwitchController.url(options),
    method: 'post',
})

AccountSwitchController.definition = {
    methods: ["post"],
    url: '/account/switch',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\AccountSwitchController::__invoke
 * @see app/Http/Controllers/Auth/AccountSwitchController.php:17
 * @route '/account/switch'
 */
AccountSwitchController.url = (options?: RouteQueryOptions) => {
    return AccountSwitchController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\AccountSwitchController::__invoke
 * @see app/Http/Controllers/Auth/AccountSwitchController.php:17
 * @route '/account/switch'
 */
AccountSwitchController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AccountSwitchController.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Auth\AccountSwitchController::__invoke
 * @see app/Http/Controllers/Auth/AccountSwitchController.php:17
 * @route '/account/switch'
 */
    const AccountSwitchControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: AccountSwitchController.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Auth\AccountSwitchController::__invoke
 * @see app/Http/Controllers/Auth/AccountSwitchController.php:17
 * @route '/account/switch'
 */
        AccountSwitchControllerForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: AccountSwitchController.url(options),
            method: 'post',
        })

    AccountSwitchController.form = AccountSwitchControllerForm
export default AccountSwitchController