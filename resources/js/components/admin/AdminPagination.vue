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
        <template v-for="(link, i) in links" :key="i">
            <Button
                v-if="link.url"
                variant="ghost"
                size="sm"
                as-child
                class="min-h-11 min-w-11 rounded-lg px-3 text-xs transition-colors sm:min-h-8 sm:min-w-8"
                :class="
                    link.active
                        ? 'bg-primary/12 text-primary shadow-xs hover:bg-primary/15'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                "
            >
                <Link :href="link.url" preserve-scroll preserve-state>
                    <span v-html="link.label" />
                </Link>
            </Button>
            <span
                v-else
                aria-disabled="true"
                class="pointer-events-none inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg px-3 text-xs text-muted-foreground/50 select-none sm:min-h-8 sm:min-w-8"
                v-html="link.label"
            />
        </template>
    </nav>
</template>
