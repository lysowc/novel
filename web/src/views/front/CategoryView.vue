<script setup lang="ts">import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import FrontNav from '@/components/front/FrontNav.vue'
import FrontFooter from '@/components/front/FrontFooter.vue'
import NovelCard from '@/components/front/NovelCard.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PaginationBar from '@/components/common/PaginationBar.vue'
import { fetchCategories, fetchNovels } from '@/api'
import type { Novel, PageResult } from '@/types/api'

const route = useRoute()

const loading = ref(true)
const novels = ref<PageResult<Novel>>({ list: [], total: 0, page: 1, page_size: 12 })
const categories = ref<{ id: number; name: string; novel_count: number }[]>([])
const keyword = ref('')

const categoryId = () => Number(route.params.id || 0)
const categoryLabel = () =>
  categoryId() === 0
    ? '全部'
    : (categories.value.find((c) => c.id === categoryId())?.name ?? '分类')

const pageTitle = computed(() =>
  keyword.value.trim() ? `搜索「${keyword.value.trim()}」` : categoryId() === 0 ? '全部小说' : categoryLabel(),
)
const pageSubtitle = computed(() => {
  const base = categoryLabel()
  return keyword.value.trim() ? `在「${base}」中找到 ${novels.value.total} 本` : `共 ${novels.value.total} 本小说`
})

async function load() {
  loading.value = true
  try {
    novels.value = await fetchNovels({
      category_id: categoryId() || undefined,
      keyword: keyword.value || undefined,
      page: novels.value.page,
      page_size: 12,
    })
  } finally {
    loading.value = false
  }
}

function onSearch() {
  novels.value.page = 1
  load()
}

function clearKeyword() {
  keyword.value = ''
  novels.value.page = 1
  load()
}

onMounted(async () => {
  const kw = typeof route.query.keyword === 'string' ? route.query.keyword : ''
  keyword.value = kw
  categories.value = (await fetchCategories()).list
  await load()
})

watch(
  () => [route.params.id, route.query.keyword] as const,
  ([, kw]) => {
    novels.value.page = 1
    keyword.value = typeof kw === 'string' ? kw : ''
    load()
  },
)
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <FrontNav />
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6">
      <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold">{{ pageTitle }}</h1>
          <p class="mt-1 flex items-center gap-2 text-sm text-muted-foreground">
            {{ pageSubtitle }}
            <Button
              v-if="keyword"
              variant="ghost"
              size="sm"
              class="h-6 px-2 text-xs"
              @click="clearKeyword"
            >
              清除搜索
            </Button>
          </p>
        </div>
        <div class="flex w-full max-w-xs items-center gap-2">
          <Input
            v-model="keyword"
            placeholder="搜索书名 / 简介"
            class="h-9"
            @keyup.enter="onSearch"
          />
          <Button variant="secondary" size="sm" class="h-9" @click="onSearch">搜索</Button>
        </div>
      </div>

      <LoadingState v-if="loading" :rows="8" />
      <EmptyState
        v-else-if="novels.list.length === 0"
        title="没有找到小说"
        description="换个关键词或分类试试"
      />
      <template v-else>
        <div class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4">
          <NovelCard v-for="n in novels.list" :key="n.id" :novel="n" />
        </div>
        <PaginationBar
          v-model:page="novels.page"
          :page-size="novels.page_size"
          :total="novels.total"
          @update:page="load"
        />
      </template>
    </main>
    <FrontFooter />
  </div>
</template>
