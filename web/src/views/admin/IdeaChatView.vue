<script setup lang="ts">import { Textarea } from '@/components/ui/textarea'
import { Button } from '@/components/ui/button'

import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft, BookOpenCheck, Lightbulb, LoaderCircle, Save, Send, Sparkles, Square,
} from '@lucide/vue'
import { toast } from 'vue-sonner'
import {
  createNovelFromIdea, fetchIdeas, fetchIdeaMessages, saveIdea, streamIdeaChat,
} from '@/api'
import { formatRelative } from '@/lib/format'
import type { Idea, IdeaMessage } from '@/types/api'

const route = useRoute()
const router = useRouter()

const ideaId = computed(() => String(route.params.id))

const loading = ref(true)
const idea = ref<Idea | null>(null)
const messages = ref<IdeaMessage[]>([])
const input = ref('')
const sending = ref(false)
const streaming = ref(false)
const scrollRef = ref<HTMLElement | null>(null)
const controller = ref<AbortController | null>(null)

const streamingText = ref('')
const streamingMsg = ref<IdeaMessage | null>(null)

async function loadIdea() {
  loading.value = true
  try {
    // 契约未提供单条点子接口，取列表匹配
    const res = await fetchIdeas({ page: 1, page_size: 100 })
    idea.value = res.list.find((i) => i.id === Number(ideaId.value)) ?? null
    messages.value = await fetchIdeaMessages(ideaId.value)
  } finally {
    loading.value = false
    await scrollToBottom()
  }
}

async function scrollToBottom() {
  await nextTick()
  const el = scrollRef.value
  if (el) el.scrollTop = el.scrollHeight
}

async function send() {
  const text = input.value.trim()
  if (!text || sending.value) return
  input.value = ''
  sending.value = true

  // 立即追加用户消息
  messages.value.push({
    id: -Date.now(),
    idea_id: Number(ideaId.value),
    role: 'user',
    content: text,
    created_at: new Date().toISOString().slice(0, 19),
  })
  await scrollToBottom()

  // 准备流式 AI 消息
  streaming.value = true
  streamingText.value = ''
  streamingMsg.value = {
    id: -Date.now() - 1,
    idea_id: Number(ideaId.value),
    role: 'assistant',
    content: '',
    created_at: new Date().toISOString().slice(0, 19),
  }
  messages.value.push(streamingMsg.value!)
  controller.value = new AbortController()

  try {
    await streamIdeaChat(
      ideaId.value,
      text,
      {
        onDelta: (t) => {
          streamingText.value += t
          if (streamingMsg.value) streamingMsg.value.content = streamingText.value
          scrollToBottom()
        },
        onDone: async () => {
          streaming.value = false
          controller.value = null
          // 结束后刷新正式消息列表
          messages.value = await fetchIdeaMessages(ideaId.value)
          await scrollToBottom()
        },
        onError: (msg) => {
          streaming.value = false
          controller.value = null
          if (streamingMsg.value && !streamingMsg.value.content) {
            messages.value = messages.value.filter((m) => m.id !== streamingMsg.value!.id)
          }
          toast.error(msg)
        },
      },
      controller.value.signal,
    )
  } finally {
    sending.value = false
  }
}

function stop() {
  controller.value?.abort()
  controller.value = null
  streaming.value = false
  if (streamingMsg.value && !streamingMsg.value.content) {
    messages.value = messages.value.filter((m) => m.id !== streamingMsg.value!.id)
  }
}

function transcript(): string {
  return messages.value
    .map((m) => `${m.role === 'user' ? '用户' : 'AI'}：${m.content}`)
    .join('\n\n')
}

async function onSave() {
  try {
    await saveIdea(ideaId.value, { content: transcript() })
    toast.success('点子已保存')
  } catch {
    // 请求层已提示
  }
}

async function onCreateNovel() {
  try {
    const res = await createNovelFromIdea(ideaId.value)
    toast.success('小说已创建，设定生成任务已入队')
    router.push(`/admin/novels/${res.novel_id}`)
  } catch {
    // 请求层已提示
  }
}

onMounted(loadIdea)
onBeforeUnmount(() => controller.value?.abort())
</script>

<template>
  <div class="flex h-[calc(100vh-3.5rem)] flex-col">
    <!-- 顶部栏 -->
    <div class="flex flex-wrap items-center gap-2 border-b bg-background/85 px-4 py-3 backdrop-blur-md">
      <Button variant="ghost" size="icon" class="size-8" @click="router.push('/admin/ideas')">
        <ArrowLeft class="size-4" />
      </Button>
      <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-semibold">{{ idea?.title || '点子聊天' }}</p>
        <p class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
          <Lightbulb class="size-3" />
          {{ idea?.category_name || '未分类' }}
          <span v-if="idea">· {{ formatRelative(idea.updated_at) }}</span>
        </p>
      </div>
      <div class="flex gap-2">
        <Button variant="outline" size="sm" class="gap-1.5" @click="onSave">
          <Save class="size-3.5" />
          保存点子
        </Button>
        <Button size="sm" class="gap-1.5" @click="onCreateNovel">
          <BookOpenCheck class="size-3.5" />
          根据点子创建小说
        </Button>
      </div>
    </div>

    <!-- 消息区 -->
    <div ref="scrollRef" class="min-h-0 flex-1 overflow-y-auto bg-muted/20">
      <div v-if="loading" class="flex h-full items-center justify-center gap-2 text-sm text-muted-foreground">
        <LoaderCircle class="size-5 animate-spin" />
        加载中…
      </div>

      <div v-else class="mx-auto max-w-3xl space-y-5 px-4 py-6">
        <div
          v-if="messages.length === 0"
          class="rounded-2xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
        >
          <Lightbulb class="mx-auto size-8 text-foreground/70" />
          <p class="mt-3 font-medium text-foreground">和 AI 聊聊这个点子吧</p>
          <p class="mt-1 text-xs">展开设定、寻找冲突、完善人物，让灵感长成故事。</p>
        </div>

        <template v-for="m in messages" :key="m.id">
          <!-- 用户消息 -->
          <div v-if="m.role === 'user'" class="flex justify-end gap-2.5">
            <div class="max-w-[80%] rounded-2xl rounded-br-md bg-primary px-4 py-2.5 text-sm text-primary-foreground shadow-sm">
              {{ m.content }}
            </div>
          </div>
          <!-- AI 消息 -->
          <div v-else class="flex gap-2.5">
            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-foreground text-background shadow-md">
              <Sparkles class="size-4" />
            </span>
            <div class="max-w-[85%] min-w-0">
              <div
                class="rounded-2xl rounded-tl-md border bg-card px-4 py-2.5 text-sm leading-relaxed shadow-sm"
                :class="m.content ? '' : 'min-h-10'"
              >
                <span class="whitespace-pre-wrap">{{ m.content }}</span>
                <span v-if="streaming && m.id === streamingMsg?.id" class="ml-0.5 inline-block h-4 w-1.5 animate-pulse rounded bg-primary align-middle" />
              </div>
              <p class="mt-1 px-1 text-[10px] text-muted-foreground/70">{{ formatRelative(m.created_at) }}</p>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- 输入区 -->
    <div class="border-t bg-background px-4 py-3">
      <div class="mx-auto flex max-w-3xl items-end gap-2">
        <Textarea
          v-model="input"
          :rows="1"
          class="max-h-32 min-h-10 flex-1 resize-none rounded-xl"
          placeholder="和 AI 聊聊你的点子…（Enter 发送，Shift+Enter 换行）"
          :disabled="sending"
          @keydown.enter.exact.prevent="send"
        />
        <Button
          v-if="streaming"
          variant="secondary"
          class="h-10 gap-1.5"
          @click="stop"
        >
          <Square class="size-4" />
          停止
        </Button>
        <Button
          v-else
          class="h-10 gap-1.5 px-4"
          :disabled="!input.trim() || sending"
          @click="send"
        >
          <Send class="size-4" />
          发送
        </Button>
      </div>
    </div>
  </div>
</template>
