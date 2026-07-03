<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import NotificationPreferenceController from '@/actions/App/Http/Controllers/Settings/NotificationPreferenceController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { edit as notificationPreferencesEdit } from '@/routes/notification-preferences';

type EventTypeOption = {
    value: string;
    label: string;
    description: string;
};

type PreferenceRow = {
    event_type: string;
    mail_enabled: boolean;
    database_enabled: boolean;
    broadcast_enabled: boolean;
    digest_frequency: 'instant' | 'daily';
};

const props = defineProps<{
    preferences: PreferenceRow[];
    eventTypes: EventTypeOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Notification preferences',
                href: notificationPreferencesEdit(),
            },
        ],
    },
});

const form = useForm({
    preferences: props.preferences.map((preference) => ({ ...preference })),
});

const eventTypeMap = computed(() =>
    Object.fromEntries(
        props.eventTypes.map((eventType) => [eventType.value, eventType]),
    ),
);

function labelFor(eventType: string): string {
    return eventTypeMap.value[eventType]?.label ?? eventType;
}

function descriptionFor(eventType: string): string {
    return eventTypeMap.value[eventType]?.description ?? '';
}

function preferenceIndex(eventType: string): number {
    return form.preferences.findIndex(
        (preference) => preference.event_type === eventType,
    );
}

function submit(): void {
    form.put(NotificationPreferenceController.update().url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Notification preferences" />

    <h1 class="sr-only">Notification preferences</h1>

    <div
        class="flex flex-col space-y-6"
        data-testid="notification-preferences-page"
    >
        <Heading
            variant="small"
            title="Notification preferences"
            description="Choose how you receive workflow alerts by channel and email frequency."
        />

        <form class="space-y-6" @submit.prevent="submit">
            <div class="overflow-x-auto rounded-lg border border-border">
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Event</th>
                            <th class="px-4 py-3 font-medium">Mail</th>
                            <th class="px-4 py-3 font-medium">In-app</th>
                            <th class="px-4 py-3 font-medium">Realtime</th>
                            <th class="px-4 py-3 font-medium">Email frequency</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="preference in form.preferences"
                            :key="preference.event_type"
                            class="border-t border-border align-top"
                        >
                            <td class="px-4 py-4">
                                <p class="font-medium text-foreground">
                                    {{ labelFor(preference.event_type) }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ descriptionFor(preference.event_type) }}
                                </p>
                            </td>
                            <td class="px-4 py-4">
                                <Checkbox
                                    :id="`${preference.event_type}-mail`"
                                    :model-value="preference.mail_enabled"
                                    @update:model-value="
                                        (value) =>
                                            (preference.mail_enabled =
                                                value === true)
                                    "
                                />
                            </td>
                            <td class="px-4 py-4">
                                <Checkbox
                                    :id="`${preference.event_type}-database`"
                                    :model-value="preference.database_enabled"
                                    @update:model-value="
                                        (value) =>
                                            (preference.database_enabled =
                                                value === true)
                                    "
                                />
                            </td>
                            <td class="px-4 py-4">
                                <Checkbox
                                    :id="`${preference.event_type}-broadcast`"
                                    :model-value="preference.broadcast_enabled"
                                    @update:model-value="
                                        (value) =>
                                            (preference.broadcast_enabled =
                                                value === true)
                                    "
                                />
                            </td>
                            <td class="px-4 py-4">
                                <Label
                                    :for="`${preference.event_type}-digest`"
                                    class="sr-only"
                                >
                                    Email frequency for
                                    {{ labelFor(preference.event_type) }}
                                </Label>
                                <select
                                    :id="`${preference.event_type}-digest`"
                                    v-model="preference.digest_frequency"
                                    class="h-9 w-full min-w-32 rounded-lg border border-input bg-background px-3 text-sm"
                                >
                                    <option value="instant">Instant email</option>
                                    <option value="daily">Daily digest</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <InputError :message="form.errors.preferences" />

            <div class="flex items-center gap-3">
                <Button
                    type="submit"
                    size="sm"
                    class="rounded-lg"
                    :disabled="form.processing"
                >
                    Save preferences
                </Button>
            </div>
        </form>
    </div>
</template>
