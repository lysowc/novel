<script setup lang="ts">
import { useRoute } from 'vue-router'
import {
  BookOpen, LayoutDashboard, Lightbulb, ScrollText, Settings2, Sparkles, Wrench,
} from '@lucide/vue'
import { cn } from '@/lib/utils'

const route = useRoute()

const items = [
  { to: '/admin', label: '仪表盘', icon: LayoutDashboard, exact: true },
  { to: '/admin/novels', label: '小说管理', icon: BookOpen },
  { to: '/admin/ideas', label: 'AI 点子', icon: Lightbulb },
  { to: '/admin/ai/config', label: 'AI 配置', icon: Sparkles },
  { to: '/admin/ai/prompts', label: 'Prompt 管理', icon: ScrollText },
  { to: '/admin/ai/logs', label: 'AI 日志', icon: Wrench },
  { to: '/admin/settings', label: '系统设置', icon: Settings2 },
]

function isActive(to: string, exact?: boolean) {
  if (exact) return route.path === to
  return route.path.startsWith(to)
}

defineEmits<{ (e: 'navigate'): void }>()
</script>

<template>
  <aside class="flex w-64 shrink-0 flex-col border-r bg-sidebar">
    <div class="flex h-16 items-center gap-3 border-b px-6">
      <span class="flex size-9 items-center justify-center rounded-lg bg-foreground text-background shadow-md">
        <BookOpen class="size-4.5" />
      </span>
      <span class="text-sm font-bold">拾光小说 · 后台</span>
    </div>
    <nav class="flex-1 space-y-1.5 overflow-y-auto p-4">
      <RouterLink
        v-for="item in items"
        :key="item.to"
        :to="item.to"
        class="flex items-center gap-3.5 rounded-lg px-3.5 py-2.5 text-sm text-sidebar-foreground/75 transition-all hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
        :class="cn(isActive(item.to, item.exact) && 'bg-sidebar-accent font-medium text-sidebar-accent-foreground')"
        @click="$emit('navigate')"
      >
        <component :is="item.icon" class="size-4.5" />
        {{ item.label }}
      </RouterLink>
    </nav>
    <div class="border-t p-4 text-center text-xs text-muted-foreground/60">
      AI 小说创作系统 v1.0
    </div>
  </aside>
</template>
