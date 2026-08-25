<script setup lang="ts">import { Switch } from '@/components/ui/switch'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

import { ref } from 'vue'
import { Eye, EyeOff, CheckCircle2, Save } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { updateNovel } from '@/api'
import type { NovelDetail, NovelStatus } from '@/types/api'

const props = defineProps<{ novelId: number; novel: NovelDetail }>()
const emit = defineEmits<{ (e: 'saved'): void }>()

const form = ref({
  category_id: props.novel.category_id,
  title: props.novel.title,
  cover: props.novel.cover,
  description: props.novel.description,
  tags: props.novel.tags,
  status: props.novel.status as NovelStatus,
  is_public: props.novel.is_public as 0 | 1,
})

const saving = ref(false)

async function save() {
  if (!form.value.title.trim()) {
    toast.error('标题不能为空')
    return
  }
  saving.value = true
  try {
    await updateNovel(props.novelId, { ...form.value })
    toast.success('信息已保存')
    emit('saved')
  } catch {
    // 请求层已提示
  } finally {
    saving.value = false
  }
}

async function setStatus(status: NovelStatus, msg: string) {
  try {
    await updateNovel(props.novelId, { status })
    form.value.status = status
    toast.success(msg)
    emit('saved')
  } catch {
    // 请求层已提示
  }
}

async function togglePublic() {
  try {
    const v = form.value.is_public === 1 ? 0 : 1
    await updateNovel(props.novelId, { is_public: v })
    form.value.is_public = v
    toast.success(v ? '已公开' : '已隐藏')
    emit('saved')
  } catch {
    // 请求层已提示
  }
}
</script>

<template>
  <div class="grid gap-6 lg:grid-cols-[1fr_280px]">
    <!-- 编辑表单 -->
    <div class="space-y-4 rounded-2xl border bg-card p-6 shadow-sm">
      <div class="space-y-1.5">
        <Label>标题 <span class="text-destructive">*</span></Label>
        <Input v-model="form.title" />
      </div>
      <div class="space-y-1.5">
        <Label>封面 URL</Label>
        <Input v-model="form.cover" placeholder="留空则使用标题渐变封面" />
      </div>
      <div class="space-y-1.5">
        <Label>简介</Label>
        <Textarea v-model="form.description" rows="4" />
      </div>
      <div class="space-y-1.5">
        <Label>标签（逗号分隔）</Label>
        <Input v-model="form.tags" placeholder="例如：仙侠, 热血, 成长" />
      </div>
      <div class="space-y-1.5">
        <Label>分类 ID</Label>
        <Input v-model.number="form.category_id" type="number" />
      </div>
      <div class="flex justify-end">
        <Button class="gap-2" :disabled="saving" @click="save">
          <Save class="size-4" />
          保存信息
        </Button>
      </div>
    </div>

    <!-- 状态管理 -->
    <div class="space-y-4">
      <div class="rounded-2xl border bg-card p-5 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold">状态管理</h3>
        <div class="grid grid-cols-1 gap-2">
          <Button
            variant="outline"
            class="justify-start gap-2"
            :class="form.status === 'published' ? 'border-foreground/60 bg-foreground/10 text-foreground' : ''"
            @click="setStatus('published', '已发布')"
          >
            <Eye class="size-4" /> 发布（连载中）
          </Button>
          <Button
            variant="outline"
            class="justify-start gap-2"
            :class="form.status === 'finished' ? 'border-foreground/60 bg-foreground/10 text-foreground' : ''"
            @click="setStatus('finished', '已标记完结')"
          >
            <CheckCircle2 class="size-4" /> 标记完结
          </Button>
          <Button
            variant="outline"
            class="justify-start gap-2"
            :class="form.status === 'draft' ? 'border-foreground/60 bg-foreground/10 text-foreground' : ''"
            @click="setStatus('draft', '已存为草稿')"
          >
            <EyeOff class="size-4" /> 存为草稿
          </Button>
        </div>
        <div class="mt-4 flex items-center justify-between rounded-xl border p-3">
          <div>
            <p class="text-sm font-medium">是否公开</p>
            <p class="text-xs text-muted-foreground">{{ form.is_public ? '前台可见' : '仅后台可见' }}</p>
          </div>
          <Switch :checked="form.is_public === 1" @update:checked="togglePublic" />
        </div>
      </div>
    </div>
  </div>
</template>
