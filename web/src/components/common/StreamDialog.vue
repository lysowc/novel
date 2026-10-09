<script setup lang="ts">import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'

import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { LoaderCircle, Sparkles, Square, AlertCircle, CheckCircle2 } from '@lucide/vue'
import { createAiTask, fetchTask, streamAiTask } from '@/api'
import type { AiTask, TaskType } from '@/types/api'

const props = withDefaults(
  defineProps<{
    open: boolean
    title?: string
    taskType: TaskType
    novelId: number
    params?: Record<string, unknown>
    /** stream: 流式渲染正文；poll: 仅轮询状态 */
    mode?: 'stream' | 'poll'
    /** 传入时订阅已存在的任务（不创建），用于"查看进行中的任务" */
    attachTaskId?: number
  }>(),
  { title: 'AI 创作', params: undefined, mode: 'stream', attachTaskId: undefined },
)

const emit = defineEmits<{
  (e: 'update:open', v: boolean): void
  (e: 'done', task: AiTask): void
  (e: 'error', msg: string): void
}>()

const phase = ref<'idle' | 'running' | 'done' | 'error'>('idle')
const output = ref('')
const statusText = ref('')
const errorMsg = ref('')
const taskId = ref<number | null>(null)
const controller = ref<AbortController | null>(null)
const contentRef = ref<HTMLElement | null>(null)
const stickToBottom = ref(true)

const isRunning = computed(() => phase.value === 'running')

function reset() {
  phase.value = 'idle'
  output.value = ''
  statusText.value = ''
  errorMsg.value = ''
  taskId.value = null
  stickToBottom.value = true
}

async function onScroll() {
  const el = contentRef.value
  if (!el) return
  stickToBottom.value = el.scrollHeight - el.scrollTop - el.clientHeight < 60
}

async function scrollToBottom() {
  if (!stickToBottom.value) return
  await nextTick()
  const el = contentRef.value
  if (el) el.scrollTop = el.scrollHeight
}

async function start() {
  reset()
  phase.value = 'running'
  controller.value = new AbortController()
  try {
    // attach 模式：订阅已存在的任务；否则创建新任务
    const task = props.attachTaskId
      ? await fetchTask(props.attachTaskId)
      : await createAiTask({
          task_type: props.taskType,
          novel_id: props.novelId,
          params: props.params,
        })
    taskId.value = task.id

    if (props.mode === 'poll') {
      // 轮询状态
      const deadline = Date.now() + 10 * 60 * 1000
      while (Date.now() < deadline) {
        await new Promise((r) => setTimeout(r, 2000))
        if (controller.value?.signal.aborted) return
        const t = await fetchTask(task.id)
        statusText.value = `任务状态：${t.status}`
        if (t.status === 'success') {
          phase.value = 'done'
          emit('done', t)
          return
        }
        if (t.status === 'failed') {
          phase.value = 'error'
          errorMsg.value = t.error_message || '任务失败'
          emit('error', errorMsg.value)
          return
        }
      }
      phase.value = 'error'
      errorMsg.value = '任务处理超时'
      emit('error', errorMsg.value)
      return
    }

    // 流式订阅
    await streamAiTask(
      task.id,
      {
        onChunk: (text) => {
          output.value += text
          scrollToBottom()
        },
        onStatus: (s) => {
          statusText.value = s
        },
        onDone: async (data) => {
          const evt = data as { status?: string; error_message?: string } | undefined
          if (evt?.status === 'failed') {
            phase.value = 'error'
            errorMsg.value = evt.error_message || '任务失败'
            emit('error', errorMsg.value)
            return
          }
          phase.value = 'done'
          const t = await fetchTask(task.id).catch(() => task)
          emit('done', t)
        },
        onError: (msg) => {
          phase.value = 'error'
          errorMsg.value = msg
          emit('error', msg)
        },
      },
      controller.value.signal,
    )
  } catch (e) {
    phase.value = 'error'
    errorMsg.value = (e as Error).message || '任务创建失败'
    emit('error', errorMsg.value)
  }
}

function stop() {
  controller.value?.abort()
  if (phase.value === 'running') {
    phase.value = 'error'
    errorMsg.value = '已手动中断'
  }
}

watch(
  () => props.open,
  (v) => {
    if (v) start()
    else controller.value?.abort()
  },
)

onMounted(() => {
  // 组件可能以 open=true 直接挂载（父组件 v-if + v-model:open），watch 不会触发
  if (props.open) start()
})

watch(
  () => props.taskType,
  () => {
    if (props.open) start()
  },
)
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="flex h-[80vh] max-w-2xl flex-col gap-0 p-0 sm:max-w-2xl">
      <DialogHeader class="border-b px-5 py-4">
        <div class="flex items-center gap-2">
          <Sparkles class="size-4 text-primary" />
          <DialogTitle class="text-base">{{ title }}</DialogTitle>
        </div>
        <DialogDescription class="flex items-center gap-2 text-xs">
          <template v-if="isRunning">
            <LoaderCircle class="size-3 animate-spin" />
            <span>任务 {{ taskId ?? '创建中' }} · {{ statusText || 'AI 正在创作…' }}</span>
          </template>
          <template v-else-if="phase === 'done'">
            <CheckCircle2 class="size-3 text-foreground" />
            <span>已完成</span>
          </template>
          <template v-else-if="phase === 'error'">
            <AlertCircle class="size-3 text-destructive" />
            <span>出错了</span>
          </template>
        </DialogDescription>
      </DialogHeader>

      <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5" ref="contentRef" @scroll.passive="onScroll">
        <div v-if="phase === 'idle' || (phase === 'running' && !output && mode === 'stream')" class="flex h-full flex-col items-center justify-center gap-3 text-muted-foreground">
          <LoaderCircle class="size-8 animate-spin text-primary" />
          <p class="text-sm">{{ props.attachTaskId ? '正在连接任务…' : '正在创建任务并连接 AI…' }}</p>
        </div>
        <p
          v-else-if="output"
          class="prose-novel whitespace-pre-wrap text-[15px]"
        >{{ output }}</p>
        <div v-else-if="mode === 'poll'" class="flex h-full flex-col items-center justify-center gap-3 text-muted-foreground">
          <LoaderCircle class="size-8 animate-spin text-primary" />
          <p class="text-sm">{{ statusText || 'AI 正在处理…' }}</p>
        </div>
        <div v-if="phase === 'error'" class="mt-4 rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
          {{ errorMsg }}
        </div>
      </div>

      <DialogFooter class="border-t px-5 py-3">
        <Button v-if="isRunning" variant="secondary" @click="stop">
          <Square class="size-4" />
          停止生成
        </Button>
        <Button v-else @click="emit('update:open', false)">关闭</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
