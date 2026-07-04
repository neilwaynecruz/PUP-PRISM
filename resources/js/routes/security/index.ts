import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
import twoFactor from './two-factor'
/**
* @see \App\Http\Controllers\Settings\SecurityController::edit
 * @see app/Http/Controllers/Settings/SecurityController.php:29
 * @route '/settings/security'
 */
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/security',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\SecurityController::edit
 * @see app/Http/Controllers/Settings/SecurityController.php:29
 * @route '/settings/security'
 */
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\SecurityController::edit
 * @see app/Http/Controllers/Settings/SecurityController.php:29
 * @route '/settings/security'
 */
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Settings\SecurityController::edit
 * @see app/Http/Controllers/Settings/SecurityController.php:29
 * @route '/settings/security'
 */
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Settings\SecurityController::edit
 * @see app/Http/Controllers/Settings/SecurityController.php:29
 * @route '/settings/security'
 */
    const editForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: edit.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Settings\SecurityController::edit
 * @see app/Http/Controllers/Settings/SecurityController.php:29
 * @route '/settings/security'
 */
        editForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Settings\SecurityController::edit
 * @see app/Http/Controllers/Settings/SecurityController.php:29
 * @route '/settings/security'
 */
        editForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    edit.form = editForm
const security = {
    edit: Object.assign(edit, edit),
twoFactor: Object.assign(twoFactor, twoFactor),
}

export default security