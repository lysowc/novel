<script setup lang="ts">
import { Clock3, FileText } from '@lucide/vue'
import CoverArt from '@/components/common/CoverArt.vue'
import { formatNumber, formatRelative } from '@/lib/format'
import type { Novel } from '@/types/api'

defineProps<{ novel: Novel }>()
</script>

<template>
  <RouterLink
    :to="`/novel/${novel.id}`"
    class="group block overflow-hidden rounded-2xl border bg-card shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg hover:shadow-primary/5"
  >
    <div class="relative overflow-hidden">
      <CoverArt
        :title="novel.title"
        :cover="novel.cover"
        aspect="portrait"
        title-size="md"
        class="rounded-none"
      />
      <div
        v-if="novel.category_name"
        class="absolute left-2.5 top-2.5 rounded-full bg-black/35 px-2 py-0.5 text-[11px] font-medium text-white backdrop-blur-sm"
      >
        {{ novel.category_name }}
      </div>
      <div
        v-if="novel.status === 'finished'"
        class="absolute right-2.5 top-2.5 rounded-full bg-foreground/85 px-2 py-0.5 text-[11px] font-medium text-background"
      >
        完结
      </div>
    </div>
    <div class="space-y-1.5 p-3.5">
      <h3 class="line-clamp-1 text-sm font-semibold transition-colors group-hover:text-primary">
        {{ novel.title }}
      </h3>
      <p class="line-clamp-2 text-xs leading-relaxed text-muted-foreground">
        {{ novel.description || '暂无简介' }}
      </p>
      <div class="flex items-center gap-3 pt-1 text-[11px] text-muted-foreground">
        <span class="inline-flex items-center gap-1">
          <FileText class="size-3" />
          {{ formatNumber(novel.word_count) }} 字
        </span>
        <span class="inline-flex items-center gap-1">
          <Clock3 class="size-3" />
          {{ formatRelative(novel.updated_at) }}
        </span>
      </div>
    </div>
  </RouterLink>
</template>
