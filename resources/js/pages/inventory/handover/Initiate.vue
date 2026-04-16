<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
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

defineProps<{
    users: UserOption[];
    recent: RecentRow[];
}>();

const assetTagCode = ref('');

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: handoverIndex() },
            { title: 'Handover', href: handoverIndex() },
        ],
    },
});
</script>

<template>
    <Head title="Asset handover" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading
            variant="small"
            title="Digital asset handover"
            description="Scan the asset tag and send a verification link to the recipient. The accountable position stays visible throughout the handover."
        />

        <div class="grid gap-6 xl:grid-cols-[minmax(18rem,24rem)_minmax(0,1fr)]">
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <Form v-bind="HandoverController.store.form()" v-slot="{ errors, processing }" class="grid gap-4">
                    <div class="grid gap-2">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <Label for="asset_tag_code">Asset tag code</Label>
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
                            placeholder="Scan / type tag code"
                            required
                        />
                        <InputError :message="errors.asset_tag_code" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="to_user_id">Recipient</Label>
                        <select
                            id="to_user_id"
                            name="to_user_id"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            required
                        >
                            <option value="" disabled selected>Select recipient</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">
                                {{ u.name }} ({{ u.email }}){{ u.position ? ` - ${u.position.title}` : '' }}
                            </option>
                        </select>
                        <div class="text-sm text-muted-foreground">
                            Recipients must already be assigned to an institutional position before the handover can proceed.
                        </div>
                        <InputError :message="errors.to_user_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">Notes (optional)</Label>
                        <Input id="notes" name="notes" placeholder="Optional notes" />
                        <InputError :message="errors.notes" />
                    </div>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="processing">Send verification</Button>
                    </div>
                </Form>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <div class="mb-3 font-medium">Recent handovers</div>
                <div class="grid gap-3">
                    <div
                        v-for="r in recent"
                        :key="r.id"
                        class="rounded-lg border border-sidebar-border/70 p-4 text-sm dark:border-sidebar-border"
                    >
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="font-medium">{{ r.asset_name ?? 'Unknown asset' }}</div>
                                <div class="text-muted-foreground">Tag: {{ r.tag_code ?? '—' }}</div>
                                <div class="text-muted-foreground">
                                    Recipient: {{ r.to?.name ?? r.to?.email ?? '—' }}
                                </div>
                                <div v-if="r.to_position" class="text-muted-foreground">
                                    To position: {{ r.to_position.title }}{{ r.to_position.department ? `, ${r.to_position.department}` : '' }}
                                </div>
                                <div v-if="r.from_position" class="text-muted-foreground">
                                    From position: {{ r.from_position.title }}{{ r.from_position.department ? `, ${r.from_position.department}` : '' }}
                                </div>
                            </div>

                            <div class="text-xs text-muted-foreground">
                                <div>Initiated: {{ r.initiated_at ?? '—' }}</div>
                                <div>Verified: {{ r.verified_at ?? 'Pending' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
