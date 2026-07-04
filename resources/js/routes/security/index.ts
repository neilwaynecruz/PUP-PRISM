import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\SecurityController::edit
 * @see app/Http/Controllers/Settings/SecurityController.php:27
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
 * @see app/Http/Controllers/Settings/SecurityController.php:27
 * @route '/settings/security'
 */
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\SecurityController::edit
 * @see app/Http/Controllers/Settings/SecurityController.php:27
 * @route '/settings/security'
 */
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Settings\SecurityController::edit
 * @see app/Http/Controllers/Settings/SecurityController.php:27
 * @route '/settings/security'
 */
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})
const security = {
    edit: Object.assign(edit, edit),
}

export default security