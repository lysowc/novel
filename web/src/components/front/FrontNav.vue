<script setup lang="ts">
import { BookOpen, LayoutDashboard, Search } from '@lucide/vue'
import { Input } from '@/components/ui/input'
import ThemeToggle from '@/components/common/ThemeToggle.vue'
import { useSiteStore } from '@/stores/site'
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

const props = withDefaults(defineProps<{ siteName?: string }>(), { siteName: '' })

const site = useSiteStore()
const router = useRouter()
const query = ref('')

onMounted(() => site.load())

function onSearch() {
  const q = query.value.trim()
  router.push({ path: '/category/0', query: q ? { keyword: q } : {} })
}
</script>

<template>
  <header class="sticky top-0 z-40 border-b bg-background/80 backdrop-blur-md">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
      <RouterLink to="/" class="group flex shrink-0 items-center gap-2.5">
        <span class="flex size-9 items-center justify-center rounded-xl bg-foreground text-background shadow-md transition-transform duration-300 group-hover:scale-105">
          <BookOpen class="size-5" />
        </span>
        <span class="text-lg font-bold tracking-wide">{{ props.siteName || site.name }}</span>
      </RouterLink>

      <nav class="hidden items-center gap-1 md:flex">
        <RouterLink to="/" class="rounded-lg px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground">
          首页
        </RouterLink>
        <RouterLink to="/category/0" class="rounded-lg px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground">
          全部小说
        </RouterLink>
      </nav>

      <!-- 搜索 -->
      <form class="hidden min-w-0 max-w-xs flex-1 md:block" @submit.prevent="onSearch">
        <div class="relative">
          <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            v-model="query"
            placeholder="搜索小说…"
            class="h-9 rounded-full bg-card pl-9 pr-3"
          />
        </div>
      </form>

      <div class="flex shrink-0 items-center gap-1.5">
        <ThemeToggle />
        <RouterLink to="/admin" class="ml-1 inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground">
          <LayoutDashboard class="size-4" />
          <span class="hidden sm:inline">后台</span>
        </RouterLink>
      </div>
    </div>
  </header>
</template>
