<script setup lang="ts">import { Button } from '@/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'

import { computed } from 'vue'
import { Check, Monitor, Moon, Sun } from '@lucide/vue'
import { useThemeStore, type ThemeMode } from '@/stores/theme'

const theme = useThemeStore()

const modes: { value: ThemeMode; label: string; icon: typeof Sun }[] = [
  { value: 'light', label: '浅色', icon: Sun },
  { value: 'dark', label: '深色', icon: Moon },
  { value: 'system', label: '跟随系统', icon: Monitor },
]

const current = computed(() => modes.find((m) => m.value === theme.mode) ?? modes[2])
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="ghost" size="icon" class="relative" :title="`主题：${current.label}`">
        <component :is="current.icon" class="size-[1.15rem]" />
        <span class="sr-only">切换主题</span>
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-40">
      <DropdownMenuLabel class="text-xs">主题模式</DropdownMenuLabel>
      <DropdownMenuItem
        v-for="m in modes"
        :key="m.value"
        :class="theme.mode === m.value ? 'bg-accent text-accent-foreground' : ''"
        @select="theme.setMode(m.value)"
      >
        <component :is="m.icon" class="size-4" />
        {{ m.label }}
        <Check v-if="theme.mode === m.value" class="ml-auto size-4" />
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
