<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    CheckCircle2,
    Clock,
    CreditCard,
    FileText,
    Globe,
    Mail,
    MapPin,
    Phone,
    RotateCcw,
    ShieldCheck,
    User,
} from 'lucide-vue-next';
import { ref } from 'vue';
import SupplierController from '@/actions/App/Http/Controllers/Inventory/SupplierController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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

const name = ref('');
const contactPerson = ref('');
const email = ref('');
const phone = ref('');
const website = ref('');
const websitePlaceholder = 'https://example.com';
const paymentTerms = ref('Net 30');
const leadTimeDays = ref('7');
const isActive = ref('1');
const address = ref('');
const notes = ref('');

const paymentTermPresets = [
    'COD',
    'Net 15',
    'Net 30',
    'Net 45',
    'Net 60',
    'Gov. Terms',
];

const leadTimePresets = [
    { label: '3 days', value: '3' },
    { label: '7 days', value: '7' },
    { label: '14 days', value: '14' },
    { label: '30 days', value: '30' },
];

function normalizeWebsite(): void {
    const trimmed = website.value.trim();

    if (trimmed !== '' && !/^https?:\/\//i.test(trimmed)) {
        website.value = `https://${trimmed}`;
    }
}

function resetFormFields(): void {
    name.value = '';
    contactPerson.value = '';
    email.value = '';
    phone.value = '';
    website.value = '';
    paymentTerms.value = 'Net 30';
    leadTimeDays.value = '7';
    isActive.value = '1';
    address.value = '';
    notes.value = '';
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
        <Head title="New Supplier" />

        <!-- Header Banner -->
        <div
            class="relative overflow-hidden rounded-2xl border border-border/70 bg-card p-5 shadow-xs sm:p-6"
        >
            <div
                class="pointer-events-none absolute -top-16 -right-16 size-52 rounded-full bg-primary/5 blur-2xl"
            />
            <div
                class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-start gap-3.5">
                    <div
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary shadow-2xs"
                    >
                        <Building2 class="size-5" />
                    </div>
                    <Heading
                        title="New supplier"
                        description="Register a vendor record for product sourcing, replenishment, and purchase order workflows."
                    />
                </div>
                <Button
                    as-child
                    variant="outline"
                    class="w-full shrink-0 sm:w-auto"
                >
                    <Link :href="suppliersIndex()">
                        <ArrowLeft class="mr-1.5 size-4" />
                        Back to suppliers
                    </Link>
                </Button>
            </div>
        </div>

        <Form
            v-bind="SupplierController.store.form()"
            v-slot="{ errors, processing }"
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]"
        >
            <!-- Left Column: Structured Form Sections -->
            <div class="flex flex-col gap-6">
                <!-- Section 1: Company & Primary Contact -->
                <section
                    class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs sm:p-6"
                >
                    <div
                        class="mb-5 flex items-center gap-2.5 border-b border-border/60 pb-3.5"
                    >
                        <div
                            class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <User class="size-4" />
                        </div>
                        <div>
                            <h2
                                class="text-base font-semibold tracking-tight text-foreground"
                            >
                                Vendor identity &amp; primary contact
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Official business name and primary
                                representative for purchase orders.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="name" class="font-medium">
                                Supplier name
                                <span class="text-destructive">*</span>
                            </Label>
                            <div class="relative">
                                <Building2
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id="name"
                                    v-model="name"
                                    name="name"
                                    required
                                    placeholder="e.g. National Office & Campus Supplies Inc."
                                    class="pl-9"
                                />
                            </div>
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="contact_person" class="font-medium">
                                Contact person
                            </Label>
                            <div class="relative">
                                <User
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id="contact_person"
                                    v-model="contactPerson"
                                    name="contact_person"
                                    placeholder="e.g. Maria Santos"
                                    class="pl-9"
                                />
                            </div>
                            <InputError :message="errors.contact_person" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="phone" class="font-medium">
                                Phone number
                            </Label>
                            <div class="relative">
                                <Phone
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id="phone"
                                    v-model="phone"
                                    name="phone"
                                    type="tel"
                                    placeholder="e.g. 0917-200-1000"
                                    class="pl-9"
                                />
                            </div>
                            <InputError :message="errors.phone" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email" class="font-medium">
                                Email address
                            </Label>
                            <div class="relative">
                                <Mail
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id="email"
                                    v-model="email"
                                    name="email"
                                    type="email"
                                    placeholder="procurement@vendor.com.ph"
                                    class="pl-9"
                                />
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                Purchase order notifications are sent here when
                                orders are dispatched.
                            </p>
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="website" class="font-medium">
                                Website
                            </Label>
                            <div class="relative">
                                <Globe
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id="website"
                                    v-model="website"
                                    name="website"
                                    type="url"
                                    :placeholder="websitePlaceholder"
                                    class="pl-9"
                                    @blur="normalizeWebsite"
                                />
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                Optional vendor portal or catalog URL.
                            </p>
                            <InputError :message="errors.website" />
                        </div>
                    </div>
                </section>

                <!-- Section 2: Procurement & Fulfillment Terms -->
                <section
                    class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs sm:p-6"
                >
                    <div
                        class="mb-5 flex items-center gap-2.5 border-b border-border/60 pb-3.5"
                    >
                        <div
                            class="flex size-8 items-center justify-center rounded-lg bg-amber-500/15 text-amber-700 dark:text-amber-300"
                        >
                            <CreditCard class="size-4" />
                        </div>
                        <div>
                            <h2
                                class="text-base font-semibold tracking-tight text-foreground"
                            >
                                Procurement &amp; fulfillment terms
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Standard payment schedule, delivery lead time,
                                and operational status.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="payment_terms" class="font-medium">
                                Payment terms
                            </Label>
                            <Input
                                id="payment_terms"
                                v-model="paymentTerms"
                                name="payment_terms"
                                placeholder="Net 30"
                            />
                            <div class="flex flex-wrap gap-1 pt-0.5">
                                <button
                                    v-for="preset in paymentTermPresets"
                                    :key="preset"
                                    type="button"
                                    class="rounded-md border px-2 py-0.5 text-[11px] font-medium transition"
                                    :class="
                                        paymentTerms === preset
                                            ? 'border-primary/40 bg-primary/10 text-primary'
                                            : 'border-border/70 bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground'
                                    "
                                    @click="paymentTerms = preset"
                                >
                                    {{ preset }}
                                </button>
                            </div>
                            <InputError :message="errors.payment_terms" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="lead_time_days" class="font-medium">
                                Lead time (days)
                            </Label>
                            <div class="relative">
                                <Clock
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id="lead_time_days"
                                    v-model="leadTimeDays"
                                    name="lead_time_days"
                                    type="number"
                                    min="0"
                                    max="365"
                                    class="pr-14 pl-9"
                                />
                                <span
                                    class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs font-medium text-muted-foreground"
                                >
                                    days
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-1 pt-0.5">
                                <button
                                    v-for="preset in leadTimePresets"
                                    :key="preset.value"
                                    type="button"
                                    class="rounded-md border px-2 py-0.5 text-[11px] font-medium transition"
                                    :class="
                                        leadTimeDays === preset.value
                                            ? 'border-primary/40 bg-primary/10 text-primary'
                                            : 'border-border/70 bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground'
                                    "
                                    @click="leadTimeDays = preset.value"
                                >
                                    {{ preset.label }}
                                </button>
                            </div>
                            <InputError :message="errors.lead_time_days" />
                        </div>

                        <div class="grid content-start gap-2">
                            <Label for="is_active" class="font-medium">
                                Status
                            </Label>
                            <select
                                id="is_active"
                                v-model="isActive"
                                name="is_active"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="1">
                                    Active (Available for POs)
                                </option>
                                <option value="0">
                                    Inactive (On hold / Archived)
                                </option>
                            </select>
                            <p class="text-[11px] text-muted-foreground">
                                Only active suppliers can be assigned to new
                                purchase orders.
                            </p>
                            <InputError :message="errors.is_active" />
                        </div>
                    </div>
                </section>

                <!-- Section 3: Address & Notes -->
                <section
                    class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs sm:p-6"
                >
                    <div
                        class="mb-5 flex items-center gap-2.5 border-b border-border/60 pb-3.5"
                    >
                        <div
                            class="flex size-8 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                        >
                            <MapPin class="size-4" />
                        </div>
                        <div>
                            <h2
                                class="text-base font-semibold tracking-tight text-foreground"
                            >
                                Location &amp; internal notes
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Warehouse/office address and accreditation or
                                delivery notes.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="address" class="font-medium">
                                Address
                            </Label>
                            <textarea
                                id="address"
                                v-model="address"
                                name="address"
                                rows="4"
                                placeholder="Building, street, barangay, city, and province"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            />
                            <InputError :message="errors.address" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="notes" class="font-medium">
                                Notes
                            </Label>
                            <textarea
                                id="notes"
                                v-model="notes"
                                name="notes"
                                rows="4"
                                placeholder="PhilGEPS registration, TIN, minimum order requirements, or delivery instructions"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            />
                            <InputError :message="errors.notes" />
                        </div>
                    </div>
                </section>

                <!-- Form Actions Bar -->
                <div
                    class="flex flex-col-reverse gap-3 rounded-2xl border border-border/70 bg-card p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <Button as-child variant="outline" type="button">
                            <Link :href="suppliersIndex()">Cancel</Link>
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            class="text-muted-foreground"
                            @click="resetFormFields"
                        >
                            <RotateCcw class="mr-1.5 size-4" />
                            Reset fields
                        </Button>
                    </div>

                    <Button
                        type="submit"
                        :disabled="processing"
                        class="w-full sm:w-auto"
                    >
                        <CheckCircle2 class="mr-1.5 size-4" />
                        {{
                            processing
                                ? 'Creating supplier…'
                                : 'Create supplier'
                        }}
                    </Button>
                </div>
            </div>

            <!-- Right Column: Live Vendor Profile Preview & Guidelines -->
            <aside class="flex flex-col gap-4 xl:sticky xl:top-6 xl:self-start">
                <div
                    class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Supplier preview
                        </span>
                        <Badge
                            :variant="
                                isActive === '1' ? 'default' : 'secondary'
                            "
                        >
                            {{ isActive === '1' ? 'Active' : 'Inactive' }}
                        </Badge>
                    </div>

                    <div class="mt-3 border-b border-border/60 pb-3.5">
                        <div
                            class="truncate text-base font-semibold text-foreground"
                        >
                            {{ name.trim() || 'Untitled Supplier' }}
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            {{
                                contactPerson.trim()
                                    ? `Contact: ${contactPerson.trim()}`
                                    : 'No contact person specified yet'
                            }}
                        </div>
                    </div>

                    <dl class="mt-3.5 grid gap-2.5 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <dt class="text-muted-foreground">Email</dt>
                            <dd class="truncate font-medium text-foreground">
                                {{ email.trim() || '—' }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <dt class="text-muted-foreground">Phone</dt>
                            <dd class="font-medium text-foreground">
                                {{ phone.trim() || '—' }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <dt class="text-muted-foreground">Payment terms</dt>
                            <dd class="font-medium text-foreground">
                                {{ paymentTerms.trim() || '—' }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <dt class="text-muted-foreground">Lead time</dt>
                            <dd class="font-medium text-foreground">
                                {{
                                    leadTimeDays !== ''
                                        ? `${leadTimeDays} day(s)`
                                        : '—'
                                }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div
                    class="rounded-2xl border border-primary/20 bg-primary/5 p-4 text-xs"
                >
                    <div
                        class="flex items-center gap-2 font-semibold text-primary"
                    >
                        <ShieldCheck class="size-4 shrink-0" />
                        <span>Procurement readiness tips</span>
                    </div>
                    <ul class="mt-2.5 space-y-2 text-muted-foreground">
                        <li class="flex items-start gap-2">
                            <FileText
                                class="mt-0.5 size-3.5 shrink-0 text-primary/80"
                            />
                            <span>
                                Setting an accurate
                                <strong class="text-foreground"
                                    >lead time</strong
                                >
                                helps calculate expected delivery dates and
                                reorder alerts automatically.
                            </span>
                        </li>
                        <li class="flex items-start gap-2">
                            <Mail
                                class="mt-0.5 size-3.5 shrink-0 text-primary/80"
                            />
                            <span>
                                Providing a valid
                                <strong class="text-foreground"
                                    >email address</strong
                                >
                                enables automated dispatch notifications when
                                purchase orders are approved.
                            </span>
                        </li>
                    </ul>
                </div>
            </aside>
        </Form>
    </div>
</template>
