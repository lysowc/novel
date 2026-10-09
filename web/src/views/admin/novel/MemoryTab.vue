<script setup lang="ts">import { Textarea } from '@/components/ui/textarea'
import { Button } from '@/components/ui/button'

import { computed, onMounted, ref } from 'vue'
import { Braces, ListTree, Save, Sparkles } from '@lucide/vue'
import { toast } from 'vue-sonner'
import LoadingState from '@/components/common/LoadingState.vue'
import { fetchNovelMemory, saveNovelMemory } from '@/api'
import { usePollingTask } from '@/composables/useAiTask'
import type { Memory, StructuredMemory } from '@/types/api'

const props = defineProps<{ novelId: number }>()

const loading = ref(true)
const saving = ref(false)
const content = ref('{}')
const updatedAt = ref('')
/** structured: 记忆槽卡片视图；raw: JSON 直接编辑 */
const viewMode = ref<'structured' | 'raw'>('structured')

const { running, run } = usePollingTask()

const parsed = computed(() => {
  try {
    return JSON.parse(content.value)
  } catch {
    return null
  }
})

const structured = computed<StructuredMemory | null>(() => {
  const p = parsed.value
  return p && typeof p === 'object' && !Array.isArray(p) && p.schema === 'v2' ? (p as StructuredMemory) : null
})

const isJsonValid = computed(() => parsed.value !== null)

async function load() {
  loading.value = true
  try {
    const m: Memory = await fetchNovelMemory(props.novelId)
    content.value = m.content || '{}'
    updatedAt.value = m.updated_at
    // v2 记忆默认展示结构化视图，旧格式/坏 JSON 回到编辑视图
    try {
      const p = JSON.parse(content.value)
      viewMode.value = p && p.schema === 'v2' ? 'structured' : 'raw'
    } catch {
      viewMode.value = 'raw'
    }
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!isJsonValid.value) {
    toast.error('记忆内容不是合法的 JSON')
    return
  }
  saving.value = true
  try {
    const m = await saveNovelMemory(props.novelId, content.value)
    content.value = m.content
    updatedAt.value = m.updated_at
    toast.success('记忆已保存')
  } catch {
    // 请求层已提示
  } finally {
    saving.value = false
  }
}

async function aiUpdate() {
  await run({
    taskType: 'update_memory',
    novelId: props.novelId,
    onSuccess: () => {
      load()
      toast.success('记忆已更新')
    },
  })
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
      <p class="text-sm text-muted-foreground">
        结构化记忆槽：剧情状态 / 人物 / 伏笔 / 世界观 / 时间线，AI 创作时自动带入
        <span v-if="updatedAt" class="ml-2 text-xs">最后更新：{{ updatedAt.slice(0, 16) }}</span>
      </p>
      <div class="flex flex-wrap gap-2">
        <Button variant="secondary" class="gap-2" :disabled="running" @click="aiUpdate">
          <Sparkles class="size-4" />
          {{ running ? 'AI 更新中…' : 'AI 更新记忆' }}
        </Button>
        <div v-if="structured" class="flex rounded-lg border p-0.5">
          <Button
            variant="ghost"
            size="sm"
            class="gap-1.5 rounded-md"
            :class="viewMode === 'structured' ? 'bg-foreground/10' : ''"
            @click="viewMode = 'structured'"
          >
            <ListTree class="size-3.5" /> 结构化
          </Button>
          <Button
            variant="ghost"
            size="sm"
            class="gap-1.5 rounded-md"
            :class="viewMode === 'raw' ? 'bg-foreground/10' : ''"
            @click="viewMode = 'raw'"
          >
            <Braces class="size-3.5" /> JSON
          </Button>
        </div>
        <Button class="gap-2" :disabled="saving" @click="save">
          <Save class="size-4" />
          保存记忆
        </Button>
      </div>
    </div>

    <LoadingState v-if="loading" variant="table" :rows="2" />

    <!-- 结构化视图 -->
    <div v-else-if="viewMode === 'structured' && structured" class="space-y-4">
      <!-- 当前状态 -->
      <div class="rounded-2xl border bg-card shadow-sm">
        <div class="border-b px-5 py-3 text-sm font-medium">当前状态</div>
        <div class="grid gap-3 p-5 sm:grid-cols-3">
          <div>
            <p class="text-xs text-muted-foreground">地点</p>
            <p class="mt-0.5 text-sm">{{ structured.current_state.location || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-muted-foreground">时间</p>
            <p class="mt-0.5 text-sm">{{ structured.current_state.time || '—' }}</p>
          </div>
          <div class="sm:col-span-1">
            <p class="text-xs text-muted-foreground">剧情进展</p>
            <p class="mt-0.5 text-sm leading-relaxed">{{ structured.current_state.plot_progress || '—' }}</p>
          </div>
        </div>
      </div>

      <!-- 人物状态 -->
      <div v-if="structured.characters.length" class="rounded-2xl border bg-card shadow-sm">
        <div class="border-b px-5 py-3 text-sm font-medium">人物状态（{{ structured.characters.length }}）</div>
        <div class="grid gap-3 p-5 sm:grid-cols-2">
          <div v-for="(c, i) in structured.characters" :key="i" class="rounded-xl border bg-background/60 p-3.5">
            <p class="font-medium">{{ c.name }}</p>
            <p v-if="c.status" class="mt-1 text-sm">{{ c.status }}</p>
            <p v-if="c.relationships" class="mt-1 text-xs text-muted-foreground">关系：{{ c.relationships }}</p>
            <p v-if="c.goals" class="mt-1 text-xs text-muted-foreground">目标：{{ c.goals }}</p>
          </div>
        </div>
      </div>

      <!-- 伏笔 -->
      <div v-if="structured.foreshadowing.length" class="rounded-2xl border bg-card shadow-sm">
        <div class="border-b px-5 py-3 text-sm font-medium">伏笔（{{ structured.foreshadowing.length }}）</div>
        <div class="space-y-2.5 p-5">
          <div v-for="(f, i) in structured.foreshadowing" :key="i" class="flex items-start gap-2.5">
            <span
              class="mt-1 size-2 shrink-0 rounded-full"
              :class="f.status === 'resolved' ? 'bg-foreground/25' : 'bg-foreground'"
            />
            <div class="min-w-0 flex-1">
              <p class="text-sm leading-relaxed" :class="f.status === 'resolved' ? 'text-muted-foreground line-through' : ''">
                {{ f.description }}
              </p>
              <p class="mt-0.5 text-[11px] text-muted-foreground">
                <span v-if="f.status === 'resolved'">已回收 · 第 {{ f.resolved_chapter }} 章</span>
                <span v-else>未回收<span v-if="f.planted_chapter > 0"> · 约第 {{ f.planted_chapter }} 章埋下</span></span>
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- 时间线 -->
      <div v-if="structured.timeline.length" class="rounded-2xl border bg-card shadow-sm">
        <div class="border-b px-5 py-3 text-sm font-medium">关键时间线</div>
        <div class="space-y-2 p-5">
          <div v-for="(e, i) in structured.timeline" :key="i" class="flex items-start gap-3">
            <span class="w-16 shrink-0 text-xs font-medium text-muted-foreground">
              {{ e.chapter > 0 ? `第 ${e.chapter} 章` : '—' }}
            </span>
            <p class="text-sm leading-relaxed">{{ e.event }}</p>
          </div>
        </div>
      </div>

      <!-- 世界观增量 / 未解决事件 / 重要物品 -->
      <div class="grid gap-4 lg:grid-cols-2">
        <div v-if="structured.world_facts.length" class="rounded-2xl border bg-card shadow-sm">
          <div class="border-b px-5 py-3 text-sm font-medium">世界观增量</div>
          <ul class="space-y-1.5 p-5 text-sm">
            <li v-for="(w, i) in structured.world_facts" :key="i" class="flex gap-2">
              <span class="mt-2 size-1 shrink-0 rounded-full bg-foreground/40" />{{ w }}
            </li>
          </ul>
        </div>
        <div v-if="structured.unresolved_events.length" class="rounded-2xl border bg-card shadow-sm">
          <div class="border-b px-5 py-3 text-sm font-medium">未解决事件</div>
          <ul class="space-y-1.5 p-5 text-sm">
            <li v-for="(u, i) in structured.unresolved_events" :key="i" class="flex gap-2">
              <span class="mt-2 size-1 shrink-0 rounded-full bg-foreground/40" />{{ u }}
            </li>
          </ul>
        </div>
      </div>

      <div v-if="structured.important_items.length" class="rounded-2xl border bg-card shadow-sm">
        <div class="border-b px-5 py-3 text-sm font-medium">重要物品</div>
        <div class="flex flex-wrap gap-2 p-5">
          <span
            v-for="(item, i) in structured.important_items"
            :key="i"
            class="rounded-full border border-foreground/15 px-3 py-1 text-sm"
          >
            {{ item.name }}<span v-if="item.status" class="text-muted-foreground">（{{ item.status }}）</span>
          </span>
        </div>
      </div>

      <div v-if="structured.style_notes" class="rounded-2xl border bg-card shadow-sm">
        <div class="border-b px-5 py-3 text-sm font-medium">文风备注</div>
        <p class="p-5 text-sm leading-relaxed">{{ structured.style_notes }}</p>
      </div>
    </div>

    <!-- JSON 编辑视图 -->
    <div v-else class="rounded-2xl border bg-card shadow-sm">
      <div class="flex items-center gap-2 border-b px-5 py-3">
        <Braces class="size-4 text-primary" />
        <span class="text-sm font-medium">记忆内容（JSON）</span>
        <span
          v-if="isJsonValid"
          class="rounded-full bg-foreground/10 px-2 py-0.5 text-[11px] text-foreground"
        >
          JSON 合法
        </span>
        <span v-else class="rounded-full bg-destructive/10 px-2 py-0.5 text-[11px] text-destructive">JSON 格式有误</span>
        <span v-if="structured" class="ml-auto text-xs text-muted-foreground">当前为 v2 结构化记忆</span>
        <span v-else class="ml-auto text-xs text-muted-foreground">旧格式记忆，AI 更新时会自动迁移为 v2 结构</span>
      </div>
      <Textarea
        v-model="content"
        rows="16"
        class="resize-y rounded-none border-0 bg-transparent font-mono text-xs leading-relaxed focus-visible:ring-0"
        placeholder='{"schema":"v2","current_state":{...}}'
      />
    </div>
  </div>
</template>
