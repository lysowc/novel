<script setup lang="ts">import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'

import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Eye, Plus, Pencil, Sparkles, Trash2, Search, LoaderCircle } from '@lucide/vue'
import { toast } from 'vue-sonner'
import PageHeader from '@/components/common/PageHeader.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PaginationBar from '@/components/common/PaginationBar.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import StreamDialog from '@/components/common/StreamDialog.vue'
import CoverArt from '@/components/common/CoverArt.vue'
import {
  createNovel, deleteNovel, fetchAdminCategories, fetchAdminNovels, updateNovel,
} from '@/api'
import { formatNumber, formatRelative } from '@/lib/format'
import type { Category, Novel, PageResult, NovelStatus } from '@/types/api'

const router = useRouter()

const loading = ref(true)
const list = ref<PageResult<Novel>>({ list: [], total: 0, page: 1, page_size: 10 })
const categories = ref<Category[]>([])

const keyword = ref('')
const categoryId = ref<string>('0')
const status = ref<string>('all')

// 新建/编辑
const formOpen = ref(false)
const editing = ref<Novel | null>(null)
const saving = ref(false)
const form = ref({
  category_id: 0,
  title: '',
  description: '',
  tags: '',
  cover: '',
  status: 'draft' as NovelStatus,
  is_public: 0 as 0 | 1,
})

// 删除
const deleting = ref<Novel | null>(null)
const deleteLoading = ref(false)

// AI 续写
const aiNovel = ref<Novel | null>(null)
const streamOpen = ref(false)

async function load() {
  loading.value = true
  try {
    list.value = await fetchAdminNovels({
      page: list.value.page,
      page_size: list.value.page_size,
      keyword: keyword.value || undefined,
      category_id: categoryId.value !== '0' ? categoryId.value : undefined,
      status: status.value !== 'all' ? status.value : undefined,
    })
  } finally {
    loading.value = false
  }
}

function onSearch() {
  list.value.page = 1
  load()
}

function openCreate() {
  editing.value = null
  form.value = {
    category_id: categories.value[0]?.id ?? 0,
    title: '',
    description: '',
    tags: '',
    cover: '',
    status: 'draft',
    is_public: 0,
  }
  formOpen.value = true
}

function openEdit(n: Novel) {
  editing.value = n
  form.value = {
    category_id: n.category_id,
    title: n.title,
    description: n.description,
    tags: n.tags,
    cover: n.cover,
    status: n.status,
    is_public: n.is_public,
  }
  formOpen.value = true
}

async function save() {
  if (!form.value.title.trim()) {
    toast.error('请填写小说标题')
    return
  }
  saving.value = true
  try {
    if (editing.value) {
      await updateNovel(editing.value.id, { ...form.value })
      toast.success('小说已更新')
    } else {
      await createNovel({ ...form.value })
      toast.success('小说已创建')
    }
    formOpen.value = false
    load()
  } catch {
    // 请求层已提示
  } finally {
    saving.value = false
  }
}

async function confirmDelete() {
  if (!deleting.value) return
  deleteLoading.value = true
  try {
    await deleteNovel(deleting.value.id)
    toast.success('小说已删除')
    deleting.value = null
    load()
  } catch {
    // 请求层已提示
  } finally {
    deleteLoading.value = false
  }
}

function openAi(n: Novel) {
  aiNovel.value = n
  streamOpen.value = true
}

const statusMap: Record<NovelStatus, { label: string; cls: string }> = {
  draft: { label: '草稿', cls: 'bg-muted text-muted-foreground' },
  published: { label: '连载中', cls: 'bg-foreground text-background' },
  finished: { label: '已完结', cls: 'border border-foreground/25 text-muted-foreground' },
}

onMounted(async () => {
  categories.value = (await fetchAdminCategories()).list
  load()
})
</script>

<template>
  <div>
    <PageHeader title="小说管理" description="管理你的全部小说作品">
      <template #actions>
        <Button @click="openCreate">
          <Plus class="size-4" />
          新建小说
        </Button>
      </template>
    </PageHeader>

    <!-- 筛选 -->
    <div class="mb-5 flex flex-wrap items-center gap-2.5 rounded-2xl border bg-card p-3 shadow-sm">
      <div class="relative min-w-52 flex-1">
        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="keyword"
          class="h-9 pl-9"
          placeholder="搜索书名 / 标签"
          @keyup.enter="onSearch"
        />
      </div>
      <Select v-model="categoryId">
        <SelectTrigger class="h-9 w-36">
          <SelectValue placeholder="全部分类" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="0">全部分类</SelectItem>
          <SelectItem v-for="c in categories" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
        </SelectContent>
      </Select>
      <Select v-model="status">
        <SelectTrigger class="h-9 w-32">
          <SelectValue placeholder="全部状态" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">全部状态</SelectItem>
          <SelectItem value="draft">草稿</SelectItem>
          <SelectItem value="published">连载中</SelectItem>
          <SelectItem value="finished">已完结</SelectItem>
        </SelectContent>
      </Select>
      <Button variant="secondary" class="h-9" @click="onSearch">查询</Button>
    </div>

    <LoadingState v-if="loading" variant="table" :rows="6" />

    <div v-else-if="list.list.length === 0" class="rounded-2xl border bg-card">
      <EmptyState title="没有找到小说" description="试试调整筛选条件，或新建一本">
        <Button variant="outline" size="sm" @click="openCreate">
          <Plus class="size-4" /> 新建小说
        </Button>
      </EmptyState>
    </div>

    <template v-else>
      <div class="overflow-hidden rounded-2xl border bg-card shadow-sm">
        <Table>
          <TableHeader>
            <TableRow class="hover:bg-transparent">
              <TableHead class="w-14">封面</TableHead>
              <TableHead>标题</TableHead>
              <TableHead class="hidden md:table-cell">分类</TableHead>
              <TableHead>状态</TableHead>
              <TableHead class="hidden sm:table-cell">字数</TableHead>
              <TableHead class="hidden lg:table-cell">章节</TableHead>
              <TableHead class="hidden lg:table-cell">更新</TableHead>
              <TableHead class="text-right">操作</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="n in list.list" :key="n.id" class="group">
              <TableCell>
                <CoverArt :title="n.title" :cover="n.cover" aspect="portrait" title-size="sm" class="w-10" />
              </TableCell>
              <TableCell>
                <p class="line-clamp-1 font-medium">{{ n.title }}</p>
                <p class="mt-0.5 line-clamp-1 text-xs text-muted-foreground">{{ n.tags || '无标签' }}</p>
              </TableCell>
              <TableCell class="hidden md:table-cell">
                <span class="rounded-full bg-accent px-2 py-0.5 text-xs text-accent-foreground">
                  {{ n.category_name || '未分类' }}
                </span>
              </TableCell>
              <TableCell>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-medium" :class="statusMap[n.status]?.cls">
                  {{ statusMap[n.status]?.label }}
                </span>
                <span v-if="!n.is_public" class="ml-1 rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground">私密</span>
              </TableCell>
              <TableCell class="hidden sm:table-cell">{{ formatNumber(n.word_count) }}</TableCell>
              <TableCell class="hidden lg:table-cell">{{ n.chapter_count }}</TableCell>
              <TableCell class="hidden lg:table-cell text-xs text-muted-foreground">{{ formatRelative(n.updated_at) }}</TableCell>
              <TableCell class="text-right">
                <div class="flex justify-end gap-1 opacity-100 transition-opacity lg:opacity-0 lg:group-hover:opacity-100">
                  <Button variant="ghost" size="icon" class="size-8" title="进入详情" @click="router.push(`/admin/novels/${n.id}`)">
                    <Eye class="size-4" />
                  </Button>
                  <Button variant="ghost" size="icon" class="size-8 text-primary" title="AI 续写" @click="openAi(n)">
                    <Sparkles class="size-4" />
                  </Button>
                  <Button variant="ghost" size="icon" class="size-8" title="编辑" @click="openEdit(n)">
                    <Pencil class="size-4" />
                  </Button>
                  <Button variant="ghost" size="icon" class="size-8 text-destructive" title="删除" @click="deleting = n">
                    <Trash2 class="size-4" />
                  </Button>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
      <PaginationBar
        v-model:page="list.page"
        :page-size="list.page_size"
        :total="list.total"
        @update:page="load"
      />
    </template>

    <!-- 新建/编辑对话框 -->
    <Dialog v-model:open="formOpen">
      <DialogContent class="max-h-[88vh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{{ editing ? '编辑小说' : '新建小说' }}</DialogTitle>
          <DialogDescription>{{ editing ? '修改小说基本信息' : '创建一本新小说' }}</DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-2">
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <Label>分类</Label>
              <Select v-model="form.category_id">
                <SelectTrigger>
                  <SelectValue placeholder="选择分类" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-1.5">
              <Label>状态</Label>
              <Select v-model="form.status">
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="draft">草稿</SelectItem>
                  <SelectItem value="published">连载中</SelectItem>
                  <SelectItem value="finished">已完结</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <div class="space-y-1.5">
            <Label>标题 <span class="text-destructive">*</span></Label>
            <Input v-model="form.title" placeholder="请输入小说标题" />
          </div>
          <div class="space-y-1.5">
            <Label>封面 URL</Label>
            <Input v-model="form.cover" placeholder="留空则按标题生成渐变封面" />
          </div>
          <div class="space-y-1.5">
            <Label>简介</Label>
            <Textarea v-model="form.description" rows="3" placeholder="用几句话介绍这部小说" />
          </div>
          <div class="space-y-1.5">
            <Label>标签（逗号分隔）</Label>
            <Input v-model="form.tags" placeholder="例如：仙侠, 热血, 成长" />
          </div>
          <div class="flex items-center justify-between rounded-xl border p-3">
            <div>
              <p class="text-sm font-medium">公开可见</p>
              <p class="text-xs text-muted-foreground">关闭后仅后台可见</p>
            </div>
            <Switch v-model:checked="form.is_public" />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="formOpen = false">取消</Button>
          <Button :disabled="saving" @click="save">
            <LoaderCircle v-if="saving" class="size-4 animate-spin" />
            保存
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- 删除确认 -->
    <ConfirmDialog
      :open="!!deleting"
      title="删除小说"
      :description="`确定要删除《${deleting?.title ?? ''}》吗？相关章节将一并删除，此操作无法撤销。`"
      confirm-text="删除"
      :loading="deleteLoading"
      @update:open="(v: boolean) => !v && (deleting = null)"
      @confirm="confirmDelete"
    />

    <!-- AI 续写 -->
    <StreamDialog
      v-if="aiNovel"
      v-model:open="streamOpen"
      :title="`AI 续写 · ${aiNovel.title}`"
      :task-type="aiNovel.chapter_count > 0 ? 'continue_chapter' : 'generate_chapter'"
      :novel-id="aiNovel.id"
      @done="() => { toast.success('AI 创作完成'); load() }"
      @update:open="(v: boolean) => { if (!v) { aiNovel = null } }"
    />
  </div>
</template>
