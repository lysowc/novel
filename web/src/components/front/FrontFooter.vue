<script setup lang="ts">
import { BookOpen } from '@lucide/vue'

withDefaults(defineProps<{ siteName?: string }>(), { siteName: '拾光小说' })
</script>

<template>
  <footer class="mt-20 border-t bg-muted/40">
    <div class="mx-auto flex max-w-6xl flex-col items-center gap-3 px-4 py-10 text-center sm:px-6">
      <div class="flex items-center gap-2 text-muted-foreground">
        <BookOpen class="size-4" />
        <span class="text-sm font-medium">{{ siteName }}</span>
      </div>
      <p class="text-xs text-muted-foreground/70">
        个人 AI 小说创作系统 · 灵感始于想法，成篇于坚持
      </p>
    </div>
  </footer>
</template>
