<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import SupplierController from '@/actions/App/Http/Controllers/Inventory/SupplierController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    create as suppliersCreate,
    index as suppliersIndex,
} from '@/routes/inventory/suppliers';

defineOptions({
    name: 'InventorySupplierCreatePage',
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: suppliersIndex() },
            { title: 'Suppliers', href: suppliersIndex() },
            { title: 'New', href: suppliersCreate() },
        ],
    },
});
</script>

<template>
    <Head title="New supplier" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading
            variant="small"
            title="New supplier"
            description="Create a procurement-ready supplier profile."
        />

        <Form
            v-bind="SupplierController.store.form()"
            class="grid gap-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="name">Supplier name</Label>
                    <Input id="name" name="name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="contact_person">Contact person</Label>
                    <Input id="contact_person" name="contact_person" />
                    <InputError :message="errors.contact_person" />
                </div>
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input id="email" name="email" type="email" />
                    <InputError :message="errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="phone">Phone</Label>
                    <Input id="phone" name="phone" />
                    <InputError :message="errors.phone" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label for="address">Address</Label>
                    <Input id="address" name="address" />
                    <InputError :message="errors.address" />
                </div>
                <div class="grid gap-2">
                    <Label for="website">Website</Label>
                    <Input id="website" name="website" placeholder="https://..." />
                    <InputError :message="errors.website" />
                </div>
                <div class="grid gap-2">
                    <Label for="payment_terms">Payment terms</Label>
                    <Input id="payment_terms" name="payment_terms" placeholder="Net 30" />
                    <InputError :message="errors.payment_terms" />
                </div>
                <div class="grid gap-2">
                    <Label for="lead_time_days">Lead time (days)</Label>
                    <Input id="lead_time_days" name="lead_time_days" type="number" min="0" />
                    <InputError :message="errors.lead_time_days" />
                </div>
                <div class="grid gap-2">
                    <Label for="is_active">Status</Label>
                    <select
                        id="is_active"
                        name="is_active"
                        class="h-9 rounded-lg border border-input bg-background px-3 text-sm"
                    >
                        <option value="1" selected>Active</option>
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
                />
                <InputError :message="errors.notes" />
            </div>

            <div class="flex items-center justify-end gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="suppliersIndex()">Cancel</Link>
                </Button>
                <Button :disabled="processing">Create supplier</Button>
            </div>
        </Form>
    </div>
</template>
