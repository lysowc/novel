<script setup lang="ts">
import { Button } from '@/components/ui/button'

import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft, ChevronLeft, ChevronRight, ListTree, Moon, Sun, Settings,
  TextCursorInput, AlignCenter, AlignRight, BookOpen, LoaderCircle,
} from '@lucide/vue'
import {
  Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger,
} from '@/components/ui/sheet'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel,
  DropdownMenuRadioGroup, DropdownMenuRadioItem, DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import Slider from '@/components/ui/slider/Slider.vue'
import { fetchChapterRead, fetchNovelChapters, fetchNovelDetail } from '@/api'
import { useReaderStore, WIDTH_STYLES, type ReaderWidth } from '@/stores/reader'
import { useThemeStore } from '@/stores/theme'
import { formatNumber } from '@/lib/format'
import type { Chapter, ChapterListItem, NovelDetail } from '@/types/api'

const route = useRoute()
const router = useRouter()
const reader = useReaderStore()
const theme = useThemeStore()

const novelId = computed(() => String(route.params.id))
const chapterNo = computed(() => Number(route.params.no))

const loading = ref(true)
const novel = ref<NovelDetail | null>(null)
const chapter = ref<Chapter | null>(null)
const prevNo = ref<number | null>(null)
const nextNo = ref<number | null>(null)
const chapters = ref<ChapterListItem[]>([])
const contentRef = ref<HTMLElement | null>(null)
const catalogOpen = ref(false)

const paragraphs = computed(() =>
  (chapter.value?.content || '').split(/\n+/).filter((p) => p.trim()),
)

const fontSizePx = computed(() => `${reader.fontSize}px`)
const widthClass = computed(() => WIDTH_STYLES[reader.width])

let saveTimer: ReturnType<typeof setTimeout> | null = null
let routeNo: number | null = null

async function load(no: number) {
  loading.value = true
  routeNo = no
  try {
    const novelRes = await fetchNovelDetail(novelId.value)
    const chapterRes = await fetchChapterRead(novelId.value, no)
    novel.value = novelRes
    chapter.value = chapterRes.chapter
    prevNo.value = chapterRes.prev_no
    nextNo.value = chapterRes.next_no
    chapters.value = (await fetchNovelChapters(novelId.value)).list
    document.title = `${chapterRes.chapter.title} · ${novelRes.title}`
  } finally {
    loading.value = false
    await restoreScroll()
  }
}

async function restoreScroll() {
  await new Promise((r) => setTimeout(r, 40))
  const saved = reader.getProgress(Number(novelId.value))
  if (saved && saved.no === chapterNo.value && saved.scrollTop > 0 && contentRef.value) {
    contentRef.value.scrollTop = saved.scrollTop
  }
}

function onScroll() {
  if (saveTimer) return
  saveTimer = setTimeout(() => {
    saveTimer = null
    saveProgress()
  }, 800)
}

function saveProgress() {
  const el = contentRef.value
  if (!el || !chapter.value || !routeNo) return
  const percent =
    el.scrollHeight > el.clientHeight ? el.scrollTop / (el.scrollHeight - el.clientHeight) : 0
  reader.saveProgress(Number(novelId.value), {
    no: routeNo,
    percent,
    scrollTop: el.scrollTop,
    updatedAt: Date.now(),
  })
}

function goChapter(no: number | null) {
  if (!no) return
  saveProgress()
  router.push(`/read/${novelId.value}/${no}`)
}

// 路由变化（切换章节 / 换小说）时重新加载
watch(
  () => [route.params.id, route.params.no] as const,
  ([, no]) => {
    if (contentRef.value) contentRef.value.scrollTop = 0
    load(Number(no))
  },
)

onMounted(() => {
  load(chapterNo.value)
})

onBeforeUnmount(() => {
  if (saveTimer) clearTimeout(saveTimer)
  saveProgress()
})
</script>

<template>
  <div class="flex h-screen flex-col bg-background">
    <!-- 顶部窄栏 -->
    <header class="z-40 shrink-0 border-b bg-background/85 backdrop-blur-md">
      <div class="mx-auto flex h-12 max-w-5xl items-center justify-between gap-2 px-3 sm:px-4">
        <div class="flex min-w-0 items-center gap-1.5">
          <Button variant="ghost" size="icon" class="size-8" title="返回详情" @click="router.push(`/novel/${novelId}`)">
            <ArrowLeft class="size-4" />
          </Button>
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold leading-tight">{{ novel?.title }}</p>
            <p class="truncate text-[11px] leading-tight text-muted-foreground">
              第 {{ chapterNo }} 章 · {{ chapter?.title }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-0.5">
          <Sheet v-model:open="catalogOpen">
            <SheetTrigger as-child>
              <Button variant="ghost" size="sm" class="gap-1.5 text-muted-foreground">
                <ListTree class="size-4" />
                <span class="hidden sm:inline">目录</span>
              </Button>
            </SheetTrigger>
            <SheetContent side="right" class="w-[86vw] max-w-sm p-0">
              <SheetHeader class="border-b px-5 py-4">
                <SheetTitle class="text-base">章节目录</SheetTitle>
              </SheetHeader>
              <div class="max-h-[calc(100vh-5rem)] overflow-y-auto p-3">
                <button
                  v-for="c in chapters"
                  :key="c.chapter_no"
                  class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors hover:bg-accent"
                  :class="c.chapter_no === chapterNo ? 'bg-accent' : ''"
                  @click="catalogOpen = false; goChapter(c.chapter_no)"
                >
                  <span
                    class="flex size-6 shrink-0 items-center justify-center rounded-md text-xs font-semibold"
                    :class="c.chapter_no === chapterNo ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                  >
                    {{ c.chapter_no }}
                  </span>
                  <span class="min-w-0 flex-1 truncate text-sm" :class="c.chapter_no === chapterNo ? 'font-medium' : ''">
                    {{ c.title }}
                  </span>
                  <span class="shrink-0 text-[11px] text-muted-foreground">{{ formatNumber(c.word_count) }} 字</span>
                </button>
              </div>
            </SheetContent>
          </Sheet>

          <!-- 阅读设置 -->
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <Button variant="ghost" size="sm" class="gap-1.5 text-muted-foreground">
                <Settings class="size-4" />
                <span class="hidden sm:inline">设置</span>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-64">
              <DropdownMenuLabel class="flex items-center gap-2 text-xs">
                <TextCursorInput class="size-3.5" />
                字号 {{ reader.fontSize }}px
              </DropdownMenuLabel>
              <div class="flex items-center gap-2 px-2 pb-2">
                <Button variant="outline" size="icon" class="size-7" @click="reader.setFontSize(reader.fontSize - 2)">
                  <span class="text-xs">A-</span>
                </Button>
                <Slider
                  :model-value="[reader.fontSize]"
                  :min="14"
                  :max="24"
                  :step="1"
                  class="flex-1"
                  @update:model-value="(v: number[] | undefined) => reader.setFontSize(v?.[0] ?? 18)"
                />
                <Button variant="outline" size="icon" class="size-7" @click="reader.setFontSize(reader.fontSize + 2)">
                  <span class="text-xs">A+</span>
                </Button>
              </div>

              <DropdownMenuSeparator />

              <DropdownMenuLabel class="flex items-center gap-2 text-xs">
                <AlignCenter class="size-3.5" />
                阅读宽度
              </DropdownMenuLabel>
              <DropdownMenuRadioGroup
                :model-value="reader.width"
                @update:model-value="(v: unknown) => reader.setWidth(String(v) as ReaderWidth)"
              >
                <DropdownMenuRadioItem value="narrow">
                  <AlignCenter class="size-3.5" /> 窄
                </DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="medium">
                  <AlignCenter class="size-3.5" /> 中
                </DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="wide">
                  <AlignRight class="size-3.5" /> 宽
                </DropdownMenuRadioItem>
              </DropdownMenuRadioGroup>

              <DropdownMenuSeparator />

              <DropdownMenuLabel class="text-xs">主题</DropdownMenuLabel>
              <DropdownMenuItem @select="theme.setMode('light')">
                <Sun class="size-4" /> 日间模式
              </DropdownMenuItem>
              <DropdownMenuItem @select="theme.setMode('dark')">
                <Moon class="size-4" /> 夜间模式
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </header>

    <!-- 正文（独立滚动容器） -->
    <div ref="contentRef" class="min-h-0 flex-1 overflow-y-auto" @scroll.passive="onScroll">
      <div v-if="loading" class="mx-auto flex max-w-3xl flex-col items-center gap-3 px-6 py-24 text-muted-foreground">
        <LoaderCircle class="size-7 animate-spin text-primary" />
        <p class="text-sm">正在加载章节…</p>
      </div>

      <div v-else-if="chapter" class="mx-auto w-full px-5 pb-10 pt-8 sm:px-8" :class="widthClass">
        <article>
          <header class="mb-10 text-center">
            <p class="text-xs tracking-widest text-muted-foreground">第 {{ chapterNo }} 章</p>
            <h1 class="mt-2 font-serif text-2xl font-bold" :style="{ fontSize: `calc(${fontSizePx} * 1.3)` }">
              {{ chapter.title }}
            </h1>
            <p class="mt-3 text-xs text-muted-foreground">
              {{ formatNumber(chapter.word_count) }} 字 · {{ chapter.updated_at?.slice(0, 10) }}
            </p>
          </header>

          <div class="prose-novel" :style="{ fontSize: fontSizePx }">
            <p v-for="(p, i) in paragraphs" :key="i">{{ p }}</p>
          </div>
        </article>

        <!-- 底部导航 -->
        <nav class="mt-14 flex items-center justify-between gap-3 border-t pt-6">
          <Button variant="outline" class="gap-2" :disabled="!prevNo" @click="goChapter(prevNo)">
            <ChevronLeft class="size-4" />
            上一章
          </Button>
          <Button variant="ghost" size="sm" class="text-muted-foreground" @click="router.push(`/novel/${novelId}`)">
            <BookOpen class="size-4" />
            返回详情
          </Button>
          <Button variant="outline" class="gap-2" :disabled="!nextNo" @click="goChapter(nextNo)">
            下一章
            <ChevronRight class="size-4" />
          </Button>
        </nav>
      </div>
    </div>
  </div>
</template>
