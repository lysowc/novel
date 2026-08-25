<script setup lang="ts">import { Button } from '@/components/ui/button'

import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight, BookOpen, Flame } from '@lucide/vue'
import FrontNav from '@/components/front/FrontNav.vue'
import FrontFooter from '@/components/front/FrontFooter.vue'
import NovelCard from '@/components/front/NovelCard.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import CoverArt from '@/components/common/CoverArt.vue'
import { fetchHome } from '@/api'
import { useReaderStore } from '@/stores/reader'
import { formatRelative } from '@/lib/format'
import type { HomeData, Novel } from '@/types/api'

const router = useRouter()
const reader = useReaderStore()

const loading = ref(true)
const home = ref<HomeData>({ recent_updates: [], categories: [] })

const continueNovel = computed<{ novel: Novel; no: number } | null>(() => {
  // 用最近更新里能找到进度的小说
  const progress = reader.progress
  for (const n of home.value.recent_updates) {
    const p = progress[n.id]
    if (p) return { novel: n, no: p.no }
  }
  return null
})

onMounted(async () => {
  try {
    home.value = await fetchHome()
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <FrontNav />

    <!-- Hero -->
    <section class="relative overflow-hidden">
      <div class="pointer-events-none absolute inset-0 -z-10">
        <div class="absolute -top-40 left-1/2 h-[30rem] w-[64rem] -translate-x-1/2 rounded-full bg-gradient-to-b from-foreground/[0.07] to-transparent blur-3xl" />
      </div>
      <div class="mx-auto max-w-6xl px-4 pb-16 pt-20 text-center sm:px-6 sm:pb-20 sm:pt-28">
        <div class="fade-up">
          <span class="inline-flex items-center gap-1.5 rounded-full border bg-card px-3.5 py-1.5 text-xs font-medium tracking-wide text-muted-foreground shadow-sm">
            <Flame class="size-3.5 text-foreground" />
            灵感 · 创作 · 阅读
          </span>
          <h1 class="mt-7 text-4xl font-bold leading-tight tracking-tight sm:text-6xl">
            拾光小说
          </h1>
          <p class="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-muted-foreground sm:text-lg">
            让 AI 成为你的创作伙伴——从一闪而过的灵感，到完整的世界与故事。
          </p>
        </div>

        <!-- 继续阅读 -->
        <div v-if="continueNovel" class="fade-up mx-auto mt-10 max-w-xl" style="animation-delay: 0.08s">
          <RouterLink
            :to="`/read/${continueNovel.novel.id}/${continueNovel.no}`"
            class="group flex items-center gap-4 rounded-2xl border bg-card p-5 text-left shadow-md transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl"
          >
            <CoverArt
              :title="continueNovel.novel.title"
              :cover="continueNovel.novel.cover"
              aspect="portrait"
              title-size="sm"
              class="w-16 shrink-0 rounded-lg"
            />
            <div class="min-w-0 flex-1">
              <p class="text-xs font-medium tracking-wide text-muted-foreground">继续阅读</p>
              <p class="mt-1 truncate text-base font-semibold">{{ continueNovel.novel.title }}</p>
              <p class="mt-1 text-xs text-muted-foreground">
                第 {{ continueNovel.no }} 章 · 更新于 {{ formatRelative(continueNovel.novel.updated_at) }}
              </p>
              <div class="mt-3 h-1 w-full overflow-hidden rounded-full bg-muted">
                <div
                  class="h-full rounded-full bg-foreground transition-all duration-300"
                  :style="{ width: `${Math.round((reader.progress[continueNovel.novel.id]?.percent ?? 0) * 100)}%` }"
                />
              </div>
            </div>
            <ArrowRight class="size-5 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-1 group-hover:text-primary" />
          </RouterLink>
        </div>
      </div>
    </section>

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 sm:px-6">
      <!-- 分类 -->
      <section class="mt-8">
        <div class="flex flex-wrap items-center gap-2">
          <RouterLink
            to="/category/0"
            class="rounded-full border bg-card px-4 py-1.5 text-sm text-muted-foreground transition-all hover:border-primary/40 hover:text-foreground"
          >
            全部
          </RouterLink>
          <RouterLink
            v-for="c in home.categories"
            :key="c.id"
            :to="`/category/${c.id}`"
            class="rounded-full border bg-card px-4 py-1.5 text-sm text-muted-foreground transition-all hover:border-primary/40 hover:text-foreground"
          >
            {{ c.name }}
            <span class="ml-1 text-xs text-muted-foreground/60">{{ c.novel_count }}</span>
          </RouterLink>
        </div>
      </section>

      <!-- 最近更新 -->
      <section class="mt-10">
        <div class="mb-5 flex items-center justify-between">
          <h2 class="flex items-center gap-2 text-lg font-semibold">
            <BookOpen class="size-5 text-primary" />
            最近更新
          </h2>
          <RouterLink to="/category/0" class="inline-flex items-center gap-1 text-sm text-primary hover:underline">
            查看全部
            <ArrowRight class="size-3.5" />
          </RouterLink>
        </div>

        <LoadingState v-if="loading" :rows="8" />
        <EmptyState
          v-else-if="home.recent_updates.length === 0"
          title="还没有小说"
          description="去后台创建你的第一本小说吧"
        >
          <Button @click="router.push('/admin')">进入后台</Button>
        </EmptyState>
        <div v-else class="grid grid-cols-2 gap-5 sm:grid-cols-3 sm:gap-6 lg:grid-cols-4">
          <NovelCard v-for="n in home.recent_updates" :key="n.id" :novel="n" />
        </div>
      </section>
    </main>

    <FrontFooter />
  </div>
</template>
