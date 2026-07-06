<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

type PaginationLink = { url: string | null; label: string; active: boolean };

defineProps<{
    links: PaginationLink[];
}>();
</script>

<template>
    <nav
        v-if="links.length > 3"
        aria-label="Pagination"
        class="flex flex-wrap items-center justify-center gap-1.5 pt-1"
    >
        <Button
            v-for="(link, i) in links"
            :key="i"
            variant="ghost"
            size="sm"
            as-child
            :disabled="!link.url"
            class="h-8 min-w-8 rounded-lg px-3 text-xs transition-colors"
            :class="
                link.active
                    ? 'bg-primary/12 text-primary shadow-xs hover:bg-primary/15'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
            "
        >
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                preserve-state
            >
                <span v-html="link.label" />
            </Link>
            <span v-else v-html="link.label" />
        </Button>
    </nav>
</template>
