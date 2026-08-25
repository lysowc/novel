<script setup lang="ts">import { Button } from '@/components/ui/button'

import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Menu } from '@lucide/vue'
import { Sheet, SheetContent } from '@/components/ui/sheet'
import AdminSidebar from '@/components/admin/AdminSidebar.vue'
import AdminTopbar from '@/components/admin/AdminTopbar.vue'

const route = useRoute()
const mobileOpen = ref(false)

const pageTitle = computed(() => (route.meta.title as string) || '后台管理')
</script>

<template>
  <div class="flex min-h-screen bg-muted/30 dark:bg-background">
    <!-- 桌面侧边栏 -->
    <AdminSidebar class="hidden lg:flex" />

    <!-- 移动端抽屉 -->
    <Sheet v-model:open="mobileOpen">
      <SheetContent side="left" class="w-64 p-0">
        <AdminSidebar class="h-full" @navigate="mobileOpen = false" />
      </SheetContent>
    </Sheet>

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b bg-background/85 px-4 backdrop-blur-md sm:px-6">
        <Button variant="ghost" size="icon" class="lg:hidden" @click="mobileOpen = true">
          <Menu class="size-5" />
        </Button>
        <div class="min-w-0">
          <h1 class="truncate text-lg font-semibold">{{ pageTitle }}</h1>
        </div>
        <AdminTopbar class="ml-auto" />
      </header>

      <main class="flex-1 px-4 py-8 sm:px-6 lg:px-10">
        <RouterView v-slot="{ Component }">
          <Transition name="fade" mode="out-in">
            <component :is="Component" />
          </Transition>
        </RouterView>
      </main>
    </div>
  </div>
</template>
