<script setup lang="ts">
import { Inbox } from '@lucide/vue'

withDefaults(
  defineProps<{
    title?: string
    description?: string
    icon?: unknown
  }>(),
  { title: '暂无数据', description: '', icon: undefined },
)
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
    <div class="flex size-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground/70">
      <component :is="icon || Inbox" class="size-7" />
    </div>
    <div>
      <p class="text-sm font-medium text-foreground">{{ title }}</p>
      <p v-if="description" class="mt-1 text-xs text-muted-foreground">{{ description }}</p>
    </div>
    <slot />
  </div>
</template>
