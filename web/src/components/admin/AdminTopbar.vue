<script setup lang="ts">import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'

import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { KeyRound, LoaderCircle, LogOut, UserRound } from '@lucide/vue'
import { toast } from 'vue-sonner'
import ThemeToggle from '@/components/common/ThemeToggle.vue'
import { useAuthStore } from '@/stores/auth'
import { changePassword } from '@/api'

const router = useRouter()
const auth = useAuthStore()

const pwdOpen = ref(false)
const oldPwd = ref('')
const newPwd = ref('')
const confirmPwd = ref('')
const saving = ref(false)

async function onLogout() {
  await auth.logout()
  toast.success('已退出登录')
  router.push('/admin/login')
}

async function onChangePassword() {
  if (!oldPwd.value || !newPwd.value) {
    toast.error('请填写完整')
    return
  }
  if (newPwd.value !== confirmPwd.value) {
    toast.error('两次输入的新密码不一致')
    return
  }
  saving.value = true
  try {
    await changePassword(oldPwd.value, newPwd.value)
    toast.success('密码已修改')
    pwdOpen.value = false
    oldPwd.value = newPwd.value = confirmPwd.value = ''
  } catch {
    // toast 已在请求层提示
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="flex items-center gap-2">
    <ThemeToggle />
    <DropdownMenu>
      <DropdownMenuTrigger as-child>
        <Button variant="ghost" class="gap-2 px-2">
          <span class="flex size-7 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
            {{ (auth.username || 'A').slice(0, 1).toUpperCase() }}
          </span>
          <span class="hidden text-sm sm:inline">{{ auth.username }}</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" class="w-44">
        <DropdownMenuLabel class="flex items-center gap-2 text-xs text-muted-foreground">
          <UserRound class="size-3.5" />
          {{ auth.username }}
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem @select="pwdOpen = true">
          <KeyRound class="size-4" />
          修改密码
        </DropdownMenuItem>
        <DropdownMenuItem variant="destructive" @select="onLogout">
          <LogOut class="size-4" />
          退出登录
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>

    <Dialog v-model:open="pwdOpen">
      <DialogContent class="max-w-sm">
        <DialogHeader>
          <DialogTitle>修改密码</DialogTitle>
          <DialogDescription>修改后下次登录请使用新密码</DialogDescription>
        </DialogHeader>
        <div class="space-y-3 py-2">
          <div class="space-y-1.5">
            <Label>当前密码</Label>
            <Input v-model="oldPwd" type="password" placeholder="请输入当前密码" />
          </div>
          <div class="space-y-1.5">
            <Label>新密码</Label>
            <Input v-model="newPwd" type="password" placeholder="请输入新密码" />
          </div>
          <div class="space-y-1.5">
            <Label>确认新密码</Label>
            <Input v-model="confirmPwd" type="password" placeholder="再次输入新密码" />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="pwdOpen = false">取消</Button>
          <Button :disabled="saving" @click="onChangePassword">
            <LoaderCircle v-if="saving" class="size-4 animate-spin" />
            保存
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
