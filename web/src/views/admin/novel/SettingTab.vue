<script setup lang="ts">import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'

import { onMounted, ref } from 'vue'
import { Save, Sparkles } from '@lucide/vue'
import { toast } from 'vue-sonner'
import LoadingState from '@/components/common/LoadingState.vue'
import { fetchNovelSetting, saveNovelSetting } from '@/api'
import { usePollingTask } from '@/composables/useAiTask'
import type { NovelSetting } from '@/types/api'

const props = defineProps<{ novelId: number }>()

const loading = ref(true)
const saving = ref(false)
const form = ref({
  world_view: '',
  characters: '',
  factions: '',
  conflicts: '',
  main_plot: '',
  style: '',
})

const fields = [
  { key: 'world_view' as const, label: '世界背景', placeholder: '世界的地理、力量体系、时代背景…' },
  { key: 'characters' as const, label: '主要人物', placeholder: '每行一个人物：名字 / 身份 / 性格 / 动机…' },
  { key: 'factions' as const, label: '势力阵营', placeholder: '各方势力及其立场、关系…' },
  { key: 'conflicts' as const, label: '核心冲突', placeholder: '主线冲突与副线冲突…' },
  { key: 'main_plot' as const, label: '主线剧情', placeholder: '故事从哪开始、到哪里去…' },
  { key: 'style' as const, label: '文风要求', placeholder: '叙述视角、语言风格、节奏…' },
]

const { running, run } = usePollingTask()

async function load() {
  loading.value = true
  try {
    const s: NovelSetting = await fetchNovelSetting(props.novelId)
    form.value = {
      world_view: s.world_view || '',
      characters: s.characters || '',
      factions: s.factions || '',
      conflicts: s.conflicts || '',
      main_plot: s.main_plot || '',
      style: s.style || '',
    }
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    await saveNovelSetting(props.novelId, { ...form.value })
    toast.success('设定已保存')
  } catch {
    // 请求层已提示
  } finally {
    saving.value = false
  }
}

async function aiGenerate() {
  await run({
    taskType: 'generate_setting',
    novelId: props.novelId,
    onSuccess: () => load(),
  })
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-4 flex items-center justify-between">
      <p class="text-sm text-muted-foreground">为小说建立完整的设定库，供 AI 创作时参考</p>
      <Button class="gap-2" variant="secondary" :disabled="running" @click="aiGenerate">
        <Sparkles class="size-4" />
        {{ running ? 'AI 生成中…' : 'AI 生成设定' }}
      </Button>
    </div>

    <LoadingState v-if="loading" variant="table" :rows="3" />

    <div v-else class="space-y-4 rounded-2xl border bg-card p-6 shadow-sm">
      <div v-for="f in fields" :key="f.key" class="space-y-1.5">
        <Label>{{ f.label }}</Label>
        <Textarea
          v-model="form[f.key]"
          :rows="f.key === 'world_view' || f.key === 'main_plot' ? 3 : 4"
          :placeholder="f.placeholder"
          class="resize-y font-mono text-xs leading-relaxed"
        />
      </div>
      <div class="flex justify-end">
        <Button class="gap-2" :disabled="saving" @click="save">
          <Save class="size-4" />
          保存设定
        </Button>
      </div>
    </div>
  </div>
</template>
