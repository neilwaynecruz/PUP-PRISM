<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Building2, MapPin } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { destroy, index, store, update } from '@/routes/admin/locations';

type Department = { id: number; name: string };
type Location = {
    id: number;
    code: string;
    name: string;
    building: string | null;
    room: string | null;
    is_active: boolean;
    assets_count: number;
    department: Department | null;
};

defineProps<{
    locations: Location[];
    departments: Department[];
}>();
defineOptions({
    name: 'AdminLocationsIndexPage',
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: index() },
            { title: 'Locations', href: index() },
        ],
    },
});

const form = useForm({
    department_id: '',
    code: '',
    name: '',
    building: '',
    room: '',
    is_active: true,
});
function submit(): void {
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
function toggle(location: Location): void {
    if (!location.is_active && !window.confirm('Reactivate this location?')) {
        return;
    }

    router.put(
        update(location.id).url,
        {
            department_id: location.department?.id ?? null,
            code: location.code,
            name: location.name,
            building: location.building,
            room: location.room,
            is_active: !location.is_active,
        },
        { preserveScroll: true },
    );
}
function deactivate(location: Location): void {
    if (
        window.confirm(
            `Deactivate ${location.name}? Existing asset and transfer history will be preserved.`,
        )
    ) {
        router.delete(destroy(location.id).url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Locations" />
    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8"
    >
        <Heading
            variant="small"
            title="Physical locations"
            description="Manage buildings, rooms, offices, and storage areas without changing department or accountability records."
        />
        <form
            class="grid gap-3 rounded-xl border border-border/70 bg-card p-4 md:grid-cols-3 xl:grid-cols-6 xl:items-end"
            @submit.prevent="submit"
        >
            <label class="grid gap-1 text-sm font-medium"
                >Location code<input
                    v-model="form.code"
                    required
                    placeholder="Example: MAIN-STOCK"
                    class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9" /><InputError
                    :message="form.errors.code"
            /></label>
            <label class="grid gap-1 text-sm font-medium"
                >Name<input
                    v-model="form.name"
                    required
                    placeholder="Main Supply Room"
                    class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9" /><InputError
                    :message="form.errors.name"
            /></label>
            <label class="grid gap-1 text-sm font-medium"
                >Building<input
                    v-model="form.building"
                    placeholder="Building name"
                    class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9"
            /></label>
            <label class="grid gap-1 text-sm font-medium"
                >Room<input
                    v-model="form.room"
                    placeholder="Room or area"
                    class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9"
            /></label>
            <label class="grid gap-1 text-sm font-medium"
                >Department<select
                    v-model="form.department_id"
                    class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9"
                >
                    <option value="">Shared or not assigned</option>
                    <option
                        v-for="department in departments"
                        :key="department.id"
                        :value="String(department.id)"
                    >
                        {{ department.name }}
                    </option>
                </select></label
            >
            <Button type="submit" :disabled="form.processing">{{
                form.processing ? 'Creating…' : 'Create Location'
            }}</Button>
        </form>

        <div
            v-if="locations.length"
            class="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
        >
            <article
                v-for="location in locations"
                :key="location.id"
                class="rounded-xl border border-border/70 bg-card p-4 shadow-xs"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="rounded-lg bg-primary/10 p-2 text-primary"
                            ><Building2 aria-hidden="true" class="h-5 w-5"
                        /></span>
                        <div>
                            <p class="font-mono text-xs text-muted-foreground">
                                {{ location.code }}
                            </p>
                            <h2 class="font-semibold">{{ location.name }}</h2>
                        </div>
                    </div>
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="
                            location.is_active
                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                : 'bg-muted text-muted-foreground'
                        "
                        >{{ location.is_active ? 'Active' : 'Inactive' }}</span
                    >
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Building / room
                        </dt>
                        <dd>
                            {{
                                [location.building, location.room]
                                    .filter(Boolean)
                                    .join(' · ') || 'Not specified'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Assets</dt>
                        <dd>{{ location.assets_count }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-muted-foreground">
                            Department
                        </dt>
                        <dd>
                            {{ location.department?.name ?? 'Shared location' }}
                        </dd>
                    </div>
                </dl>
                <div class="mt-4 flex justify-end gap-2">
                    <Button
                        v-if="!location.is_active"
                        size="sm"
                        variant="outline"
                        @click="toggle(location)"
                        >Reactivate</Button
                    ><Button
                        v-else
                        size="sm"
                        variant="destructive"
                        @click="deactivate(location)"
                        >Deactivate</Button
                    >
                </div>
            </article>
        </div>
        <div
            v-else
            class="rounded-xl border border-dashed border-border p-10 text-center"
        >
            <MapPin
                aria-hidden="true"
                class="mx-auto h-9 w-9 text-muted-foreground/60"
            />
            <h2 class="mt-3 font-semibold">No locations have been added</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Create the first physical place used to store or operate
                university assets.
            </p>
        </div>
    </div>
</template>
