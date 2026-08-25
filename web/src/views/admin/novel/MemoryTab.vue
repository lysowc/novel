<script setup lang="ts">import { Textarea } from '@/components/ui/textarea'
import { Button } from '@/components/ui/button'

import { computed, onMounted, ref } from 'vue'
import { Braces, Save, Sparkles } from '@lucide/vue'
import { toast } from 'vue-sonner'
import LoadingState from '@/components/common/LoadingState.vue'
import { fetchNovelMemory, saveNovelMemory } from '@/api'
import { usePollingTask } from '@/composables/useAiTask'
import type { Memory } from '@/types/api'

const props = defineProps<{ novelId: number }>()

const loading = ref(true)
const saving = ref(false)
const content = ref('[]')
const updatedAt = ref('')

const { running, run } = usePollingTask()

const parsed = computed(() => {
  try {
    const arr = JSON.parse(content.value)
    return Array.isArray(arr) ? arr : null
  } catch {
    return null
  }
})

async function load() {
  loading.value = true
  try {
    const m: Memory = await fetchNovelMemory(props.novelId)
    content.value = m.content || '[]'
    updatedAt.value = m.updated_at
  } finally {
    loading.value = false
  }
}

async function save() {
  if (parsed.value === null) {
    toast.error('记忆内容不是合法的 JSON 数组')
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
        长期记忆以 JSON 数组保存，AI 创作时自动带入
        <span v-if="updatedAt" class="ml-2 text-xs">最后更新：{{ updatedAt.slice(0, 16) }}</span>
      </p>
      <div class="flex gap-2">
        <Button variant="secondary" class="gap-2" :disabled="running" @click="aiUpdate">
          <Sparkles class="size-4" />
          {{ running ? 'AI 更新中…' : 'AI 更新记忆' }}
        </Button>
        <Button class="gap-2" :disabled="saving" @click="save">
          <Save class="size-4" />
          保存记忆
        </Button>
      </div>
    </div>

    <LoadingState v-if="loading" variant="table" :rows="2" />

    <div v-else class="rounded-2xl border bg-card shadow-sm">
      <div class="flex items-center gap-2 border-b px-5 py-3">
        <Braces class="size-4 text-primary" />
        <span class="text-sm font-medium">记忆内容</span>
        <span
          v-if="parsed !== null"
          class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-[11px] text-emerald-600 dark:text-emerald-400"
        >
          JSON 合法 · {{ parsed.length }} 条
        </span>
        <span v-else class="rounded-full bg-destructive/10 px-2 py-0.5 text-[11px] text-destructive">JSON 格式有误</span>
      </div>
      <Textarea
        v-model="content"
        rows="14"
        class="resize-y rounded-none border-0 bg-transparent font-mono text-xs leading-relaxed focus-visible:ring-0"
        placeholder='[{"key":"记忆条目","value":"记忆内容"}]'
      />
    </div>
  </div>
</template>
