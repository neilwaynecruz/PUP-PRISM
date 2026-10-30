<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Building2, MapPin, Pencil, RotateCcw, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AdminCodeBadge from '@/components/admin/AdminCodeBadge.vue';
import AdminCountBadge from '@/components/admin/AdminCountBadge.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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

const props = defineProps<{
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

const searchQuery = ref('');
const departmentFilter = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive'>('all');

const hasActiveFilters = computed(
    () =>
        searchQuery.value.trim() !== '' ||
        departmentFilter.value !== '' ||
        statusFilter.value !== 'all',
);

const filteredLocations = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();

    return props.locations.filter((location) => {
        if (statusFilter.value === 'active' && !location.is_active) {
            return false;
        }

        if (statusFilter.value === 'inactive' && location.is_active) {
            return false;
        }

        if (
            departmentFilter.value === 'shared' &&
            location.department !== null
        ) {
            return false;
        }

        if (
            departmentFilter.value !== '' &&
            departmentFilter.value !== 'shared' &&
            String(location.department?.id ?? '') !== departmentFilter.value
        ) {
            return false;
        }

        if (!q) {
            return true;
        }

        return (
            location.code.toLowerCase().includes(q) ||
            location.name.toLowerCase().includes(q) ||
            (location.building ?? '').toLowerCase().includes(q) ||
            (location.room ?? '').toLowerCase().includes(q) ||
            (location.department?.name ?? '').toLowerCase().includes(q)
        );
    });
});

function clearFilters(): void {
    searchQuery.value = '';
    departmentFilter.value = '';
    statusFilter.value = 'all';
}

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

const editingLocation = ref<Location | null>(null);
const isEditOpen = ref(false);
const editForm = useForm({
    department_id: '',
    code: '',
    name: '',
    building: '',
    room: '',
    is_active: true,
});

function openEdit(location: Location): void {
    editingLocation.value = location;
    editForm.clearErrors();
    editForm.department_id = location.department?.id
        ? String(location.department.id)
        : '';
    editForm.code = location.code;
    editForm.name = location.name;
    editForm.building = location.building ?? '';
    editForm.room = location.room ?? '';
    editForm.is_active = location.is_active;
    isEditOpen.value = true;
}

function submitEdit(): void {
    if (!editingLocation.value) {
        return;
    }

    editForm
        .transform((data) => ({
            ...data,
            department_id: data.department_id
                ? Number(data.department_id)
                : null,
            building: data.building.trim() || null,
            room: data.room.trim() || null,
        }))
        .put(update(editingLocation.value.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                isEditOpen.value = false;
                editingLocation.value = null;
            },
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
        data-testid="admin-locations-index-page"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Physical locations"
                description="Manage buildings, rooms, offices, and storage areas without changing department or accountability records."
            />
            <div
                class="flex items-center gap-2 self-start rounded-lg border border-border/70 bg-card px-3 py-1.5 text-xs text-muted-foreground shadow-2xs"
            >
                <MapPin class="h-3.5 w-3.5 text-primary" />
                <span>Showing</span>
                <strong class="font-semibold text-foreground">{{
                    filteredLocations.length
                }}</strong>
                <span>of {{ locations.length }}</span>
            </div>
        </div>

        <form
            class="grid gap-3 rounded-xl border border-border/70 bg-card p-4 shadow-xs md:grid-cols-3 xl:grid-cols-7 xl:items-end"
            @submit.prevent="submit"
        >
            <label class="grid gap-1 text-sm font-medium"
                >Location code<input
                    v-model="form.code"
                    name="location_code"
                    autocomplete="off"
                    required
                    placeholder="Example: MAIN-STOCK"
                    class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9" /><InputError
                    :message="form.errors.code"
            /></label>
            <label class="grid gap-1 text-sm font-medium"
                >Name<input
                    v-model="form.name"
                    name="location_name"
                    autocomplete="off"
                    required
                    placeholder="Main Supply Room"
                    class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9" /><InputError
                    :message="form.errors.name"
            /></label>
            <label class="grid gap-1 text-sm font-medium"
                >Building<input
                    v-model="form.building"
                    name="location_building"
                    autocomplete="off"
                    placeholder="Building name"
                    class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9" /><InputError
                    :message="form.errors.building"
            /></label>
            <label class="grid gap-1 text-sm font-medium"
                >Room<input
                    v-model="form.room"
                    name="location_room"
                    autocomplete="off"
                    placeholder="Room or area"
                    class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9" /><InputError
                    :message="form.errors.room"
            /></label>
            <label class="grid gap-1 text-sm font-medium xl:col-span-2"
                >Department<select
                    v-model="form.department_id"
                    name="location_department"
                    autocomplete="off"
                    class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9"
                >
                    <option value="">Shared or not assigned</option>
                    <option
                        v-for="department in departments"
                        :key="department.id"
                        :value="String(department.id)"
                    >
                        {{ department.name }}
                    </option></select
                ><InputError :message="form.errors.department_id"
            /></label>
            <Button
                type="submit"
                class="min-h-11 sm:min-h-9"
                :disabled="form.processing"
                >{{ form.processing ? 'Creating…' : 'Create Location' }}</Button
            >
        </form>

        <!-- Search & Filter Bar -->
        <div
            class="flex flex-col gap-3 rounded-xl border border-border/70 bg-card p-3 shadow-xs sm:flex-row sm:items-center"
        >
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="searchQuery"
                    name="locations_search"
                    aria-label="Search locations"
                    autocomplete="off"
                    placeholder="Search by code, name, building, room, or department…"
                    class="min-h-11 pl-9 sm:min-h-9"
                />
            </div>

            <select
                v-model="departmentFilter"
                name="locations_department_filter"
                aria-label="Filter by department"
                class="min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm sm:min-h-9 sm:w-56"
            >
                <option value="">All departments</option>
                <option value="shared">Shared locations only</option>
                <option
                    v-for="department in departments"
                    :key="department.id"
                    :value="String(department.id)"
                >
                    {{ department.name }}
                </option>
            </select>

            <select
                v-model="statusFilter"
                name="locations_status_filter"
                aria-label="Filter locations by status"
                class="min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm sm:min-h-9 sm:w-44"
            >
                <option value="all">All statuses</option>
                <option value="active">Active only</option>
                <option value="inactive">Inactive only</option>
            </select>

            <Button
                v-if="hasActiveFilters"
                type="button"
                variant="ghost"
                size="sm"
                class="min-h-11 gap-1.5 sm:min-h-9"
                @click="clearFilters"
            >
                <RotateCcw class="h-3.5 w-3.5" />
                Clear
            </Button>
        </div>

        <div
            v-if="filteredLocations.length"
            class="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
        >
            <article
                v-for="location in filteredLocations"
                :key="location.id"
                class="flex flex-col justify-between rounded-xl border border-border/70 bg-card p-4 shadow-xs transition-colors hover:border-primary/35"
            >
                <div>
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <span
                                class="shrink-0 rounded-lg bg-primary/10 p-2 text-primary"
                            >
                                <Building2 aria-hidden="true" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 space-y-1">
                                <AdminCodeBadge :value="location.code" />
                                <h2
                                    class="truncate font-semibold text-foreground"
                                >
                                    {{ location.name }}
                                </h2>
                            </div>
                        </div>
                        <AdminStatusBadge :active="location.is_active" />
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Building / room
                            </dt>
                            <dd class="mt-0.5 font-medium text-foreground">
                                {{
                                    [location.building, location.room]
                                        .filter(Boolean)
                                        .join(' · ') || 'Not specified'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Assets
                            </dt>
                            <dd class="mt-0.5">
                                <AdminCountBadge
                                    :value="location.assets_count"
                                />
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-xs text-muted-foreground">
                                Department
                            </dt>
                            <dd class="mt-0.5 text-foreground">
                                {{
                                    location.department?.name ??
                                    'Shared location'
                                }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <div
                    class="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-border/50 pt-3"
                >
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        class="min-h-11 gap-1.5 sm:min-h-9"
                        @click="openEdit(location)"
                    >
                        <Pencil class="h-3.5 w-3.5" />
                        Edit
                    </Button>
                    <Button
                        v-if="!location.is_active"
                        size="sm"
                        variant="outline"
                        class="min-h-11 sm:min-h-9"
                        @click="toggle(location)"
                        >Reactivate</Button
                    ><Button
                        v-else
                        size="sm"
                        variant="destructive"
                        class="min-h-11 sm:min-h-9"
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
            <h2 class="mt-3 font-semibold">
                {{
                    hasActiveFilters
                        ? 'No locations match your filters'
                        : 'No locations have been added'
                }}
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
                {{
                    hasActiveFilters
                        ? 'Try adjusting your search query, department, or status filter.'
                        : 'Create the first physical place used to store or operate university assets.'
                }}
            </p>
            <Button
                v-if="hasActiveFilters"
                type="button"
                variant="outline"
                size="sm"
                class="mt-4 min-h-11 sm:min-h-9"
                @click="clearFilters"
            >
                Clear filters
            </Button>
        </div>

        <Dialog v-model:open="isEditOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit location</DialogTitle>
                    <DialogDescription>
                        Update physical location details while keeping existing
                        asset and transfer records linked.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitEdit">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="grid gap-1 text-sm font-medium">
                            Edit location code
                            <input
                                v-model="editForm.code"
                                name="edit_location_code"
                                autocomplete="off"
                                required
                                class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9"
                            />
                            <InputError :message="editForm.errors.code" />
                        </label>
                        <label class="grid gap-1 text-sm font-medium">
                            Edit location name
                            <input
                                v-model="editForm.name"
                                name="edit_location_name"
                                autocomplete="off"
                                required
                                class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9"
                            />
                            <InputError :message="editForm.errors.name" />
                        </label>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="grid gap-1 text-sm font-medium">
                            Edit building
                            <input
                                v-model="editForm.building"
                                name="edit_location_building"
                                autocomplete="off"
                                class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9"
                            />
                            <InputError :message="editForm.errors.building" />
                        </label>
                        <label class="grid gap-1 text-sm font-medium">
                            Edit room
                            <input
                                v-model="editForm.room"
                                name="edit_location_room"
                                autocomplete="off"
                                class="min-h-11 rounded-md border border-input bg-background px-3 text-sm sm:min-h-9"
                            />
                            <InputError :message="editForm.errors.room" />
                        </label>
                    </div>
                    <label class="grid gap-1 text-sm font-medium">
                        Edit department
                        <select
                            v-model="editForm.department_id"
                            name="edit_location_department"
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
                        </select>
                        <InputError :message="editForm.errors.department_id" />
                    </label>
                    <label
                        class="flex min-h-11 items-center gap-2.5 rounded-lg border border-border/60 bg-muted/20 px-3 text-sm font-medium sm:min-h-9"
                    >
                        <input
                            v-model="editForm.is_active"
                            type="checkbox"
                            class="size-4 accent-primary"
                        />
                        Active location
                    </label>
                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="min-h-11 sm:min-h-9"
                            @click="isEditOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            class="min-h-11 sm:min-h-9"
                            :disabled="editForm.processing"
                        >
                            {{
                                editForm.processing ? 'Saving…' : 'Save Changes'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
