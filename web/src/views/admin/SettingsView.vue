<script setup lang="ts">import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

import { onMounted, ref } from 'vue'
import { Save } from '@lucide/vue'
import { toast } from 'vue-sonner'
import PageHeader from '@/components/common/PageHeader.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import { fetchConfig, saveConfig } from '@/api'
import type { SystemConfig } from '@/types/api'

const loading = ref(true)
const saving = ref(false)
const form = ref<SystemConfig>({})

const fields: { key: string; label: string; type: 'text' | 'number'; placeholder: string; hint: string; step?: number }[] = [
  { key: 'site_name', label: '站点名称', type: 'text', placeholder: '拾光小说', hint: '前台导航与页脚展示的站点名' },
  { key: 'ai_temperature', label: 'AI 温度', type: 'number', placeholder: '0.8', hint: '创作随机性，0-2，越高越发散', step: 0.1 },
  { key: 'ai_http_timeout', label: 'AI HTTP 超时（秒）', type: 'number', placeholder: '120', hint: '调用模型接口的超时上限', step: 1 },
  { key: 'context_max_recent_chapters', label: '最近章节数', type: 'number', placeholder: '3', hint: '生成章节时带入的最近章节数量', step: 1 },
  { key: 'context_summary_max_chars', label: '摘要字符上限', type: 'number', placeholder: '300', hint: '上下文摘要的最大字符数', step: 1 },
  { key: 'chapter_target_words', label: '章节目标字数', type: 'number', placeholder: '3000', hint: 'AI 生成章节的默认目标字数', step: 1 },
]

async function load() {
  loading.value = true
  try {
    form.value = await fetchConfig()
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    await saveConfig({ ...form.value })
    toast.success('系统设置已保存')
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
    <PageHeader title="系统设置" description="配置站点与 AI 创作参数" />

    <LoadingState v-if="loading" variant="table" :rows="3" />

    <div v-else class="max-w-2xl space-y-4 rounded-2xl border bg-card p-6 shadow-sm">
      <div v-for="f in fields" :key="f.key" class="space-y-1.5">
        <Label>{{ f.label }}</Label>
        <Input
          :type="f.type"
          :step="f.step"
          v-model="form[f.key]"
          :placeholder="f.placeholder"
        />
        <p class="text-xs text-muted-foreground">{{ f.hint }}</p>
      </div>
      <div class="flex justify-end border-t pt-4">
        <Button class="gap-2" :disabled="saving" @click="save">
          <Save class="size-4" />
          保存设置
        </Button>
      </div>
    </div>
  </div>
</template>
