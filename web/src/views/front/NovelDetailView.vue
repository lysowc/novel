<script setup lang="ts">import { Button } from '@/components/ui/button'

import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, BookOpen, Clock3, FileText, Layers, ListTree, Play } from '@lucide/vue'
import FrontNav from '@/components/front/FrontNav.vue'
import FrontFooter from '@/components/front/FrontFooter.vue'
import CoverArt from '@/components/common/CoverArt.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import { fetchNovelChapters, fetchNovelDetail } from '@/api'
import { useReaderStore } from '@/stores/reader'
import { formatNumber, formatRelative } from '@/lib/format'
import type { ChapterListItem, NovelDetail } from '@/types/api'

const route = useRoute()
const router = useRouter()
const reader = useReaderStore()

const id = computed(() => String(route.params.id))
const loading = ref(true)
const novel = ref<NovelDetail | null>(null)
const chapters = ref<ChapterListItem[]>([])
const chapterListRef = ref<HTMLElement | null>(null)

function scrollToChapters() {
  chapterListRef.value?.scrollIntoView({ behavior: 'smooth' })
}

const startNo = computed(() => {
  const p = reader.getProgress(Number(id.value))
  if (p && chapters.value.some((c) => c.chapter_no === p.no)) return p.no
  return novel.value?.first_no || 1
})

const tags = computed(() =>
  (novel.value?.tags || '').split(/[,，]/).map((t) => t.trim()).filter(Boolean),
)

const statusText = computed(() => {
  switch (novel.value?.status) {
    case 'draft': return { label: '连载中', cls: 'bg-sky-500/10 text-sky-600 dark:text-sky-400' }
    case 'published': return { label: '连载中', cls: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' }
    case 'finished': return { label: '已完结', cls: 'bg-amber-500/10 text-amber-600 dark:text-amber-400' }
    default: return { label: '—', cls: '' }
  }
})

onMounted(async () => {
  try {
    novel.value = await fetchNovelDetail(id.value)
    const res = await fetchNovelChapters(id.value)
    chapters.value = res.list
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <FrontNav />
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6">
      <Button variant="ghost" size="sm" class="mb-5 -ml-2 text-muted-foreground" @click="router.back()">
        <ArrowLeft class="size-4" />
        返回
      </Button>

      <LoadingState v-if="loading" variant="list" :rows="3" />

      <template v-else-if="novel">
        <div class="grid gap-8 lg:grid-cols-[300px_1fr]">
          <!-- 左侧封面与信息 -->
          <div class="space-y-6">
            <CoverArt
              :title="novel.title"
              :cover="novel.cover"
              aspect="portrait"
              title-size="lg"
              class="w-full shadow-xl shadow-primary/5"
            />
            <div class="flex flex-wrap gap-2">
              <span
                v-for="t in tags"
                :key="t"
                class="rounded-full border bg-card px-2.5 py-1 text-xs text-muted-foreground"
              >
                # {{ t }}
              </span>
              <span v-if="tags.length === 0" class="text-xs text-muted-foreground/60">暂无标签</span>
            </div>
          </div>

          <!-- 右侧信息 -->
          <div class="min-w-0">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h1 class="text-3xl font-bold tracking-tight">{{ novel.title }}</h1>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                  <span class="rounded-full bg-accent px-2.5 py-1 font-medium text-accent-foreground">
                    {{ novel.category_name || '未分类' }}
                  </span>
                  <span class="rounded-full px-2.5 py-1 font-medium" :class="statusText.cls">
                    {{ statusText.label }}
                  </span>
                </div>
              </div>
            </div>

            <p class="mt-5 text-sm leading-7 text-muted-foreground">
              {{ novel.description || '作者还没有写简介。' }}
            </p>

            <!-- 统计 -->
            <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
              <div class="rounded-xl border bg-card p-3 text-center">
                <FileText class="mx-auto size-4 text-primary" />
                <p class="mt-1.5 text-base font-semibold">{{ formatNumber(novel.word_count) }}</p>
                <p class="text-[11px] text-muted-foreground">总字数</p>
              </div>
              <div class="rounded-xl border bg-card p-3 text-center">
                <Layers class="mx-auto size-4 text-primary" />
                <p class="mt-1.5 text-base font-semibold">{{ novel.chapter_count }}</p>
                <p class="text-[11px] text-muted-foreground">章节数</p>
              </div>
              <div class="rounded-xl border bg-card p-3 text-center">
                <Clock3 class="mx-auto size-4 text-primary" />
                <p class="mt-1.5 text-sm font-semibold">{{ formatRelative(novel.updated_at) }}</p>
                <p class="text-[11px] text-muted-foreground">最近更新</p>
              </div>
              <div class="rounded-xl border bg-card p-3 text-center">
                <BookOpen class="mx-auto size-4 text-primary" />
                <p class="mt-1.5 text-base font-semibold">{{ novel.first_no ? '可读' : '待更' }}</p>
                <p class="text-[11px] text-muted-foreground">阅读状态</p>
              </div>
            </div>

            <!-- 操作 -->
            <div class="mt-6 flex flex-wrap gap-3">
              <Button
                :size="'lg'"
                class="gap-2"
                :disabled="!novel.first_no"
                @click="router.push(`/read/${novel.id}/${startNo}`)"
              >
                <Play class="size-4" />
                {{ reader.getProgress(novel.id) ? '继续阅读' : '开始阅读' }}
              </Button>
              <Button
                variant="outline"
                size="lg"
                class="gap-2"
                @click="scrollToChapters"
              >
                <ListTree class="size-4" />
                章节目录
              </Button>
            </div>
          </div>
        </div>

        <!-- 章节目录 -->
        <section ref="chapterListRef" id="chapter-list" class="mt-12">
          <h2 class="mb-4 flex items-center gap-2 text-lg font-semibold">
            <ListTree class="size-5 text-primary" />
            章节目录
            <span class="text-sm font-normal text-muted-foreground">（{{ chapters.length }}）</span>
          </h2>
          <EmptyState v-if="chapters.length === 0" title="还没有章节" description="作者正在努力创作中" />
          <div v-else class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            <RouterLink
              v-for="c in chapters"
              :key="c.chapter_no"
              :to="`/read/${novel.id}/${c.chapter_no}`"
              class="group flex items-center justify-between gap-3 rounded-xl border bg-card px-4 py-3 transition-all hover:border-primary/30 hover:shadow-sm"
            >
              <div class="flex min-w-0 items-center gap-3">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-accent text-xs font-semibold text-accent-foreground">
                  {{ c.chapter_no }}
                </span>
                <span class="truncate text-sm font-medium group-hover:text-primary">{{ c.title }}</span>
              </div>
              <span class="shrink-0 text-[11px] text-muted-foreground">{{ formatNumber(c.word_count) }} 字</span>
            </RouterLink>
          </div>
        </section>
      </template>
    </main>
    <FrontFooter />
  </div>
</template>
