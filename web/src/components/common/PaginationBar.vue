<script setup lang="ts">import { Button } from '@/components/ui/button'

import { computed } from 'vue'
import { ChevronLeft, ChevronRight } from '@lucide/vue'

const props = withDefaults(
  defineProps<{
    page: number
    pageSize: number
    total: number
  }>(),
  { page: 1, pageSize: 10, total: 0 },
)

const emit = defineEmits<{ (e: 'update:page', p: number): void }>()

const totalPages = computed(() => Math.max(1, Math.ceil(props.total / props.pageSize)))

/** 生成页码列表（含省略号） */
const pages = computed<(number | '…')[]>(() => {
  const t = totalPages.value
  const cur = props.page
  if (t <= 7) return Array.from({ length: t }, (_, i) => i + 1)
  const set = new Set<number>([1, t, cur - 1, cur, cur + 1])
  const arr = [...set].filter((p) => p >= 1 && p <= t).sort((a, b) => a - b)
  const out: (number | '…')[] = []
  let prev = 0
  for (const p of arr) {
    if (p - prev > 1) out.push('…')
    out.push(p)
    prev = p
  }
  return out
})

function go(p: number) {
  if (p < 1 || p > totalPages.value || p === props.page) return
  emit('update:page', p)
}
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-2 py-4 sm:flex-row sm:gap-4">
    <p v-if="total > 0" class="text-xs text-muted-foreground">
      共 {{ total }} 条 · 第 {{ page }}/{{ totalPages }} 页
    </p>
    <div v-if="totalPages > 1" class="flex items-center gap-1">
      <Button
        variant="outline"
        size="icon"
        class="size-8"
        :disabled="page <= 1"
        @click="go(page - 1)"
      >
        <ChevronLeft class="size-4" />
      </Button>
      <template v-for="(p, i) in pages" :key="i">
        <span v-if="p === '…'" class="px-1 text-xs text-muted-foreground">…</span>
        <Button
          v-else
          variant="ghost"
          size="icon"
          class="size-8 text-sm"
          :class="p === page ? 'bg-primary font-semibold text-primary-foreground hover:bg-primary hover:text-primary-foreground' : ''"
          @click="go(p)"
        >
          {{ p }}
        </Button>
      </template>
      <Button
        variant="outline"
        size="icon"
        class="size-8"
        :disabled="page >= totalPages"
        @click="go(page + 1)"
      >
        <ChevronRight class="size-4" />
      </Button>
    </div>
  </div>
</template>
