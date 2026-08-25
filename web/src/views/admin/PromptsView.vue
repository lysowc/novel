<script setup lang="ts">import { Textarea } from '@/components/ui/textarea'
import { Button } from '@/components/ui/button'

import { computed, onMounted, ref } from 'vue'
import { Save, ScrollText } from '@lucide/vue'
import { toast } from 'vue-sonner'
import PageHeader from '@/components/common/PageHeader.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import { fetchPrompts, updatePrompt } from '@/api'
import { cn } from '@/lib/utils'
import type { Prompt } from '@/types/api'

const loading = ref(true)
const saving = ref(false)
const prompts = ref<Prompt[]>([])
const activeType = ref('')

const active = computed(() => prompts.value.find((p) => p.type === activeType.value) ?? null)
const content = ref('')

const PLACEHOLDERS = [
  '{{title}} 小说标题',
  '{{description}} 小说简介',
  '{{category}} 分类',
  '{{tags}} 标签',
  '{{setting}} 小说设定',
  '{{memory}} 长期记忆',
  '{{chapter_title}} 章节标题',
  '{{summary}} 本章概要',
  '{{content}} 正文内容',
  '{{tail}} 正文末尾',
  '{{target_words}} 目标字数',
]

async function load() {
  loading.value = true
  try {
    prompts.value = await fetchPrompts()
    if (prompts.value.length > 0) {
      activeType.value = prompts.value[0].type
      content.value = prompts.value[0].content
    }
  } finally {
    loading.value = false
  }
}

function select(p: Prompt) {
  activeType.value = p.type
  content.value = p.content
}

async function save() {
  if (!active.value) return
  saving.value = true
  try {
    const updated = await updatePrompt(active.value.id, content.value)
    active.value.content = updated.content
    active.value.updated_at = updated.updated_at
    toast.success('Prompt 已保存')
  } catch {
    // 请求层已提示
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Prompt 管理" description="定制 AI 各场景使用的提示词模板" />

    <LoadingState v-if="loading" variant="list" :rows="3" />

    <div v-else class="grid gap-5 lg:grid-cols-[280px_1fr]">
      <!-- 类型列表 -->
      <div class="h-fit space-y-1.5 rounded-2xl border bg-card p-3 shadow-sm">
        <p class="flex items-center gap-2 px-2 pb-2 pt-1 text-xs font-medium text-muted-foreground">
          <ScrollText class="size-3.5" />
          共 {{ prompts.length }} 类场景
        </p>
        <button
          v-for="p in prompts"
          :key="p.type"
          class="w-full rounded-xl border p-3 text-left transition-all hover:border-primary/30"
          :class="cn(p.type === activeType && 'border-primary/40 bg-accent/60')"
          @click="select(p)"
        >
          <p class="text-sm font-semibold" :class="p.type === activeType ? 'text-primary' : ''">{{ p.name }}</p>
          <p class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">{{ p.description }}</p>
          <p class="mt-1.5 text-[10px] text-muted-foreground/60">
            {{ p.type }} · {{ p.updated_at?.slice(0, 10) }}
          </p>
        </button>
      </div>

      <!-- 编辑器 -->
      <div v-if="active" class="rounded-2xl border bg-card shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-3.5">
          <div>
            <p class="text-sm font-semibold">{{ active.name }}</p>
            <p class="text-xs text-muted-foreground">{{ active.description }}</p>
          </div>
          <Button class="gap-2" :disabled="saving" @click="save">
            <Save class="size-4" />
            保存
          </Button>
        </div>
        <div class="grid gap-4 p-5 lg:grid-cols-[1fr_220px]">
          <Textarea
            v-model="content"
            rows="20"
            class="resize-y font-mono text-xs leading-relaxed"
            placeholder="请输入 Prompt 内容…"
          />
          <aside class="h-fit rounded-xl border bg-muted/40 p-3">
            <p class="text-xs font-semibold">可用占位符</p>
            <ul class="mt-2 space-y-1.5">
              <li v-for="ph in PLACEHOLDERS" :key="ph" class="rounded-md bg-background px-2 py-1 font-mono text-[11px] text-muted-foreground">
                {{ ph }}
              </li>
            </ul>
            <p class="mt-3 text-[11px] leading-relaxed text-muted-foreground">
              AI 调用时会将占位符替换为对应上下文。
            </p>
          </aside>
        </div>
      </div>
    </div>
  </div>
</template>
