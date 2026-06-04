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

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const d = new Date(iso);

    return d.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

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

    <div
        class="flex flex-col gap-6 p-4 sm:p-6"
        data-testid="handover-initiate-page"
    >
        <Heading
            variant="small"
            title="Digital asset handover"
            description="Transfer accountability by scanning an asset tag and sending a verification link to the recipient. Position records stay visible throughout."
        />

        <div class="grid items-start gap-6 xl:grid-cols-[560px_1fr]">
            <div
                class="self-start rounded-xl border border-border/50 bg-card shadow-sm"
            >
                <div class="border-b border-border/40 px-5 py-4">
                    <div class="text-sm font-semibold tracking-tight">
                        Handover details
                    </div>
                    <div class="text-xs text-muted-foreground/80">
                        Scan an asset tag and choose a recipient to initiate
                        transfer.
                    </div>
                </div>
                <Form
                    v-bind="HandoverController.store.form()"
                    v-slot="{ errors, processing }"
                    class="grid gap-4 px-5 py-4"
                >
                    <!-- Asset tag -->
                    <div class="grid gap-1.5">
                        <Label
                            for="asset_tag_code"
                            class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                            >Asset tag code
                            <span class="text-rose-500">*</span></Label
                        >
                        <div class="flex items-center gap-2">
                            <Input
                                id="asset_tag_code"
                                v-model="assetTagCode"
                                name="asset_tag_code"
                                data-testid="handover-asset-tag-input"
                                placeholder="Scan or type tag code"
                                required
                                class="h-9 flex-1 rounded-lg text-sm"
                            />
                            <QrScannerDialog
                                button-label="Scan"
                                title="Scan handover asset tag"
                                description="Use the camera to read the asset label, or type the tag code manually."
                                @scanned="assetTagCode = $event"
                            />
                        </div>
                        <InputError :message="errors.asset_tag_code" />
                    </div>

                    <!-- Recipient search -->
                    <div class="grid gap-1.5">
                        <Label
                            for="recipient_search"
                            class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                            >Search recipient</Label
                        >
                        <Input
                            id="recipient_search"
                            v-model="recipientSearch"
                            data-testid="handover-recipient-search-input"
                            placeholder="Type name or email to filter"
                            class="h-9 rounded-lg text-sm"
                        />
                        <div class="text-[11px] text-muted-foreground/70">
                            Loads up to 25 matching recipients.
                        </div>
                    </div>

                    <!-- Recipient select -->
                    <div class="grid gap-1.5">
                        <Label
                            for="to_user_id"
                            class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                            >Selected recipient
                            <span class="text-rose-500">*</span></Label
                        >
                        <select
                            id="to_user_id"
                            name="to_user_id"
                            data-testid="handover-recipient-select"
                            class="h-9 w-full rounded-lg border border-input bg-background px-3 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            required
                        >
                            <option value="" disabled selected>
                                Select a recipient
                            </option>
                            <option
                                v-for="u in users"
                                :key="u.id"
                                :value="u.id"
                            >
                                {{ u.name }} ({{ u.email }}){{
                                    u.position ? ` — ${u.position.title}` : ''
                                }}
                            </option>
                        </select>
                        <div class="text-[11px] text-muted-foreground/70">
                            Recipients must be assigned to an institutional
                            position.
                        </div>
                        <InputError :message="errors.to_user_id" />
                    </div>

                    <!-- Notes -->
                    <div class="grid gap-1.5">
                        <Label
                            for="notes"
                            class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                            >Notes</Label
                        >
                        <Input
                            id="notes"
                            name="notes"
                            placeholder="Optional transfer notes"
                            class="h-9 rounded-lg text-sm"
                        />
                        <InputError :message="errors.notes" />
                    </div>

                    <!-- Submit -->
                    <div class="pt-1">
                        <Button
                            type="submit"
                            :disabled="processing"
                            data-test="send-verification-button"
                            data-testid="send-verification-button"
                            class="w-full rounded-lg font-semibold shadow-sm"
                        >
                            Send verification
                        </Button>
                    </div>
                </Form>
            </div>

            <div class="rounded-xl border border-border/50 bg-card shadow-sm">
                <div
                    class="flex items-center justify-between border-b border-border/40 px-5 py-4"
                >
                    <div class="text-sm font-semibold tracking-tight">
                        Recent handovers
                    </div>
                    <span
                        class="inline-flex items-center rounded-full border border-border/40 bg-muted/40 px-2.5 py-1 text-[11px] font-medium text-muted-foreground"
                        >{{ recent.length }} total</span
                    >
                </div>

                <div class="px-5 py-4">
                    <div v-if="recent.length" class="grid gap-3">
                        <div
                            v-for="r in recent"
                            :key="r.id"
                            class="group relative overflow-hidden rounded-xl border border-border/40 transition-all hover:border-border/60 hover:shadow-sm"
                        >
                            <div
                                class="absolute top-0 left-0 h-full w-1 transition-colors"
                                :class="
                                    r.verified_at
                                        ? 'bg-emerald-500'
                                        : 'bg-amber-500'
                                "
                            />
                            <div
                                class="flex flex-col gap-3 p-4 pl-4 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <div class="text-sm font-semibold">
                                            {{
                                                r.asset_name ?? 'Unknown asset'
                                            }}
                                        </div>
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold tracking-wider uppercase',
                                                r.verified_at
                                                    ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                    : 'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400',
                                            ]"
                                        >
                                            <span
                                                :class="[
                                                    'h-1 w-1 rounded-full',
                                                    r.verified_at
                                                        ? 'bg-emerald-500'
                                                        : 'bg-amber-500',
                                                ]"
                                            />
                                            {{
                                                r.verified_at
                                                    ? 'Verified'
                                                    : 'Pending'
                                            }}
                                        </span>
                                    </div>
                                    <div
                                        class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
                                    >
                                        <span
                                            class="inline-flex items-center gap-1"
                                        >
                                            <span
                                                class="h-1 w-1 rounded-full bg-primary/40"
                                            />
                                            {{ r.tag_code ?? '—' }}
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1"
                                        >
                                            <span
                                                class="h-1 w-1 rounded-full bg-primary/40"
                                            />
                                            To:
                                            {{
                                                r.to?.name ?? r.to?.email ?? '—'
                                            }}
                                        </span>
                                    </div>
                                    <div
                                        v-if="r.to_position || r.from_position"
                                        class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground/80"
                                    >
                                        <span v-if="r.to_position">
                                            {{ r.to_position.title
                                            }}{{
                                                r.to_position.department
                                                    ? `, ${r.to_position.department}`
                                                    : ''
                                            }}
                                        </span>
                                        <span
                                            v-if="r.from_position"
                                            class="text-muted-foreground/60"
                                        >
                                            From: {{ r.from_position.title
                                            }}{{
                                                r.from_position.department
                                                    ? `, ${r.from_position.department}`
                                                    : ''
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <div class="shrink-0 text-right">
                                    <div
                                        class="text-[11px] text-muted-foreground/70 tabular-nums"
                                    >
                                        {{ formatDateTime(r.initiated_at) }}
                                    </div>
                                    <div
                                        v-if="r.verified_at"
                                        class="text-[11px] text-emerald-600 tabular-nums dark:text-emerald-400"
                                    >
                                        {{ formatDateTime(r.verified_at) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border/50 py-10 text-center"
                    >
                        <div
                            class="rounded-full border border-border/40 bg-muted/40 p-3"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="text-muted-foreground"
                            >
                                <rect
                                    width="20"
                                    height="14"
                                    x="2"
                                    y="5"
                                    rx="2"
                                />
                                <line x1="2" x2="22" y1="10" y2="10" />
                            </svg>
                        </div>
                        <div class="text-sm font-medium text-muted-foreground">
                            No recent handovers
                        </div>
                        <div class="max-w-xs text-xs text-muted-foreground/70">
                            Initiate a handover using the form. Completed
                            transfers will appear here.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
