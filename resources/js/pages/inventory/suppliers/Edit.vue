<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import SupplierController from '@/actions/App/Http/Controllers/Inventory/SupplierController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    edit as suppliersEdit,
    index as suppliersIndex,
    show as suppliersShow,
} from '@/routes/inventory/suppliers';

type Supplier = {
    id: number;
    name: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    website: string | null;
    payment_terms: string | null;
    lead_time_days: number | null;
    is_active: boolean;
    notes: string | null;
};

const props = defineProps<{
    supplier: Supplier;
    can: {
        delete: boolean;
    };
}>();

defineOptions({
    name: 'InventorySupplierEditPage',
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: suppliersIndex() },
            { title: 'Suppliers', href: suppliersIndex() },
            { title: 'Edit', href: suppliersEdit(0) },
        ],
    },
});

function destroySupplier(): void {
    router.delete(SupplierController.destroy(props.supplier.id).url);
}
</script>

<template>
    <Head :title="`Edit ${supplier.name}`" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading
            variant="small"
            title="Edit supplier"
            :description="supplier.name"
        />

        <Form
            v-bind="SupplierController.update.form(supplier.id)"
            class="grid gap-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="name">Supplier name</Label>
                    <Input id="name" name="name" :default-value="supplier.name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="contact_person">Contact person</Label>
                    <Input
                        id="contact_person"
                        name="contact_person"
                        :default-value="supplier.contact_person ?? ''"
                    />
                    <InputError :message="errors.contact_person" />
                </div>
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input id="email" name="email" type="email" :default-value="supplier.email ?? ''" />
                    <InputError :message="errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="phone">Phone</Label>
                    <Input id="phone" name="phone" :default-value="supplier.phone ?? ''" />
                    <InputError :message="errors.phone" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label for="address">Address</Label>
                    <Input id="address" name="address" :default-value="supplier.address ?? ''" />
                    <InputError :message="errors.address" />
                </div>
                <div class="grid gap-2">
                    <Label for="website">Website</Label>
                    <Input id="website" name="website" :default-value="supplier.website ?? ''" />
                    <InputError :message="errors.website" />
                </div>
                <div class="grid gap-2">
                    <Label for="payment_terms">Payment terms</Label>
                    <Input
                        id="payment_terms"
                        name="payment_terms"
                        :default-value="supplier.payment_terms ?? ''"
                    />
                    <InputError :message="errors.payment_terms" />
                </div>
                <div class="grid gap-2">
                    <Label for="lead_time_days">Lead time (days)</Label>
                    <Input
                        id="lead_time_days"
                        name="lead_time_days"
                        type="number"
                        min="0"
                        :default-value="
                            supplier.lead_time_days !== null
                                ? String(supplier.lead_time_days)
                                : ''
                        "
                    />
                    <InputError :message="errors.lead_time_days" />
                </div>
                <div class="grid gap-2">
                    <Label for="is_active">Status</Label>
                    <select
                        id="is_active"
                        name="is_active"
                        class="h-9 rounded-lg border border-input bg-background px-3 text-sm"
                        :defaultValue="supplier.is_active ? '1' : '0'"
                    >
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                    <InputError :message="errors.is_active" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="notes">Notes</Label>
                <textarea
                    id="notes"
                    name="notes"
                    rows="4"
                    class="min-h-24 rounded-lg border border-input bg-background px-3 py-2 text-sm"
                    :defaultValue="supplier.notes ?? ''"
                />
                <InputError :message="errors.notes" />
            </div>

            <div class="flex items-center justify-end gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="suppliersShow(supplier.id)">View</Link>
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="suppliersIndex()">Back</Link>
                </Button>
                <Button
                    v-if="can.delete"
                    type="button"
                    variant="destructive"
                    @click="destroySupplier"
                >
                    Delete
                </Button>
                <Button :disabled="processing">Save changes</Button>
            </div>
        </Form>
    </div>
</template>
