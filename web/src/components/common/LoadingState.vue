<script setup lang="ts">import { Skeleton } from '@/components/ui/skeleton'

withDefaults(
  defineProps<{
    rows?: number
    variant?: 'card' | 'table' | 'list'
  }>(),
  { rows: 3, variant: 'card' },
)
</script>

<template>
  <div v-if="variant === 'card'" class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4">
    <div v-for="i in rows" :key="i" class="space-y-3">
      <Skeleton class="aspect-[3/4] w-full rounded-xl" />
      <Skeleton class="h-4 w-3/4" />
      <Skeleton class="h-3 w-1/2" />
    </div>
  </div>
  <div v-else-if="variant === 'list'" class="space-y-3">
    <div v-for="i in rows" :key="i" class="flex items-center gap-4 rounded-xl border p-4">
      <Skeleton class="size-10 shrink-0 rounded-lg" />
      <div class="flex-1 space-y-2">
        <Skeleton class="h-4 w-1/3" />
        <Skeleton class="h-3 w-1/2" />
      </div>
    </div>
  </div>
  <div v-else class="space-y-2">
    <Skeleton v-for="i in rows" :key="i" class="h-10 w-full" />
  </div>
</template>
