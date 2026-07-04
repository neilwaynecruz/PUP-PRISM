import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\SecurityController::setupData
 * @see app/Http/Controllers/Settings/SecurityController.php:49
 * @route '/settings/security/two-factor/setup-data'
 */
export const setupData = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: setupData.url(options),
    method: 'get',
})

setupData.definition = {
    methods: ["get","head"],
    url: '/settings/security/two-factor/setup-data',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\SecurityController::setupData
 * @see app/Http/Controllers/Settings/SecurityController.php:49
 * @route '/settings/security/two-factor/setup-data'
 */
setupData.url = (options?: RouteQueryOptions) => {
    return setupData.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\SecurityController::setupData
 * @see app/Http/Controllers/Settings/SecurityController.php:49
 * @route '/settings/security/two-factor/setup-data'
 */
setupData.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: setupData.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Settings\SecurityController::setupData
 * @see app/Http/Controllers/Settings/SecurityController.php:49
 * @route '/settings/security/two-factor/setup-data'
 */
setupData.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: setupData.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Settings\SecurityController::setupData
 * @see app/Http/Controllers/Settings/SecurityController.php:49
 * @route '/settings/security/two-factor/setup-data'
 */
    const setupDataForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: setupData.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Settings\SecurityController::setupData
 * @see app/Http/Controllers/Settings/SecurityController.php:49
 * @route '/settings/security/two-factor/setup-data'
 */
        setupDataForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: setupData.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Settings\SecurityController::setupData
 * @see app/Http/Controllers/Settings/SecurityController.php:49
 * @route '/settings/security/two-factor/setup-data'
 */
        setupDataForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: setupData.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    setupData.form = setupDataForm
const twoFactor = {
    setupData: Object.assign(setupData, setupData),
}

export default twoFactor