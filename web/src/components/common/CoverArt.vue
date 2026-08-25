<script setup lang="ts">
import { computed } from 'vue'
import { coverGradient } from '@/lib/format'
import { cn } from '@/lib/utils'

type Aspect = 'portrait' | 'landscape' | 'square' | 'auto'

const props = withDefaults(
  defineProps<{
    title: string
    cover?: string
    aspect?: Aspect
    /** 标题字号大小 */
    titleSize?: 'sm' | 'md' | 'lg'
    class?: string
  }>(),
  { cover: '', aspect: 'portrait', titleSize: 'md' },
)

const gradient = computed(() => coverGradient(props.title))

const aspectClass: Record<Aspect, string> = {
  portrait: 'aspect-[3/4]',
  landscape: 'aspect-[16/9]',
  square: 'aspect-square',
  auto: 'aspect-auto',
}

const titleClass: Record<string, string> = {
  sm: 'text-sm leading-snug',
  md: 'text-base leading-snug',
  lg: 'text-xl leading-normal',
}
</script>

<template>
  <div
    class="cover-art relative overflow-hidden rounded-xl bg-muted shadow-sm transition-transform duration-300"
    :class="cn(aspectClass[aspect], props.class)"
    :style="cover ? undefined : { background: gradient }"
  >
    <img
      v-if="cover"
      :src="cover"
      :alt="title"
      loading="lazy"
      class="absolute inset-0 h-full w-full object-cover"
    />
    <div v-else class="cover-shine pointer-events-none absolute inset-0" />
    <div
      v-if="!cover"
      class="relative flex h-full w-full items-center justify-center px-3 text-center text-white"
    >
      <span class="cover-title line-clamp-4" :class="titleClass[titleSize]">
        {{ title || '未命名' }}
      </span>
    </div>
    <slot />
  </div>
</template>
