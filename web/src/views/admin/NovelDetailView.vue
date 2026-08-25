<script setup lang="ts">import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Button } from '@/components/ui/button'

import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, ExternalLink } from '@lucide/vue'
import CoverArt from '@/components/common/CoverArt.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import InfoTab from './novel/InfoTab.vue'
import SettingTab from './novel/SettingTab.vue'
import OutlineTab from './novel/OutlineTab.vue'
import ChaptersTab from './novel/ChaptersTab.vue'
import MemoryTab from './novel/MemoryTab.vue'
import { fetchAdminNovel } from '@/api'
import { formatNumber } from '@/lib/format'
import type { NovelDetail } from '@/types/api'

const route = useRoute()
const router = useRouter()
const id = computed(() => String(route.params.id))

const loading = ref(true)
const novel = ref<NovelDetail | null>(null)
const active = ref('info')

const tabItems = [
  { value: 'info', label: '信息' },
  { value: 'setting', label: '设定' },
  { value: 'outline', label: '大纲' },
  { value: 'chapters', label: '章节' },
  { value: 'memory', label: '记忆' },
]

async function reload() {
  novel.value = await fetchAdminNovel(id.value)
}

onMounted(async () => {
  try {
    novel.value = await fetchAdminNovel(id.value)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
      <Button variant="ghost" size="sm" class="-ml-2 text-muted-foreground" @click="router.push('/admin/novels')">
        <ArrowLeft class="size-4" />
        返回列表
      </Button>
      <Button variant="outline" size="sm" class="gap-1.5" :disabled="!novel" @click="router.push(`/novel/${id}`)">
        <ExternalLink class="size-4" />
        前台预览
      </Button>
    </div>

    <LoadingState v-if="loading" variant="list" :rows="2" />

    <template v-else-if="novel">
      <!-- 头部信息 -->
      <div class="mb-6 flex items-center gap-4 rounded-2xl border bg-card p-5 shadow-sm">
        <CoverArt :title="novel.title" :cover="novel.cover" aspect="portrait" title-size="md" class="w-20 shrink-0" />
        <div class="min-w-0 flex-1">
          <h1 class="truncate text-xl font-bold">{{ novel.title }}</h1>
          <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
            <span>{{ novel.category_name || '未分类' }}</span>
            <span>{{ formatNumber(novel.word_count) }} 字</span>
            <span>{{ novel.chapter_count }} 章</span>
            <span>{{ novel.is_public ? '公开' : '私密' }}</span>
          </div>
          <p class="mt-2 line-clamp-1 text-sm text-muted-foreground">{{ novel.description || '暂无简介' }}</p>
        </div>
      </div>

      <Tabs v-model="active" class="w-full">
        <TabsList class="grid w-full grid-cols-5 sm:w-auto">
          <TabsTrigger v-for="t in tabItems" :key="t.value" :value="t.value">
            {{ t.label }}
          </TabsTrigger>
        </TabsList>
        <TabsContent value="info" class="mt-5">
          <InfoTab :novel-id="Number(id)" :novel="novel" @saved="reload" />
        </TabsContent>
        <TabsContent value="setting" class="mt-5">
          <SettingTab :novel-id="Number(id)" />
        </TabsContent>
        <TabsContent value="outline" class="mt-5">
          <OutlineTab :novel-id="Number(id)" />
        </TabsContent>
        <TabsContent value="chapters" class="mt-5">
          <ChaptersTab :novel-id="Number(id)" />
        </TabsContent>
        <TabsContent value="memory" class="mt-5">
          <MemoryTab :novel-id="Number(id)" />
        </TabsContent>
      </Tabs>
    </template>
  </div>
</template>
