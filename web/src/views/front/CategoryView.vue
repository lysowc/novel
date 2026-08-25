<script setup lang="ts">import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

import { onMounted, ref, watch } from 'vue'
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
const categoryName = () =>
  categoryId() === 0
    ? '全部小说'
    : (categories.value.find((c) => c.id === categoryId())?.name ?? '分类')

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

onMounted(async () => {
  categories.value = (await fetchCategories()).list
  await load()
})

watch(() => route.params.id, () => {
  novels.value.page = 1
  keyword.value = ''
  load()
})
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <FrontNav />
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6">
      <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold">{{ categoryName() }}</h1>
          <p class="mt-1 text-sm text-muted-foreground">共 {{ novels.total }} 本小说</p>
        </div>
        <div class="flex w-full max-w-xs items-center gap-2">
          <Input
            v-model="keyword"
            placeholder="搜索书名 / 标签"
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
