<script setup lang="ts">
import { BookOpen } from '@lucide/vue'
import { useSiteStore } from '@/stores/site'
import { onMounted } from 'vue'

const props = withDefaults(defineProps<{ siteName?: string }>(), { siteName: '' })

const year = new Date().getFullYear()

const site = useSiteStore()
onMounted(() => site.load())
</script>

<template>
  <footer class="mt-20 border-t bg-muted/40">
    <div class="mx-auto flex max-w-6xl flex-col items-center gap-3 px-4 py-10 text-center sm:px-6">
      <div class="flex items-center gap-2 text-muted-foreground">
        <BookOpen class="size-4" />
        <span class="text-sm font-medium">{{ props.siteName || site.name }}</span>
      </div>
      <p class="text-xs text-muted-foreground/70">
        个人 AI 小说创作系统 · 灵感始于想法，成篇于坚持
      </p>
      <div class="flex flex-col items-center gap-1.5 sm:flex-row sm:gap-3">
        <span class="text-xs text-muted-foreground/70">© {{ year }} {{ props.siteName || site.name }}</span>
        <a
          href="https://beian.miit.gov.cn/"
          target="_blank"
          rel="noopener noreferrer"
          class="text-xs text-muted-foreground/70 transition-colors hover:text-muted-foreground"
        >
          陕ICP备20009654号-4
        </a>
      </div>
    </div>
  </footer>
</template>
