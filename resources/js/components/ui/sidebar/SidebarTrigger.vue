<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { computed } from "vue"
import { PanelLeftClose, PanelLeftOpen } from "lucide-vue-next"
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
import { useSidebar } from "./utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { isMobile, openMobile, state, toggleSidebar } = useSidebar()

const isExpanded = computed(() => isMobile.value ? openMobile.value : state.value === 'expanded')
const accessibleLabel = computed(() => {
  if (isMobile.value) {
    return openMobile.value ? 'Close navigation menu' : 'Open navigation menu'
  }

  return state.value === 'collapsed' ? 'Expand navigation sidebar' : 'Collapse navigation sidebar'
})
</script>

<template>
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    aria-controls="app-navigation-sidebar"
    :aria-expanded="isExpanded"
    :aria-label="accessibleLabel"
    variant="ghost"
    size="icon"
    :class="cn('size-11 sm:size-9', props.class)"
    @click="toggleSidebar"
  >
    <PanelLeftOpen v-if="isMobile || state === 'collapsed'" />
    <PanelLeftClose v-else />
    <span class="sr-only">{{ accessibleLabel }}</span>
  </Button>
</template>
