<script setup lang="ts">import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Button } from '@/components/ui/button'

import { onMounted, ref } from 'vue'
import { Trash2 } from '@lucide/vue'
import { toast } from 'vue-sonner'
import PageHeader from '@/components/common/PageHeader.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PaginationBar from '@/components/common/PaginationBar.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import { clearLogs, fetchLogs } from '@/api'
import { formatDate, formatDuration, formatNumber } from '@/lib/format'
import type { AiLog, PageResult } from '@/types/api'

const loading = ref(true)
const logs = ref<PageResult<AiLog>>({ list: [], total: 0, page: 1, page_size: 15 })
const clearOpen = ref(false)
const clearing = ref(false)

const taskTypeLabel: Record<string, string> = {
  generate_setting: '生成设定', generate_outline: '生成大纲', generate_chapter: '生成章节',
  continue_chapter: '续写章节', regenerate_chapter: '重新生成', generate_summary: '生成摘要',
  update_memory: '更新记忆', idea_chat: '点子聊天',
}

async function load() {
  loading.value = true
  try {
    logs.value = await fetchLogs({ page: logs.value.page, page_size: logs.value.page_size })
  } finally {
    loading.value = false
  }
}

async function onClear() {
  clearing.value = true
  try {
    await clearLogs()
    toast.success('日志已清空')
    clearOpen.value = false
    logs.value.page = 1
    load()
  } catch {
    // 请求层已提示
  } finally {
    clearing.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="AI 日志" description="记录每一次 AI 调用的用量与状态">
      <template #actions>
        <Button variant="destructive" class="gap-2" @click="clearOpen = true">
          <Trash2 class="size-4" />
          清空日志
        </Button>
      </template>
    </PageHeader>

    <LoadingState v-if="loading" variant="table" :rows="8" />

    <div v-else-if="logs.list.length === 0" class="rounded-2xl border bg-card">
      <EmptyState title="暂无日志" description="AI 调用记录会显示在这里" />
    </div>

    <div v-else class="overflow-x-auto rounded-2xl border bg-card shadow-sm">
      <Table class="min-w-[820px]">
        <TableHeader>
          <TableRow class="hover:bg-transparent">
            <TableHead>时间</TableHead>
            <TableHead>模型</TableHead>
            <TableHead>任务类型</TableHead>
            <TableHead class="text-right">输入 tokens</TableHead>
            <TableHead class="text-right">输出 tokens</TableHead>
            <TableHead class="text-right">总 tokens</TableHead>
            <TableHead class="text-right">耗时</TableHead>
            <TableHead>状态</TableHead>
            <TableHead>错误信息</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="l in logs.list" :key="l.id">
            <TableCell class="whitespace-nowrap text-xs">{{ formatDate(l.created_at, true) }}</TableCell>
            <TableCell>
              <p class="font-medium">{{ l.model }}</p>
              <p class="text-[11px] text-muted-foreground">{{ l.provider }}</p>
            </TableCell>
            <TableCell class="text-xs">{{ taskTypeLabel[l.task_type] || l.task_type }}</TableCell>
            <TableCell class="text-right text-xs">{{ formatNumber(l.prompt_tokens) }}</TableCell>
            <TableCell class="text-right text-xs">{{ formatNumber(l.completion_tokens) }}</TableCell>
            <TableCell class="text-right text-xs">{{ formatNumber(l.total_tokens) }}</TableCell>
            <TableCell class="text-right text-xs">{{ formatDuration(l.duration) }}</TableCell>
            <TableCell>
              <span
                class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                :class="l.status === 1 ? 'bg-foreground/10 text-foreground' : 'bg-destructive/10 text-destructive'"
              >
                {{ l.status === 1 ? '成功' : '失败' }}
              </span>
            </TableCell>
            <TableCell class="max-w-52">
              <p class="line-clamp-1 text-xs text-destructive" :title="l.error_message">{{ l.error_message || '—' }}</p>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <PaginationBar
      v-if="!loading && logs.total > 0"
      v-model:page="logs.page"
      :page-size="logs.page_size"
      :total="logs.total"
      @update:page="load"
    />

    <ConfirmDialog
      :open="clearOpen"
      title="清空日志"
      description="确定要清空全部 AI 调用日志吗？此操作无法撤销。"
      confirm-text="清空"
      :loading="clearing"
      @update:open="(v: boolean) => (clearOpen = v)"
      @confirm="onClear"
    />
  </div>
</template>
