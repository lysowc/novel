<script setup lang="ts">import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { BookOpen, KeyRound, LoaderCircle, UserRound } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import { MOCK_USERNAME, MOCK_PASSWORD, USE_MOCK } from '@/api'
import ThemeToggle from '@/components/common/ThemeToggle.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const theme = useThemeStore()

const username = ref('')
const password = ref('')
const loading = ref(false)

onMounted(() => {
  theme.init()
  if (auth.isLoggedIn) {
    router.replace('/admin')
    return
  }
  if (USE_MOCK) {
    username.value = MOCK_USERNAME
    password.value = MOCK_PASSWORD
  }
})

async function onSubmit() {
  if (!username.value || !password.value) {
    toast.error('请输入用户名和密码')
    return
  }
  loading.value = true
  try {
    await auth.login(username.value, password.value)
    toast.success('登录成功，欢迎回来')
    const redirect = (route.query.redirect as string) || '/admin'
    router.replace(redirect)
  } catch (e) {
    toast.error((e as Error).message || '登录失败')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-background px-4">
    <!-- 背景光晕 -->
    <div class="pointer-events-none absolute inset-0 -z-10">
      <div class="absolute -top-40 left-1/2 h-[28rem] w-[36rem] -translate-x-1/2 rounded-full bg-gradient-to-br from-foreground/10 via-foreground/5 to-foreground/10 blur-3xl" />
    </div>

    <div class="absolute right-5 top-5">
      <ThemeToggle />
    </div>

    <div class="fade-up w-full max-w-sm">
      <div class="mb-8 text-center">
        <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-foreground text-background shadow-xl">
          <BookOpen class="size-7" />
        </span>
        <h1 class="mt-4 text-2xl font-bold tracking-tight">拾光小说 · 后台</h1>
        <p class="mt-1.5 text-sm text-muted-foreground">登录以管理你的创作世界</p>
      </div>

      <div class="rounded-2xl border bg-card p-6 shadow-xl shadow-primary/5">
        <form class="space-y-4" @submit.prevent="onSubmit">
          <div class="space-y-1.5">
            <Label for="username">用户名</Label>
            <div class="relative">
              <UserRound class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                id="username"
                v-model="username"
                class="h-10 pl-9"
                placeholder="请输入用户名"
                autocomplete="username"
              />
            </div>
          </div>
          <div class="space-y-1.5">
            <Label for="password">密码</Label>
            <div class="relative">
              <KeyRound class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                id="password"
                v-model="password"
                type="password"
                class="h-10 pl-9"
                placeholder="请输入密码"
                autocomplete="current-password"
                @keyup.enter="onSubmit"
              />
            </div>
          </div>
          <Button type="submit" class="h-10 w-full gap-2" :disabled="loading">
            <LoaderCircle v-if="loading" class="size-4 animate-spin" />
            登 录
          </Button>
        </form>

        <p v-if="USE_MOCK" class="mt-4 rounded-lg bg-muted px-3 py-2 text-center text-xs text-muted-foreground">
          Mock 模式：账号 {{ MOCK_USERNAME }} / 密码 {{ MOCK_PASSWORD }}
        </p>
      </div>
    </div>
  </div>
</template>
