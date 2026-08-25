<script setup lang="ts">import { Textarea } from '@/components/ui/textarea'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Button } from '@/components/ui/button'

import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Lightbulb, MessageSquare, Plus, Trash2, LoaderCircle } from '@lucide/vue'
import { toast } from 'vue-sonner'
import PageHeader from '@/components/common/PageHeader.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PaginationBar from '@/components/common/PaginationBar.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import { createIdea, deleteIdea, fetchAdminCategories, fetchIdeas } from '@/api'
import { formatRelative } from '@/lib/format'
import type { Category, Idea, PageResult } from '@/types/api'

const router = useRouter()

const loading = ref(true)
const list = ref<PageResult<Idea>>({ list: [], total: 0, page: 1, page_size: 10 })
const categories = ref<Category[]>([])
const categoryId = ref('0')
const status = ref('all')

// 新建
const createOpen = ref(false)
const creating = ref(false)
const createForm = ref({ category_id: 0, title: '', content: '' })

// 删除
const deleting = ref<Idea | null>(null)
const deleteLoading = ref(false)

async function load() {
  loading.value = true
  try {
    list.value = await fetchIdeas({
      page: list.value.page,
      page_size: list.value.page_size,
      category_id: categoryId.value !== '0' ? categoryId.value : undefined,
      status: status.value !== 'all' ? status.value : undefined,
    })
  } finally {
    loading.value = false
  }
}

function openChat(idea: Idea) {
  router.push(`/admin/ideas/${idea.id}/chat`)
}

async function onCreate() {
  if (!createForm.value.title.trim()) {
    toast.error('请填写点子标题')
    return
  }
  creating.value = true
  try {
    const idea = await createIdea({ ...createForm.value })
    toast.success('点子已创建')
    createOpen.value = false
    createForm.value = { category_id: categories.value[0]?.id ?? 0, title: '', content: '' }
    // 直接进入聊天界面
    router.push(`/admin/ideas/${idea.id}/chat`)
  } catch {
    // 请求层已提示
  } finally {
    creating.value = false
  }
}

async function confirmDelete() {
  if (!deleting.value) return
  deleteLoading.value = true
  try {
    await deleteIdea(deleting.value.id)
    toast.success('点子已删除')
    deleting.value = null
    load()
  } catch {
    // 请求层已提示
  } finally {
    deleteLoading.value = false
  }
}

onMounted(async () => {
  categories.value = (await fetchAdminCategories()).list
  load()
})
</script>

<template>
  <div>
    <PageHeader title="AI 点子" description="收集灵感，与 AI 讨论完善，一键孵化成小说">
      <template #actions>
        <Button @click="createOpen = true">
          <Plus class="size-4" />
          新建点子
        </Button>
      </template>
    </PageHeader>

    <!-- 筛选 -->
    <div class="mb-5 flex flex-wrap items-center gap-2.5 rounded-2xl border bg-card p-3 shadow-sm">
      <Select v-model="categoryId" @update:model-value="() => { list.page = 1; load() }">
        <SelectTrigger class="h-9 w-36">
          <SelectValue placeholder="全部分类" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="0">全部分类</SelectItem>
          <SelectItem v-for="c in categories" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
        </SelectContent>
      </Select>
      <Select v-model="status" @update:model-value="() => { list.page = 1; load() }">
        <SelectTrigger class="h-9 w-32">
          <SelectValue placeholder="全部状态" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">全部状态</SelectItem>
          <SelectItem value="unused">未使用</SelectItem>
          <SelectItem value="used">已使用</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <LoadingState v-if="loading" variant="list" :rows="4" />

    <div v-else-if="list.list.length === 0" class="rounded-2xl border bg-card">
      <EmptyState
        :icon="Lightbulb"
        title="还没有点子"
        description="灵光一现？记下来，让 AI 帮你展开"
      >
        <Button size="sm" @click="createOpen = true">
          <Plus class="size-4" /> 新建点子
        </Button>
      </EmptyState>
    </div>

    <div v-else class="grid gap-3 sm:grid-cols-2">
      <div
        v-for="idea in list.list"
        :key="idea.id"
        class="group cursor-pointer rounded-2xl border bg-card p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
        @click="openChat(idea)"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="flex min-w-0 items-center gap-2.5">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-foreground/10 text-foreground">
              <Lightbulb class="size-4.5" />
            </span>
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold">{{ idea.title }}</p>
              <p class="mt-0.5 flex items-center gap-2 text-[11px] text-muted-foreground">
                <span class="rounded-full bg-accent px-1.5 py-px text-accent-foreground">{{ idea.category_name || '未分类' }}</span>
                <span>{{ formatRelative(idea.created_at) }}</span>
              </p>
            </div>
          </div>
          <span
            class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium"
            :class="idea.status === 'used' ? 'bg-foreground text-background' : 'bg-muted text-muted-foreground'"
          >
            {{ idea.status === 'used' ? '已使用' : '未使用' }}
          </span>
        </div>
        <p class="mt-3 line-clamp-2 text-xs leading-relaxed text-muted-foreground">
          {{ idea.content || '暂无内容，点击进入聊天完善' }}
        </p>
        <div class="mt-3 flex items-center justify-between border-t pt-2.5" @click.stop>
          <Button variant="ghost" size="sm" class="gap-1.5 text-primary" @click="openChat(idea)">
            <MessageSquare class="size-3.5" />
            进入聊天
          </Button>
          <div class="flex gap-0.5">
            <Button
              variant="ghost"
              size="icon"
              class="size-8 text-destructive"
              title="删除"
              @click="deleting = idea"
            >
              <Trash2 class="size-4" />
            </Button>
          </div>
        </div>
      </div>
    </div>

    <PaginationBar
      v-if="!loading && list.total > 0"
      v-model:page="list.page"
      :page-size="list.page_size"
      :total="list.total"
      @update:page="load"
    />

    <!-- 新建点子 -->
    <Dialog v-model:open="createOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>新建点子</DialogTitle>
          <DialogDescription>记录灵感，创建后立即进入 AI 聊天完善</DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-2">
          <div class="space-y-1.5">
            <Label>分类</Label>
            <Select v-model="createForm.category_id">
              <SelectTrigger>
                <SelectValue placeholder="选择分类" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="space-y-1.5">
            <Label>标题 <span class="text-destructive">*</span></Label>
            <Input v-model="createForm.title" placeholder="一句话概括你的灵感" />
          </div>
          <div class="space-y-1.5">
            <Label>内容</Label>
            <Textarea v-model="createForm.content" rows="4" placeholder="详细描述你的想法（可选）" />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="createOpen = false">取消</Button>
          <Button :disabled="creating" @click="onCreate">
            <LoaderCircle v-if="creating" class="size-4 animate-spin" />
            创建并聊天
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <ConfirmDialog
      :open="!!deleting"
      title="删除点子"
      :description="`确定要删除「${deleting?.title ?? ''}」吗？聊天记录将一并删除。`"
      confirm-text="删除"
      :loading="deleteLoading"
      @update:open="(v: boolean) => !v && (deleting = null)"
      @confirm="confirmDelete"
    />
  </div>
</template>
