<script setup lang="ts">import { Textarea } from '@/components/ui/textarea'
import { Button } from '@/components/ui/button'

import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft, BookOpenCheck, Lightbulb, LoaderCircle, Save, Send, Sparkles, Square,
} from '@lucide/vue'
import { toast } from 'vue-sonner'
import {
  createNovelFromIdea, fetchIdeas, fetchIdeaMessages, fetchModels, fetchProviders,
  saveIdea, streamIdeaChat,
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
const chatError = ref('')
const noProvider = ref(false)

async function loadIdea() {
  loading.value = true
  try {
    // 契约未提供单条点子接口，取列表匹配
    const res = await fetchIdeas({ page: 1, page_size: 100 })
    idea.value = res.list.find((i) => i.id === Number(ideaId.value)) ?? null
    messages.value = await fetchIdeaMessages(ideaId.value)
    await checkAiAvailability()
  } finally {
    loading.value = false
    await scrollToBottom()
  }
}

/** 检查是否有可用的 Provider + Model，没有则给出常驻提示 */
async function checkAiAvailability() {
  try {
    const [providers, models] = await Promise.all([fetchProviders(), fetchModels()])
    noProvider.value = !providers.some((p) => Number(p.status) === 1)
      || !models.some((m) => Number(m.status) === 1)
  } catch {
    noProvider.value = false
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
  chatError.value = ''

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
          chatError.value = ''
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
          chatError.value = msg
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
  <div class="flex min-h-0 flex-1 flex-col bg-background">
    <!-- 顶部栏 -->
    <header class="flex shrink-0 items-center gap-3 border-b bg-background px-4 py-3 sm:px-6">
      <Button variant="ghost" size="icon" class="size-9 shrink-0" @click="router.push('/admin/ideas')">
        <ArrowLeft class="size-4.5" />
      </Button>
      <div class="min-w-0 flex-1">
        <p class="truncate text-[15px] font-semibold">{{ idea?.title || '点子聊天' }}</p>
        <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
          <Lightbulb class="size-3" />
          {{ idea?.category_name || '未分类' }}
          <span v-if="idea">· {{ formatRelative(idea.updated_at) }}</span>
        </p>
      </div>
      <div class="flex shrink-0 gap-2">
        <Button variant="outline" size="sm" class="gap-1.5" @click="onSave">
          <Save class="size-3.5" />
          保存点子
        </Button>
        <Button size="sm" class="gap-1.5" @click="onCreateNovel">
          <BookOpenCheck class="size-3.5" />
          创建小说
        </Button>
      </div>
    </header>

    <!-- 未启用 AI 的常驻提示 -->
    <div
      v-if="noProvider"
      class="shrink-0 border-b bg-destructive/10 px-4 py-2 text-center text-xs text-destructive"
    >
      当前没有可用的 AI Provider/Model，聊天无法回复——请先到「AI 配置」里启用（或添加）后使用
    </div>

    <!-- 消息区（与页面同底色，无边界感） -->
    <div ref="scrollRef" class="min-h-0 flex-1 overflow-y-auto">
      <div v-if="loading" class="flex h-full items-center justify-center gap-2 text-sm text-muted-foreground">
        <LoaderCircle class="size-5 animate-spin" />
        加载中…
      </div>

      <div v-else class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6">
        <div v-if="messages.length === 0" class="flex flex-col items-center pt-16 text-center">
          <span class="flex size-14 items-center justify-center rounded-full bg-foreground/5">
            <Lightbulb class="size-6 text-foreground/60" />
          </span>
          <p class="mt-4 text-base font-medium">和 AI 聊聊这个点子吧</p>
          <p class="mt-1.5 text-sm text-muted-foreground">展开设定、寻找冲突、完善人物，让灵感长成故事。</p>
        </div>

        <div v-else class="space-y-7">
          <template v-for="m in messages" :key="m.id">
            <!-- 用户消息：黑色气泡 -->
            <div v-if="m.role === 'user'" class="flex justify-end">
              <div class="max-w-[75%] whitespace-pre-wrap rounded-2xl rounded-br-md bg-foreground px-4 py-2.5 text-sm leading-relaxed text-background">
                {{ m.content }}
              </div>
            </div>
            <!-- AI 消息：无框纯文本 -->
            <div v-else class="flex gap-3">
              <span class="mt-1 flex size-7 shrink-0 items-center justify-center rounded-full bg-foreground text-background">
                <Sparkles class="size-3.5" />
              </span>
              <div class="min-w-0 flex-1">
                <p class="whitespace-pre-wrap text-[15px] leading-7">
                  {{ m.content }}
                  <span
                    v-if="streaming && m.id === streamingMsg?.id"
                    class="ml-0.5 inline-block h-4 w-1.5 animate-pulse rounded bg-foreground align-middle"
                  />
                </p>
                <p class="mt-1.5 text-xs text-muted-foreground/60">{{ formatRelative(m.created_at) }}</p>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- 输入区 -->
    <div class="shrink-0 border-t bg-background px-4 pb-4 pt-3 sm:px-6">
      <!-- 发送失败的内联错误提示（比 toast 更醒目、常驻） -->
      <div
        v-if="chatError"
        class="mx-auto mb-2 flex w-full max-w-3xl items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive"
      >
        <span class="mt-0.5 font-medium">发送失败：</span>
        <span class="flex-1">{{ chatError }}</span>
      </div>
      <div class="mx-auto flex w-full max-w-3xl items-end gap-2">
        <Textarea
          v-model="input"
          :rows="1"
          class="max-h-32 min-h-11 flex-1 resize-none rounded-2xl"
          placeholder="和 AI 聊聊你的点子…"
          :disabled="sending"
          @keydown.enter.exact.prevent="send"
        />
        <Button
          v-if="streaming"
          variant="secondary"
          class="h-11 gap-1.5"
          @click="stop"
        >
          <Square class="size-4" />
          停止
        </Button>
        <Button
          v-else
          class="h-11 gap-1.5 px-5"
          :disabled="!input.trim() || sending"
          @click="send"
        >
          <Send class="size-4" />
          发送
        </Button>
      </div>
      <p class="mx-auto mt-2 w-full max-w-3xl text-center text-[11px] text-muted-foreground/60">
        Enter 发送 · Shift+Enter 换行
      </p>
    </div>
  </div>
</template>
