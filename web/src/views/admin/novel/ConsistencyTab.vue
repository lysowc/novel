<script setup lang="ts">import { Button } from '@/components/ui/button'

import { onMounted, ref } from 'vue'
import { ClipboardCheck, ListChecks, ShieldAlert, Sparkles } from '@lucide/vue'
import { toast } from 'vue-sonner'
import LoadingState from '@/components/common/LoadingState.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import { fetchConsistencyReports } from '@/api'
import { usePollingTask } from '@/composables/useAiTask'
import { formatRelative } from '@/lib/format'
import type { ConsistencyIssue, ConsistencyReport, ConsistencyStatus } from '@/types/api'

const props = defineProps<{ novelId: number }>()

const loading = ref(true)
const reports = ref<ConsistencyReport[]>([])

const { running, run } = usePollingTask()

const statusMap: Record<ConsistencyStatus, { label: string; cls: string }> = {
  ok: { label: '通过', cls: 'bg-muted text-muted-foreground' },
  warning: { label: '需关注', cls: 'border border-foreground/25 text-foreground' },
  critical: { label: '严重问题', cls: 'bg-foreground text-background' },
}

const severityMap: Record<string, { label: string; dot: string }> = {
  minor: { label: '轻微', dot: 'bg-foreground/25' },
  major: { label: '重要', dot: 'bg-foreground/60' },
  critical: { label: '严重', dot: 'bg-foreground' },
}

const typeLabels: Record<string, string> = {
  outline_drift: '剧情偏离',
  contradiction: '前后矛盾',
  foreshadowing_dropped: '伏笔遗忘',
  character_inconsistency: '人物失据',
  timeline_conflict: '时间线冲突',
  other: '其他',
}

async function load() {
  loading.value = true
  try {
    reports.value = await fetchConsistencyReports(props.novelId)
  } finally {
    loading.value = false
  }
}

async function runCheck() {
  await run({
    taskType: 'consistency_check',
    novelId: props.novelId,
    onSuccess: () => {
      load()
      toast.success('审校完成')
    },
  })
}

function issueLabel(issue: ConsistencyIssue) {
  return `${typeLabels[issue.type] ?? issue.type} · ${severityMap[issue.severity]?.label ?? issue.severity}`
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
      <p class="text-sm text-muted-foreground">
        对照大纲、已写章节与小说记忆，检测剧情偏离、前后矛盾与伏笔遗忘
      </p>
      <Button variant="secondary" class="gap-2" :disabled="running" @click="runCheck">
        <Sparkles class="size-4" />
        {{ running ? '审校中…' : '运行审校' }}
      </Button>
    </div>

    <LoadingState v-if="loading" variant="list" :rows="2" />

    <EmptyState
      v-else-if="reports.length === 0"
      title="还没有审校报告"
      description="点击「运行审校」，AI 会对照大纲检查已写章节的一致性（每章生成完成后也会按设置自动审校）"
    >
      <Button size="sm" class="gap-2" @click="runCheck">
        <Sparkles class="size-4" /> 运行审校
      </Button>
    </EmptyState>

    <div v-else class="space-y-4">
      <div v-for="r in reports" :key="r.id" class="rounded-2xl border bg-card shadow-sm">
        <div class="flex flex-wrap items-center gap-2 border-b px-5 py-3">
          <ClipboardCheck class="size-4 text-primary" />
          <span class="text-sm font-medium">审校报告 #{{ r.id }}</span>
          <span class="rounded-full px-2 py-0.5 text-[11px] font-medium" :class="statusMap[r.status]?.cls">
            {{ statusMap[r.status]?.label }}
          </span>
          <span class="text-xs text-muted-foreground">覆盖至第 {{ r.chapter_no }} 章</span>
          <span class="ml-auto text-xs text-muted-foreground">{{ formatRelative(r.created_at) }}</span>
        </div>

        <div class="space-y-4 p-5">
          <p v-if="r.report.summary" class="text-sm leading-relaxed">{{ r.report.summary }}</p>

          <div v-if="r.report.issues.length === 0" class="flex items-center gap-2 text-sm text-muted-foreground">
            <ShieldAlert class="size-4" />
            未发现明显问题
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="(issue, i) in r.report.issues"
              :key="i"
              class="rounded-xl border border-dashed p-3.5"
            >
              <div class="flex items-start gap-2.5">
                <span class="mt-1.5 size-2 shrink-0 rounded-full" :class="severityMap[issue.severity]?.dot" />
                <div class="min-w-0 flex-1">
                  <p class="text-xs font-medium text-muted-foreground">{{ issueLabel(issue) }}</p>
                  <p class="mt-1 text-sm leading-relaxed">{{ issue.description }}</p>
                  <p v-if="issue.suggestion" class="mt-1.5 text-sm text-muted-foreground">
                    <ListChecks class="mr-1 inline size-3.5" />
                    建议：{{ issue.suggestion }}
                  </p>
                  <div v-if="issue.related_chapters?.length" class="mt-2 flex flex-wrap gap-1.5">
                    <span
                      v-for="no in issue.related_chapters"
                      :key="no"
                      class="rounded-full bg-foreground/10 px-2 py-0.5 text-[11px]"
                    >
                      第 {{ no }} 章
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
