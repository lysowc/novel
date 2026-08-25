<script setup lang="ts">import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'

import { onMounted, ref } from 'vue'
import { FolderPlus, Plus, Save, Sparkles, Trash2 } from '@lucide/vue'
import { toast } from 'vue-sonner'
import LoadingState from '@/components/common/LoadingState.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import { fetchNovelOutline, saveNovelOutline } from '@/api'
import { usePollingTask } from '@/composables/useAiTask'
import { parseOutline } from '@/lib/format'
import type { Outline } from '@/types/api'

const props = defineProps<{ novelId: number }>()

const loading = ref(true)
const saving = ref(false)
const outline = ref<Outline>({ volumes: [] })

const { running, run } = usePollingTask()

async function load() {
  loading.value = true
  try {
    const res = await fetchNovelOutline(props.novelId)
    outline.value = parseOutline(res.outline)
  } finally {
    loading.value = false
  }
}

function addVolume() {
  outline.value.volumes.push({
    title: `第 ${outline.value.volumes.length + 1} 卷`,
    chapters: [],
  })
}

function removeVolume(idx: number) {
  outline.value.volumes.splice(idx, 1)
}

function addChapter(vIdx: number) {
  outline.value.volumes[vIdx].chapters.push({
    no: outline.value.volumes[vIdx].chapters.length + 1,
    title: '',
    summary: '',
  })
}

function removeChapter(vIdx: number, cIdx: number) {
  outline.value.volumes[vIdx].chapters.splice(cIdx, 1)
}

/** 保存前重排章节号（跨卷连续） */
function normalize() {
  let no = 1
  for (const v of outline.value.volumes) {
    for (const c of v.chapters) {
      c.no = no++
    }
  }
}

async function save() {
  normalize()
  saving.value = true
  try {
    await saveNovelOutline(props.novelId, JSON.stringify(outline.value))
    toast.success('大纲已保存')
  } catch {
    // 请求层已提示
  } finally {
    saving.value = false
  }
}

async function aiGenerate() {
  await run({
    taskType: 'generate_outline',
    novelId: props.novelId,
    onSuccess: () => {
      load()
      toast.success('大纲已生成')
    },
  })
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
      <p class="text-sm text-muted-foreground">按「卷 → 章」组织故事结构，保存为结构化 JSON</p>
      <div class="flex gap-2">
        <Button variant="secondary" class="gap-2" :disabled="running" @click="aiGenerate">
          <Sparkles class="size-4" />
          {{ running ? 'AI 生成中…' : 'AI 生成大纲' }}
        </Button>
        <Button variant="outline" class="gap-2" @click="addVolume">
          <FolderPlus class="size-4" />
          新增卷
        </Button>
        <Button class="gap-2" :disabled="saving" @click="save">
          <Save class="size-4" />
          保存大纲
        </Button>
      </div>
    </div>

    <LoadingState v-if="loading" variant="list" :rows="2" />

    <div v-else class="space-y-5">
      <EmptyState
        v-if="outline.volumes.length === 0"
        title="还没有大纲"
        description="点击「AI 生成大纲」或手动添加卷与章节"
      >
        <Button size="sm" class="gap-2" @click="addVolume">
          <Plus class="size-4" /> 新增卷
        </Button>
      </EmptyState>

      <div v-for="(v, vIdx) in outline.volumes" :key="vIdx" class="rounded-2xl border bg-card shadow-sm">
        <div class="flex items-center gap-3 border-b px-5 py-3">
          <Input v-model="v.title" class="h-9 max-w-sm font-semibold" placeholder="卷标题，如：第一卷 风起" />
          <Button
            variant="ghost"
            size="icon"
            class="ml-auto size-8 text-destructive"
            title="删除本卷"
            @click="removeVolume(vIdx)"
          >
            <Trash2 class="size-4" />
          </Button>
        </div>
        <div class="space-y-2 p-4">
          <div
            v-for="(c, cIdx) in v.chapters"
            :key="cIdx"
            class="flex flex-col gap-2 rounded-xl border bg-background/60 p-3 sm:flex-row sm:items-center"
          >
            <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-accent text-xs font-semibold text-accent-foreground">
              {{ cIdx + 1 }}
            </span>
            <Input v-model="c.title" class="h-9 sm:w-56" placeholder="章节标题" />
            <Input v-model="c.summary" class="h-9 flex-1" placeholder="本章概要（AI 生成章节时参考）" />
            <Button
              variant="ghost"
              size="icon"
              class="size-8 shrink-0 text-destructive"
              title="删除章节"
              @click="removeChapter(vIdx, cIdx)"
            >
              <Trash2 class="size-4" />
            </Button>
          </div>
          <Button variant="outline" size="sm" class="gap-2" @click="addChapter(vIdx)">
            <Plus class="size-4" />
            添加章节
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
