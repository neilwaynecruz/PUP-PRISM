import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\NotificationPreferenceController::edit
 * @see app/Http/Controllers/Settings/NotificationPreferenceController.php:19
 * @route '/settings/notifications'
 */
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/notifications',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\NotificationPreferenceController::edit
 * @see app/Http/Controllers/Settings/NotificationPreferenceController.php:19
 * @route '/settings/notifications'
 */
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\NotificationPreferenceController::edit
 * @see app/Http/Controllers/Settings/NotificationPreferenceController.php:19
 * @route '/settings/notifications'
 */
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Settings\NotificationPreferenceController::edit
 * @see app/Http/Controllers/Settings/NotificationPreferenceController.php:19
 * @route '/settings/notifications'
 */
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\NotificationPreferenceController::update
 * @see app/Http/Controllers/Settings/NotificationPreferenceController.php:53
 * @route '/settings/notifications'
 */
export const update = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/settings/notifications',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Settings\NotificationPreferenceController::update
 * @see app/Http/Controllers/Settings/NotificationPreferenceController.php:53
 * @route '/settings/notifications'
 */
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\NotificationPreferenceController::update
 * @see app/Http/Controllers/Settings/NotificationPreferenceController.php:53
 * @route '/settings/notifications'
 */
update.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})
const NotificationPreferenceController = { edit, update }

export default NotificationPreferenceController