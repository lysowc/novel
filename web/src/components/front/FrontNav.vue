<script setup lang="ts">
import { BookOpen, LayoutDashboard } from '@lucide/vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'

withDefaults(defineProps<{ siteName?: string }>(), { siteName: '拾光小说' })
</script>

<template>
  <header class="sticky top-0 z-40 border-b bg-background/80 backdrop-blur-md">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
      <RouterLink to="/" class="group flex items-center gap-2.5">
        <span class="flex size-9 items-center justify-center rounded-xl bg-foreground text-background shadow-md transition-transform duration-300 group-hover:scale-105">
          <BookOpen class="size-5" />
        </span>
        <span class="text-lg font-bold tracking-wide">{{ siteName }}</span>
      </RouterLink>

      <nav class="hidden items-center gap-1 md:flex">
        <RouterLink to="/" class="rounded-lg px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground">
          首页
        </RouterLink>
        <RouterLink to="/category/0" class="rounded-lg px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground">
          全部小说
        </RouterLink>
      </nav>

      <div class="flex items-center gap-1.5">
        <ThemeToggle />
        <RouterLink to="/admin" class="ml-1 inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground">
          <LayoutDashboard class="size-4" />
          <span class="hidden sm:inline">后台</span>
        </RouterLink>
      </div>
    </div>
  </header>
</template>
