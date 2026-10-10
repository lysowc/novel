<script setup lang="ts">import { Textarea } from '@/components/ui/textarea'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Button } from '@/components/ui/button'

import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Eye, FilePlus2, LoaderCircle, Pencil, Plus, RefreshCw, Sparkles, Trash2, Wand2 } from '@lucide/vue'
import { toast } from 'vue-sonner'
import LoadingState from '@/components/common/LoadingState.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import StreamDialog from '@/components/common/StreamDialog.vue'
import {
  createChapter, deleteChapter, fetchAdminChapters, fetchChapter, fetchTasks, updateChapter,
} from '@/api'
import { countWords, formatNumber, formatRelative } from '@/lib/format'
import type { AiTask, Chapter } from '@/types/api'

const props = defineProps<{ novelId: number }>()

const loading = ref(true)
const chapters = ref<Chapter[]>([])

// 编辑/新增章节对话框
const editOpen = ref(false)
const editing = ref<Chapter | null>(null)
const saving = ref(false)
const chapterForm = ref({ title: '', content: '', summary: '' })

// 删除
const deleting = ref<Chapter | null>(null)
const deleteLoading = ref(false)

// AI 流式任务
const aiOpen = ref(false)
const aiTask = ref<{ type: 'generate_chapter' | 'continue_chapter' | 'regenerate_chapter' | 'generate_summary'; no?: number; mode: 'stream' | 'poll'; remaining?: number } | null>(null)
const aiTitle = ref('')

// 连续续写
const batchCount = ref(5)

// 查看章节内容（只读）
const viewOpen = ref(false)
const viewing = ref<Chapter | null>(null)

// 进行中的任务（查看进度）
const runningTask = ref<AiTask | null>(null)
const progressOpen = ref(false)

const latestNo = computed(() =>
  chapters.value.length ? Math.max(...chapters.value.map((c) => c.chapter_no)) : 0,
)

async function load() {
  loading.value = true
  try {
    chapters.value = await fetchAdminChapters(props.novelId)
    await loadRunningTask()
  } finally {
    loading.value = false
  }
}

async function loadRunningTask() {
  try {
    const res = await fetchTasks({ novel_id: props.novelId, page_size: 20 })
    runningTask.value = res.list.find((t) => t.status === 'pending' || t.status === 'running') ?? null
  } catch {
    runningTask.value = null
  }
}

// 轮询进行中任务，让横幅随任务开始/结束自动出现、消失
let pollTimer: ReturnType<typeof setInterval> | null = null
function startPolling() {
  stopPolling()
  pollTimer = setInterval(loadRunningTask, 3000)
}
function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

function openCreate() {
  editing.value = null
  chapterForm.value = { title: `第 ${latestNo.value + 1} 章`, content: '', summary: '' }
  editOpen.value = true
}

async function openEdit(c: Chapter) {
  try {
    const full = await fetchChapter(c.id)
    editing.value = full
    chapterForm.value = { title: full.title, content: full.content, summary: full.summary || '' }
    editOpen.value = true
  } catch {
    // 请求层已提示
  }
}

async function openView(c: Chapter) {
  viewing.value = c
  viewOpen.value = true
  try {
    viewing.value = await fetchChapter(c.id)
  } catch {
    // 请求层已提示
  }
}

async function saveChapter() {
  if (!chapterForm.value.title.trim()) {
    toast.error('请填写章节标题')
    return
  }
  if (!chapterForm.value.content.trim()) {
    toast.error('章节内容不能为空')
    return
  }
  saving.value = true
  try {
    if (editing.value) {
      await updateChapter(editing.value.id, {
        title: chapterForm.value.title,
        content: chapterForm.value.content,
        summary: chapterForm.value.summary,
      })
      toast.success('章节已更新')
    } else {
      await createChapter(props.novelId, { ...chapterForm.value })
      toast.success('章节已创建')
    }
    editOpen.value = false
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
    await deleteChapter(deleting.value.id)
    toast.success('章节已删除')
    deleting.value = null
    load()
  } catch {
    // 请求层已提示
  } finally {
    deleteLoading.value = false
  }
}

/** 若已有任务在跑，则打开进度回放并返回 true（阻止另起任务） */
function openProgressIfRunning(): boolean {
  if (runningTask.value) {
    toast.info('已有任务进行中，为你打开进度查看')
    progressOpen.value = true
    return true
  }
  return false
}

function startAi(type: 'generate_chapter' | 'continue_chapter' | 'regenerate_chapter' | 'generate_summary', c?: Chapter) {
  if (openProgressIfRunning()) return
  // generate_chapter 表示"生成下一章"，章号 = 当前最大章号 + 1
  const no = type === 'generate_chapter' && !c
    ? latestNo.value + 1
    : (c?.chapter_no ?? latestNo.value)
  if ((type === 'continue_chapter' || type === 'regenerate_chapter' || type === 'generate_summary') && !no) {
    toast.error('还没有章节，请先创建或生成第一章')
    return
  }
  aiTask.value = { type, no: no || undefined, mode: type === 'generate_summary' ? 'poll' : 'stream' }
  aiTitle.value =
    type === 'generate_chapter' ? 'AI 生成章节'
      : type === 'continue_chapter' ? `AI 续写 · 第 ${no} 章之后`
      : type === 'regenerate_chapter' ? `AI 重新生成 · 第 ${no} 章`
      : `AI 生成摘要 · 第 ${no} 章`
  aiOpen.value = true
}

function aiParams() {
  const p: Record<string, unknown> = {}
  if (aiTask.value?.no) p.chapter_no = aiTask.value.no
  if (aiTask.value?.type === 'generate_chapter') p.target_words = 3000
  if (aiTask.value?.type === 'continue_chapter') p.target_words = 3000
  if (aiTask.value?.remaining) p.remaining = aiTask.value.remaining
  return p
}

/** 连续续写 N 章（首章流式展示，后续自动排队） */
function startBatch() {
  if (openProgressIfRunning()) return
  if (chapters.value.length === 0) {
    toast.error('还没有章节，请先创建或生成第一章')
    return
  }
  const n = Math.max(1, batchCount.value)
  aiTask.value = { type: 'continue_chapter', mode: 'stream', remaining: n - 1 }
  aiTitle.value = `连续续写 · 共 ${n} 章`
  aiOpen.value = true
}

function openProgress() {
  progressOpen.value = true
}

onMounted(() => {
  load()
  startPolling()
})
onBeforeUnmount(stopPolling)
</script>

<template>
  <div>
    <!-- 操作栏 -->
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
      <p class="text-sm text-muted-foreground">共 {{ chapters.length }} 章</p>
      <div class="flex flex-wrap items-center gap-2">
        <Button class="gap-2" :disabled="chapters.length === 0" @click="startAi('continue_chapter')">
          <Wand2 class="size-4" />
          AI 续写
        </Button>
        <div v-if="chapters.length > 0" class="flex items-center gap-1.5">
          <select
            v-model.number="batchCount"
            class="h-9 rounded-lg border bg-background px-2 text-sm text-muted-foreground"
          >
            <option :value="3">3 章</option>
            <option :value="5">5 章</option>
            <option :value="10">10 章</option>
          </select>
          <Button variant="secondary" class="gap-2" @click="startBatch">
            <Sparkles class="size-4" />
            连续续写
          </Button>
        </div>
        <Button variant="outline" class="gap-2" @click="startAi('generate_chapter')">
          <Sparkles class="size-4" />
          AI 生成新章节
        </Button>
        <Button variant="secondary" class="gap-2" @click="openCreate">
          <FilePlus2 class="size-4" />
          新增章节
        </Button>
      </div>
    </div>

    <!-- 进行中任务横幅 -->
    <div
      v-if="runningTask"
      class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-primary/30 bg-primary/5 px-4 py-2.5"
    >
      <LoaderCircle class="size-4 animate-spin text-primary" />
      <p class="min-w-0 flex-1 text-sm">
        有任务进行中：<span class="font-medium">{{ runningTask.task_type_text || runningTask.task_type }}</span>
        <span class="text-muted-foreground">（任务 #{{ runningTask.id }}）</span>
      </p>
      <Button size="sm" variant="outline" class="gap-1.5" @click="openProgress">
        <Eye class="size-3.5" />
        查看进度
      </Button>
    </div>

    <LoadingState v-if="loading" variant="table" :rows="5" />

    <div v-else-if="chapters.length === 0" class="rounded-2xl border bg-card">
      <EmptyState title="还没有章节" description="用 AI 生成或手动添加第一章吧">
        <div class="flex gap-2">
          <Button size="sm" class="gap-2" @click="startAi('generate_chapter')">
            <Sparkles class="size-4" /> AI 生成
          </Button>
          <Button size="sm" variant="outline" class="gap-2" @click="openCreate">
            <Plus class="size-4" /> 手动添加
          </Button>
        </div>
      </EmptyState>
    </div>

    <div v-else class="overflow-hidden rounded-2xl border bg-card shadow-sm">
      <Table>
        <TableHeader>
          <TableRow class="hover:bg-transparent">
            <TableHead class="w-16">章号</TableHead>
            <TableHead>标题</TableHead>
            <TableHead class="hidden sm:table-cell">字数</TableHead>
            <TableHead class="hidden md:table-cell">摘要</TableHead>
            <TableHead class="hidden lg:table-cell">更新时间</TableHead>
            <TableHead class="text-right">操作</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="c in chapters" :key="c.id" class="group">
            <TableCell class="font-medium">{{ c.chapter_no }}</TableCell>
            <TableCell>
              <p class="line-clamp-1 font-medium">{{ c.title }}</p>
              <p class="text-[11px] text-muted-foreground">{{ countWords(c.content) }} 字</p>
            </TableCell>
            <TableCell class="hidden sm:table-cell">{{ formatNumber(c.word_count) }}</TableCell>
            <TableCell class="hidden max-w-52 md:table-cell">
              <p class="line-clamp-1 text-xs text-muted-foreground">{{ c.summary || '—' }}</p>
            </TableCell>
            <TableCell class="hidden lg:table-cell text-xs text-muted-foreground">{{ formatRelative(c.updated_at) }}</TableCell>
            <TableCell class="text-right">
              <div class="flex justify-end gap-1 lg:opacity-0 lg:transition-opacity lg:group-hover:opacity-100">
                <Button variant="ghost" size="icon" class="size-8" title="查看内容" @click="openView(c)">
                  <Eye class="size-4" />
                </Button>
                <Button variant="ghost" size="icon" class="size-8" title="编辑" @click="openEdit(c)">
                  <Pencil class="size-4" />
                </Button>
                <Button variant="ghost" size="icon" class="size-8 text-primary" title="AI 重新生成" @click="startAi('regenerate_chapter', c)">
                  <RefreshCw class="size-4" />
                </Button>
                <Button variant="ghost" size="icon" class="size-8 text-muted-foreground hover:text-foreground" title="AI 补摘要" @click="startAi('generate_summary', c)">
                  <Wand2 class="size-4" />
                </Button>
                <Button variant="ghost" size="icon" class="size-8 text-destructive" title="删除" @click="deleting = c">
                  <Trash2 class="size-4" />
                </Button>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <!-- 章节编辑对话框 -->
    <Dialog v-model:open="editOpen">
      <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{{ editing ? `编辑章节 · 第 ${editing.chapter_no} 章` : '新增章节' }}</DialogTitle>
          <DialogDescription>{{ editing ? '修改章节标题、正文与摘要' : '手动创建新章节，章号自动递增' }}</DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-2">
          <div class="space-y-1.5">
            <Label>标题 <span class="text-destructive">*</span></Label>
            <Input v-model="chapterForm.title" />
          </div>
          <div class="space-y-1.5">
            <Label>正文 <span class="text-destructive">*</span>（{{ countWords(chapterForm.content) }} 字）</Label>
            <Textarea
              v-model="chapterForm.content"
              rows="16"
              class="resize-y font-serif leading-relaxed"
              placeholder="请输入章节正文…"
            />
          </div>
          <div class="space-y-1.5">
            <Label>摘要</Label>
            <Textarea v-model="chapterForm.summary" rows="2" placeholder="一句话摘要，展示在目录中（可选）" />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="editOpen = false">取消</Button>
          <Button :disabled="saving" @click="saveChapter">
            <LoaderCircle v-if="saving" class="size-4 animate-spin" />
            保存
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- 查看章节内容（只读） -->
    <Dialog v-model:open="viewOpen">
      <DialogContent class="max-h-[85vh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>第 {{ viewing?.chapter_no }} 章 {{ viewing?.title }}</DialogTitle>
          <DialogDescription v-if="viewing?.summary">摘要：{{ viewing.summary }}</DialogDescription>
        </DialogHeader>
        <div class="py-2">
          <p class="whitespace-pre-wrap font-serif text-[15px] leading-8">{{ viewing?.content }}</p>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="viewOpen = false">关闭</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- 删除确认 -->
    <ConfirmDialog
      :open="!!deleting"
      title="删除章节"
      :description="`确定要删除「第 ${deleting?.chapter_no} 章 ${deleting?.title ?? ''}」吗？此操作无法撤销。`"
      confirm-text="删除"
      :loading="deleteLoading"
      @update:open="(v: boolean) => !v && (deleting = null)"
      @confirm="confirmDelete"
    />

    <!-- AI 流式对话框 -->
    <StreamDialog
      v-if="aiTask"
      v-model:open="aiOpen"
      :title="aiTitle"
      :task-type="aiTask.type"
      :novel-id="novelId"
      :params="aiParams()"
      :mode="aiTask.mode"
      @done="() => { toast.success('AI 任务完成'); load() }"
      @update:open="(v: boolean) => { if (!v) { aiTask = null; loadRunningTask() } }"
    />

    <!-- 查看进行中任务进度（订阅已有任务，不新建） -->
    <StreamDialog
      v-if="runningTask"
      v-model:open="progressOpen"
      :title="`查看进度 · 任务 #${runningTask.id}`"
      :task-type="runningTask.task_type"
      :novel-id="novelId"
      mode="stream"
      :attach-task-id="runningTask.id"
      @done="load"
      @update:open="(v: boolean) => { if (!v) load() }"
    />
  </div>
</template>
