<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import HandoverController from '@/actions/App/Http/Controllers/Inventory/HandoverController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import QrScannerDialog from '@/components/inventory/QrScannerDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as handoverIndex } from '@/routes/inventory/handover';

type UserOption = {
    id: number;
    name: string;
    email: string;
    position: {
        id: number;
        title: string;
        code: string;
        department: string | null;
    } | null;
};
type RecentRow = {
    id: number;
    tag_code: string | null;
    asset_name: string | null;
    to: UserOption | null;
    from_position: { title: string; department: string | null } | null;
    to_position: { title: string; department: string | null } | null;
    initiated_at: string | null;
    verified_at: string | null;
};

const assetTagCode = ref('');

const props = defineProps<{
    filters: { recipient_search: string };
    users: UserOption[];
    recent: RecentRow[];
}>();

const recipientSearch = ref(props.filters.recipient_search ?? '');

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: handoverIndex() },
            { title: 'Handover', href: handoverIndex() },
        ],
    },
});

let recipientSearchTimer: number | undefined;
watch(recipientSearch, () => {
    window.clearTimeout(recipientSearchTimer);
    recipientSearchTimer = window.setTimeout(() => {
        router.get(
            handoverIndex().url,
            {
                recipient_search: recipientSearch.value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, 250);
});
</script>

<template>
    <Head title="Asset handover" />

    <div class="flex flex-col gap-6 p-4 sm:p-6" data-testid="handover-initiate-page">
        <Heading
            variant="small"
            title="Digital asset handover"
            description="Scan the asset tag and send a verification link to the recipient. The accountable position stays visible throughout the handover."
        />

        <div class="grid items-start gap-6 xl:grid-cols-[400px_1fr]">
            <div class="self-start rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2 text-sm font-semibold tracking-tight">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-primary/60" />
                    Handover details
                </div>
                <Form v-bind="HandoverController.store.form()" v-slot="{ errors, processing }" class="grid gap-5">
                    <div class="grid gap-2">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <Label for="asset_tag_code" class="text-xs font-medium uppercase tracking-wider text-muted-foreground/70">Asset tag code</Label>
                            <QrScannerDialog
                                button-label="Scan asset tag"
                                title="Scan handover asset tag"
                                description="Use the camera to read the asset label, or type the tag code manually."
                                @scanned="assetTagCode = $event"
                            />
                        </div>
                        <Input
                            id="asset_tag_code"
                            v-model="assetTagCode"
                            name="asset_tag_code"
                            data-testid="handover-asset-tag-input"
                            placeholder="Scan / type tag code"
                            required
                            class="rounded-lg"
                        />
                        <InputError :message="errors.asset_tag_code" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="recipient_search" class="text-xs font-medium uppercase tracking-wider text-muted-foreground/70">Find recipient</Label>
                        <Input id="recipient_search" v-model="recipientSearch" data-testid="handover-recipient-search-input" placeholder="Search by name or email" class="rounded-lg" />
                        <div class="text-xs text-muted-foreground">
                            The selector loads up to 25 matching recipients at a time.
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="to_user_id" class="text-xs font-medium uppercase tracking-wider text-muted-foreground/70">Recipient</Label>
                        <select
                            id="to_user_id"
                            name="to_user_id"
                            data-testid="handover-recipient-select"
                            class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
                            required
                        >
                            <option value="" disabled selected>Select recipient</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">
                                {{ u.name }} ({{ u.email }}){{ u.position ? ` — ${u.position.title}` : '' }}
                            </option>
                        </select>
                        <div class="text-xs text-muted-foreground">
                            Recipients must already be assigned to an institutional position before the handover can proceed.
                        </div>
                        <InputError :message="errors.to_user_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes" class="text-xs font-medium uppercase tracking-wider text-muted-foreground/70">Notes (optional)</Label>
                        <Input id="notes" name="notes" placeholder="Optional notes" class="rounded-lg" />
                        <InputError :message="errors.notes" />
                    </div>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="processing" data-test="send-verification-button" data-testid="send-verification-button" class="rounded-lg shadow-sm">
                            <span class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-primary-foreground/60" />Send verification
                        </Button>
                    </div>
                </Form>
            </div>

            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2 text-sm font-semibold tracking-tight">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-sky-500/60" />
                        Recent handovers
                    </div>
                    <span class="text-xs text-muted-foreground">{{ recent.length }} total</span>
                </div>

                <div v-if="recent.length" class="grid gap-3">
                    <div
                        v-for="r in recent"
                        :key="r.id"
                        class="group relative overflow-hidden rounded-xl border border-border/40 p-4 transition-all hover:border-border/60 hover:shadow-sm"
                    >
                        <div class="absolute left-0 top-0 h-full w-1 transition-colors"
                            :class="r.verified_at ? 'bg-emerald-500' : 'bg-amber-500'"
                        />

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between pl-3">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <div class="font-medium">{{ r.asset_name ?? 'Unknown asset' }}</div>
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider',
                                            r.verified_at
                                                ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                : 'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400',
                                        ]"
                                    >
                                        <span
                                            :class="[
                                                'h-1 w-1 rounded-full',
                                                r.verified_at ? 'bg-emerald-500' : 'bg-amber-500',
                                            ]"
                                        />
                                        {{ r.verified_at ? 'Verified' : 'Pending' }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                    <span class="inline-flex items-center gap-1">
                                        <span class="h-1 w-1 rounded-full bg-primary/40" />
                                        {{ r.tag_code ?? '—' }}
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <span class="h-1 w-1 rounded-full bg-primary/40" />
                                        To: {{ r.to?.name ?? r.to?.email ?? '—' }}
                                    </span>
                                </div>
                                <div v-if="r.to_position || r.from_position" class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground/80">
                                    <span v-if="r.to_position">
                                        {{ r.to_position.title }}{{ r.to_position.department ? `, ${r.to_position.department}` : '' }}
                                    </span>
                                    <span v-if="r.from_position" class="text-muted-foreground/60">
                                        From: {{ r.from_position.title }}{{ r.from_position.department ? `, ${r.from_position.department}` : '' }}
                                    </span>
                                </div>
                            </div>

                            <div class="shrink-0 text-right">
                                <div class="text-[11px] tabular-nums text-muted-foreground/70">
                                    {{ r.initiated_at ?? '—' }}
                                </div>
                                <div v-if="r.verified_at" class="text-[11px] tabular-nums text-emerald-600 dark:text-emerald-400">
                                    Verified {{ r.verified_at }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="rounded-xl border border-dashed border-border/60 p-8 text-center text-sm text-muted-foreground">
                    No recent handovers found.
                </div>
            </div>
        </div>
    </div>
</template>
