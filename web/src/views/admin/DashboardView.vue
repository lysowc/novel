<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  BookOpen, FileText, Feather, Lightbulb, LoaderCircle, ScrollText,
  Timer, CheckCircle2, XCircle, Clock3, ListTodo,
} from '@lucide/vue'
import LoadingState from '@/components/common/LoadingState.vue'
import { fetchDashboard } from '@/api'
import { formatNumber, formatRelative } from '@/lib/format'
import type { Dashboard, TaskStatus } from '@/types/api'

const loading = ref(true)
const data = ref<Dashboard | null>(null)

const stats = computed(() => {
  const d = data.value
  if (!d) return []
  return [
    { label: '小说总数', value: d.novel_count, icon: BookOpen, tint: 'from-indigo-500 to-violet-500' },
    { label: '章节总数', value: d.chapter_count, icon: ScrollText, tint: 'from-sky-500 to-cyan-500' },
    { label: '累计字数', value: formatNumber(d.total_words) + ' 字', icon: Feather, tint: 'from-emerald-500 to-teal-500' },
    { label: '点子数量', value: d.idea_count, icon: Lightbulb, tint: 'from-amber-500 to-orange-500' },
    { label: '运行中任务', value: d.running_tasks, icon: LoaderCircle, tint: 'from-rose-500 to-pink-500' },
    { label: '今日新增章节', value: d.today_chapters, icon: FileText, tint: 'from-fuchsia-500 to-purple-500' },
  ]
})

const taskStatusMap: Record<TaskStatus, { label: string; cls: string; icon: typeof CheckCircle2 }> = {
  pending: { label: '排队中', cls: 'bg-muted text-muted-foreground', icon: Clock3 },
  running: { label: '运行中', cls: 'bg-sky-500/10 text-sky-600 dark:text-sky-400', icon: LoaderCircle },
  success: { label: '成功', cls: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400', icon: CheckCircle2 },
  failed: { label: '失败', cls: 'bg-destructive/10 text-destructive', icon: XCircle },
}

const taskTypeLabel: Record<string, string> = {
  generate_setting: '生成设定', generate_outline: '生成大纲', generate_chapter: '生成章节',
  continue_chapter: '续写章节', regenerate_chapter: '重新生成', generate_summary: '生成摘要',
  update_memory: '更新记忆',
}

onMounted(async () => {
  try {
    data.value = await fetchDashboard()
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- 统计卡片 -->
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">
      <div
        v-for="s in stats"
        :key="s.label"
        class="fade-up rounded-2xl border bg-card p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
      >
        <span
          class="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-md"
          :class="s.tint"
        >
          <component :is="s.icon" class="size-4.5" />
        </span>
        <p class="mt-3 truncate text-lg font-bold leading-none">{{ s.value }}</p>
        <p class="mt-1.5 text-xs text-muted-foreground">{{ s.label }}</p>
      </div>
    </div>

    <LoadingState v-if="loading" variant="table" :rows="4" />

    <template v-else-if="data">
      <div class="grid gap-6 lg:grid-cols-2">
        <!-- 最近任务 -->
        <div class="rounded-2xl border bg-card shadow-sm">
          <div class="flex items-center justify-between border-b px-5 py-3.5">
            <h2 class="flex items-center gap-2 text-sm font-semibold">
              <ListTodo class="size-4 text-primary" />
              最近任务
            </h2>
            <RouterLink to="/admin/novels" class="text-xs text-primary hover:underline">前往管理</RouterLink>
          </div>
          <div v-if="data.recent_tasks.length === 0" class="p-6 text-center text-xs text-muted-foreground">
            暂无任务记录
          </div>
          <ul v-else class="divide-y">
            <li v-for="t in data.recent_tasks" :key="t.id" class="flex items-center gap-3 px-5 py-3">
              <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                <component :is="taskStatusMap[t.status]?.icon" class="size-4" />
              </span>
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">{{ taskTypeLabel[t.task_type] || t.task_type }}</p>
                <p class="text-xs text-muted-foreground">任务 #{{ t.id }} · {{ formatRelative(t.created_at) }}</p>
              </div>
              <span
                class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium"
                :class="taskStatusMap[t.status]?.cls"
              >
                {{ taskStatusMap[t.status]?.label }}
              </span>
            </li>
          </ul>
        </div>

        <!-- 最近 AI 日志 -->
        <div class="rounded-2xl border bg-card shadow-sm">
          <div class="flex items-center justify-between border-b px-5 py-3.5">
            <h2 class="flex items-center gap-2 text-sm font-semibold">
              <Timer class="size-4 text-primary" />
              最近 AI 调用
            </h2>
            <RouterLink to="/admin/ai/logs" class="text-xs text-primary hover:underline">查看全部</RouterLink>
          </div>
          <div v-if="data.recent_logs.length === 0" class="p-6 text-center text-xs text-muted-foreground">
            暂无调用日志
          </div>
          <ul v-else class="divide-y">
            <li v-for="l in data.recent_logs" :key="l.id" class="flex items-center gap-3 px-5 py-3">
              <span
                class="size-2 shrink-0 rounded-full"
                :class="l.status === 1 ? 'bg-emerald-500' : 'bg-destructive'"
              />
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">
                  {{ l.model }}
                  <span class="ml-1 text-xs font-normal text-muted-foreground">{{ l.provider }}</span>
                </p>
                <p class="text-xs text-muted-foreground">
                  {{ taskTypeLabel[l.task_type] || l.task_type }} · {{ formatNumber(l.total_tokens) }} tokens · {{ (l.duration / 1000).toFixed(1) }}s
                </p>
              </div>
              <span class="shrink-0 text-xs text-muted-foreground">{{ formatRelative(l.created_at) }}</span>
            </li>
          </ul>
        </div>
      </div>
    </template>
  </div>
</template>
